<?php

namespace ADT\MailQueue\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * @ORM\MappedSuperclass
 * @property \DateTime $createdAt
 * @property \DateTime|NULL $sentAt
 * @property string $from
 * @property string $subject
 * @property \Nette\Mail\Message|NULL $message
 */
abstract class AbstractMailQueueEntry {
	use \Kdyby\Doctrine\Entities\MagicAccessors;

	/**
	 * @ORM\Column(type="datetime")
	 */
	protected $createdAt;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	protected $sentAt;

	/**
	 * @ORM\Column(type="text", name="`from`")
	 */
	protected $from;

	/**
	 * @ORM\Column(type="text")
	 */
	protected $subject;

	/**
	 * @ORM\Column(type="object", nullable=true)
	 */
	protected $message;
}