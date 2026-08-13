<?php

namespace ADT\MailQueue\Service;

use ADT\MailQueue\Entity;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @method onQueueDrained(OutputInterface|NULL $output)
 */
class QueueService {

	use \Nette\SmartObject;

	const MUTEX_TIME_FORMAT = DATE_W3C;

	/** @var string */
	protected $queueEntryClass;

	/** @var EntityManagerInterface */
	protected $em;

	/** @var string */
	protected $mutexFile;

	/** @var string */
	protected $mutexTimeFile;

	/** @var \Nette\Mail\Mailer */
	protected $mailer;

	/** @var IMessenger */
	protected $messenger;

	/** @var \Tracy\Logger */
	protected $logger;

	/** @var NULL|callable */
	protected $sendErrorHandler;

	/** @var callable[] */
	public $onQueueDrained = [];

	/** @var int */
	protected $lockTimeout;

	/** @var int */
	protected $limit;

	/** @var \ADT\BackgroundQueue\BackgroundQueue */
	protected $backgroundQueueService;

	/** @var string */
	protected $backgroundQueueCallbackName;

	/** @var bool|null Detected lazily via reflection - see flushEntry() */
	protected $emSupportsSingleEntityFlush;

	public function __construct($config, EntityManagerInterface $em) {
		if (! is_dir($config['tempDir'])) {
			mkdir($config['tempDir']);
		}

		$this->mutexFile = 'nette.safe://' . $config['tempDir'] . '/adt-mail-queue.lock';
		$this->mutexTimeFile = 'nette.safe://' . $config['tempDir'] . '/adt-mail-queue.lock.timestamp';

		$this->lockTimeout = $config['lockTimeout'];
		$this->limit = $config['limit'];
		$this->queueEntryClass = $config['queueEntityClass'];
		$this->em = $em;
		$this->logger = \Tracy\Debugger::getLogger();

		$this->backgroundQueueService = $config['backgroundQueueService'];
		$this->backgroundQueueCallbackName = $config['backgroundQueueCallbackName'];
	}

	public function setMailer(\Nette\Mail\Mailer $mailer) {
		$this->mailer = $mailer;
		return $this;
	}

	public function setMessenger(IMessenger $messenger) {
		$this->messenger = $messenger;
		return $this;
	}

	public function setSendErrorHandler(callable $handler) {
		$this->sendErrorHandler = $handler;
		return $this;
	}

	/**
	 * @param \Nette\Mail\Message $message
	 * @param array|callable $custom
	 * @return Entity\AbstractMailQueueEntry
	 */
	protected function createQueueEntry(\Nette\Mail\Message $message, $custom = []) {
		/** @var Entity\AbstractMailQueueEntry $entry */
		$entry = new $this->queueEntryClass;
		$entry->setCreatedAt(new \DateTime);
		$entry->setFrom(array_keys($message->getFrom())[0]);
		$entry->setSubject($message->getSubject());
		$entry->setMessage($message);

		if (is_callable($custom)) {
			$custom($entry);
		} else {
			foreach ($custom as $field => $value) {
				$setter = 'set' . ucfirst($field);
				$entry->$setter($value);
			}
		}

		return $entry;
	}


	/**
	 * @param \Nette\Mail\Message $message
	 * @param array|callable $custom
	 * @return Entity\AbstractMailQueueEntry
	 */
	public function enqueue(\Nette\Mail\Message $message, $custom = [], ?int $backgroundQueuePriority = null) {
		$entry = $this->createQueueEntry($message, $custom);
		$this->em->persist($entry);
		$this->flushEntry($entry);

		$this->backgroundQueueService->publish($this->backgroundQueueCallbackName, [$entry->getId()], null, null, false, null, $backgroundQueuePriority);

		return $entry;
	}

	protected function send(Entity\AbstractMailQueueEntry $entry) {
		if ($this->mailer) {
			$this->mailer->send($entry->getMessage());
		} else {
			$this->messenger->send($entry);
		}
	}


	/**
	 * @param int $entryId
	 * @return bool
	 *
	 * new_rabbit
	 */
	public function process(int $entryId) {
		$entry = $this->em->find($this->queueEntryClass, $entryId);


		try {
			$this->send($entry);
			$entry->setSentAt(new \DateTime);
		} catch (\Exception $e) {
			$msg = $e->getMessage();

			if ($e->getPrevious()) {
				$msg .= " (" . $e->getPrevious()->getMessage() . ")";
			}

			if ($this->sendErrorHandler) {
				$sendException = $e instanceof \Nette\Mail\SendException
					? $e
					: new \Nette\Mail\SendException($msg, $e->getCode(), $e);

				$errorHandlerResponse = call_user_func($this->sendErrorHandler, $entry, $sendException);

				if (is_string($errorHandlerResponse)) {
					// error handled but should be logged
					$msg .= '; ' . $errorHandlerResponse;
				} else if ($errorHandlerResponse === NULL) {
					// error not handled
				} else {
					// error handled gracefully
					$msg = NULL;
				}
			}

			if ($msg !== NULL) {
				// mail report
				$error = 'id=' . $entry->getId() . ': ' . $msg;
				$this->logger->log($error, \Tracy\Logger::ERROR);
			}
		}

		$this->em->persist($entry);
		$this->flushEntry($entry);

		return TRUE;
	}

	/**
	 * Flushes the queue entry without touching other pending changes when possible.
	 *
	 * ORM 2 supports flushing a single entity, which keeps the caller's unit of work
	 * untouched - enqueueing a mail must not write unrelated pending changes to DB.
	 * ORM 3 removed single-entity flush, so there the full flush() is the only option
	 * and callers should not enqueue mails with an unflushed unit of work.
	 */
	protected function flushEntry(Entity\AbstractMailQueueEntry $entry): void {
		if ($this->emSupportsSingleEntityFlush === null) {
			$this->emSupportsSingleEntityFlush = (new \ReflectionMethod($this->em, 'flush'))->getNumberOfParameters() > 0;
		}

		if ($this->emSupportsSingleEntityFlush) {
			$this->em->flush($entry);
		} else {
			$this->em->flush();
		}
	}

}
