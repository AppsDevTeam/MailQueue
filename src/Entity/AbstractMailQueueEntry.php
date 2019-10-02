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
	use \Kdyby\Doctrine\MagicAccessors\MagicAccessors;

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
	 * @ORM\Column(type="blob", nullable=true)
	 */
	protected $message;

	/**
	 * @return \Nette\Mail\Message|NULL
	 */
	public function getMessage() {
		if ($this->message === NULL) {
			return NULL;
		}

		// $this->message is stream resource
		return unserialize(stream_get_contents($this->message));
	}

	/**
	 * @param \Nette\Mail\Message|NULL $message
	 * @return $this
	 */
	public function setMessage(\Nette\Mail\Message $message = NULL) {
		if ($message === NULL) {
			$this->message = NULL;
		} else {
			$this->message = serialize($message);
		}
		return $this;
	}

	/**
	 * @return mixed|NULL
	 */
	abstract public function getId();
}