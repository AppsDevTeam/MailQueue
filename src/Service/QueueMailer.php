<?php

namespace ADT\MailQueue\Service;

class QueueMailer implements \Nette\Mail\IMailer {

	/** @var QueueService */
	protected $queueService;

	public function __construct(QueueService $queueService) {
		$this->queueService = $queueService;
	}

	public function send(\Nette\Mail\Message $mail) {
		$this->queueService->enqueue($mail);
	}

}