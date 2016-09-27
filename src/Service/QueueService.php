<?php

namespace ADT\MailQueue\Service;

use ADT\MailQueue\Entity;
use Symfony\Component\Console\Output\OutputInterface;


class QueueService {

	/** @var string */
	protected $queueEntryClass;

	/** @var \Kdyby\Doctrine\EntityManager */
	protected $em;

	/** @var string */
	protected $mutexFile;

	/** @var \Nette\Mail\IMailer */
	protected $outboundMailer;

	public function __construct($tempDir, $queueEntryClass, \Kdyby\Doctrine\EntityManager $em) {
		$this->mutexFile = 'nette.safe://' . $tempDir . '/adt-mail-queue.lock';
		$this->queueEntryClass = $queueEntryClass;
		$this->em = $em;
	}

	public function setOutboundMailer(\Nette\Mail\IMailer $outboundMailer) {
		$this->outboundMailer = $outboundMailer;
	}

	/**
	 * @param \Nette\Mail\Message $message
	 * @param array $customFields
	 * @return Entity\AbstractMailQueueEntry
	 */
	protected function createQueueEntry(\Nette\Mail\Message $message, array $customFields = []) {
		/** @var Entity\AbstractMailQueueEntry $entry */
		$entry = new $this->queueEntryClass;
		$entry->createdAt = new \DateTime;
		$entry->from = array_keys($message->getFrom())[0];
		$entry->subject = $message->getSubject();
		$entry->message = $message;

		foreach ($customFields as $field => $value) {
			$entry->$field = $value;
		}

		return $entry;
	}

	/**
	 * @param \Nette\Mail\Message $message
	 * @param array $customFields
	 * @return Entity\AbstractMailQueueEntry
	 */
	public function enqueue(\Nette\Mail\Message $message, array $customFields = []) {
		$entry = $this->createQueueEntry($message, $customFields);
		$this->em->persist($entry);
		$this->em->flush($entry);
		return $entry;
	}

	protected function mutexLock(OutputInterface $output = NULL) {
		if ($output) {
			$output->write('Locking mutex ...');
		}

		// this is NOT atomic mutex!
		// it is all we have right now and it should be enough for our purpose but in case it is not,
		// just make sure the "read, increment, write" operation is atomic and all should work just fine
		file_put_contents($this->mutexFile, (@file_get_contents($this->mutexFile) ?: 0) + 1); // @ - file may not exist yet

		if (file_get_contents($this->mutexFile) !== '1') {
			if ($output) {
				$output->writeln(' already locked');
			}

			return FALSE;
		}

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
		if ($output) {
			$output->writeln(' done');
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

		if ($count) {
			/** @var Entity\AbstractMailQueueEntry[] $entries */
			$entries = $repo->findBy($undeliveredCriteria, $orderBy, $count);

			foreach ($entries as $counter => $entry) {
				if ($output) {
					$output->write("\r" . 'Sending message ' . (1 + $counter) . ' out of ' . $count);
				}

				try {
					$this->outboundMailer->send($entry->message);
				} catch (\Exception $e) {
					// log?
					continue;
				}

				$entry->sentAt = new \DateTime;
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
		return TRUE;
	}


}