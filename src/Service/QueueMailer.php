<?php

namespace ADT\MailQueue\Service;

// Nette\Mail\Mailer exists since nette/mail 3.1 (IMailer is its alias there)
// and is the only name available in nette/mail 4, so it works for both generations.
class QueueMailer implements \Nette\Mail\Mailer {

	/** @var QueueService */
	protected $queueService;

	public function __construct(QueueService $queueService) {
		$this->queueService = $queueService;
	}

	public function send(\Nette\Mail\Message $mail): void {
		$this->queueService->enqueue($mail);
	}

}