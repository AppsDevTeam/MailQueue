<?php

namespace ADT\MailQueue\Service;

use ADT\MailQueue\Entity;

interface IMessenger {
	/**
	 * @param Entity\AbstractMailQueueEntry $entry
	 * @return void
	 * @throws \Exception
	 */
	function send(Entity\AbstractMailQueueEntry $entry);
}