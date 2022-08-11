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

	const PARAMETER_NAME_MAIL_QUEUE_ENTRY_ID = 'mailQueueEntry_id';

	/** @var string */
	protected $queueEntryClass;

	/** @var \Kdyby\Doctrine\EntityManager */
	protected $em;

	/** @var string */
	protected $mutexFile;

	/** @var string */
	protected $mutexTimeFile;

	/** @var \Nette\Mail\IMailer */
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

	public function setMailer(\Nette\Mail\IMailer $mailer) {
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
	public function enqueue(\Nette\Mail\Message $message, $custom = []) {
		$entry = $this->createQueueEntry($message, $custom);
		$this->em->persist($entry);
		$this->em->flush($entry);

		$this->backgroundQueueService->publish($this->backgroundQueueCallbackName, [$entry->getId()]);

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
		$this->em->flush($entry);

		return TRUE;
	}


}
