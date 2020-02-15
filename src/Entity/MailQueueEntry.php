<?php

namespace ADT\MailQueue\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\Entity
 */
class MailQueueEntry extends AbstractMailQueueEntry {
	use  \ADT\MailQueue\Traits\Identifier;
}
