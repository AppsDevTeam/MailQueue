<?php

namespace ADT\MailQueue\Service;

use ADT\MailQueue\Entity;
use Symfony\Component\Console\Output\OutputInterface;


/**
 * @method onQueueDrained(OutputInterface|NULL $output)
 */
class QueueService extends \Nette\Object {

	const MUTEX_TIME_FORMAT = DATE_W3C;
	const MUTEX_TIMEOUT = 600;

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

	public function __construct($tempDir, $queueEntryClass, \Kdyby\Doctrine\EntityManager $em) {
		$this->mutexFile = 'nette.safe://' . $tempDir . '/adt-mail-queue.lock';
		$this->mutexTimeFile = 'nette.safe://' . $tempDir . '/adt-mail-queue.lock.timestamp';

		$this->queueEntryClass = $queueEntryClass;
		$this->em = $em;
		$this->logger = \Tracy\Debugger::getLogger();
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
		$entry->createdAt = new \DateTime;
		$entry->from = array_keys($message->getFrom())[0];
		$entry->subject = $message->getSubject();
		$entry->message = $message;

		if (is_callable($custom)) {
			$custom($entry);
		} else {
			foreach ($custom as $field => $value) {
				$entry->$field = $value;
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
		return $entry;
	}

	protected function mutexLock(OutputInterface $output = NULL) {
		$now = new \DateTime;

		if ($output) {
			$output->write('Locking mutex ...');
		}

		// DISCLAIMER: This is NOT atomic mutex!
		// It is all we have right now and it should be enough for our purpose but in case it is not,
		// just make sure the "read, increment, write" operation is atomic and everything should work just fine.
		file_put_contents($this->mutexFile, $mutexValue = ((@file_get_contents($this->mutexFile) ?: 0) + 1)); // @ - file may not exist yet

		if ($mutexValue !== 1) {
			if ($output) {
				$output->writeln(' already locked');
			}

			$mutexCreatedAt = \DateTime::createFromFormat(static::MUTEX_TIME_FORMAT, file_get_contents($this->mutexTimeFile));
			$mutexLockedFor = $now->getTimestamp() - $mutexCreatedAt->getTimestamp();

			if ($output) {
				$output->writeln('Mutex has been locked for ' . $mutexLockedFor . ' seconds');
			}

			if ($mutexLockedFor >= static::MUTEX_TIMEOUT) {
				$this->mutexUnlock($output);
				return $this->mutexLock($output);
			}

			return FALSE;
		}

		// store lock creation time
		file_put_contents($this->mutexTimeFile, $now->format(static::MUTEX_TIME_FORMAT));

		if ($output) {
			$output->writeln(' done');
		}

		return TRUE;
	}

	protected function mutexUnlock(OutputInterface $output = NULL) {
		if ($output) {
			$output->write('Unlocking mutex ...');
		}
		unlink($this->mutexFile);
		unlink($this->mutexTimeFile);
		if ($output) {
			$output->writeln(' done');
		}
	}

	protected function send(Entity\AbstractMailQueueEntry $entry) {
		if ($this->mailer) {
			$this->mailer->send($entry->message);
		} else {
			$this->messenger->send($entry);
		}
	}

	public function process(OutputInterface $output = NULL) {
		if (!$this->mutexLock($output)) {
			// mutex is already locked
			return FALSE;
		}

		$undeliveredCriteria = [ 'sentAt' => NULL ];
		$orderBy = [ 'createdAt' => 'ASC' ];
		$repo = $this->em->getRepository($this->queueEntryClass);
		$count = min($repo->countBy($undeliveredCriteria), 1000);
		$errors = [];

		if ($count) {
			/** @var Entity\AbstractMailQueueEntry[] $entries */
			$entries = $repo->findBy($undeliveredCriteria, $orderBy, $count);

			foreach ($entries as $counter => $entry) {
				if ($output) {
					$output->write("\r" . 'Sending message ' . (1 + $counter) . ' out of ' . $count);
				}

				try {
					$this->send($entry);
					$entry->sentAt = new \DateTime;
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
						$errors[] = 'Message ' . (1 + $counter) . '/' . $count . '; id=' . $entry->getId() . ': ' . $msg;

						// CLI report
						if ($output) {
							$output->write('; error: ' . $msg);

							if (count($entries) - 1 > $counter) {
								$output->writeln('');
							}
						}
					}
				}

				$this->em->persist($entry);
				$this->em->flush($entry);
			}

			if ($output) {
				$output->writeln('');
			}
		} else if ($output) {
			$output->writeln('No undelivered message found.');
		}

		$this->mutexUnlock($output);

		if ($errors) {
			$errors = implode(PHP_EOL, $errors);
			$this->logger->log($errors, \Tracy\Logger::ERROR);
		}

		$this->onQueueDrained($output);

		return TRUE;
	}


}