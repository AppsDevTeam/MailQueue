<?php

namespace ADT\MailQueue\Entity;

use Doctrine\ORM\Mapping as ORM;


/**
 * Mapping is duplicated as annotations and attributes on purpose: annotations
 * are read by the annotation driver (ORM 2), attributes by the attribute
 * driver (ORM 2.9+ and the only option in ORM 3). Each driver ignores the
 * other format, so the class works with both ORM generations.
 *
 * @ORM\MappedSuperclass
 * @property \DateTime $createdAt
 * @property \DateTime|NULL $sentAt
 * @property string $from
 * @property string $subject
 * @property \Nette\Mail\Message|NULL $message
 */
#[ORM\MappedSuperclass]
abstract class AbstractMailQueueEntry {

	/**
	 * @ORM\Column(type="datetime")
	 */
	#[ORM\Column(type: 'datetime')]
	protected $createdAt;

	/**
	 * @ORM\Column(type="datetime", nullable=true)
	 */
	#[ORM\Column(type: 'datetime', nullable: true)]
	protected $sentAt;

	/**
	 * @ORM\Column(type="string", name="`from`")
	 */
	#[ORM\Column(type: 'string', name: '`from`')]
	protected $from;

	/**
	 * @ORM\Column(type="text")
	 */
	#[ORM\Column(type: 'text')]
	protected $subject;

	/**
	 * @ORM\Column(type="blob", nullable=true)
	 */
	#[ORM\Column(type: 'blob', nullable: true)]
	protected $message;

	/**
	 * @return \Nette\Mail\Message|NULL
	 */
	public function getMessage() {
		if ($this->message === NULL) {
			return NULL;
		}

		// $this->message is stream resource
		rewind($this->message);
		return unserialize(stream_get_contents($this->message));
	}

	/**
	 * @param \Nette\Mail\Message|NULL $message
	 * @return $this
	 */
	public function setMessage(?\Nette\Mail\Message $message = NULL): self {
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


	/**
	 * @return \DateTime
	 */
	public function getCreatedAt(): \DateTime {
		return $this->createdAt;
	}

	/**
	 * @param \DateTime $createdAt
	 * @return AbstractMailQueueEntry
	 */
	public function setCreatedAt(\DateTime $createdAt): self {
		$this->createdAt = $createdAt;
		return $this;
	}

	/**
	 * @return \DateTime|NULL
	 */
	public function getSentAt(): ?\DateTime {
		return $this->sentAt;
	}

	/**
	 * @param \DateTime|NULL $sentAt
	 * @return AbstractMailQueueEntry
	 */
	public function setSentAt(?\DateTime $sentAt): self {
		$this->sentAt = $sentAt;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getFrom(): string {
		return $this->from;
	}

	/**
	 * @param string $from
	 * @return AbstractMailQueueEntry
	 */
	public function setFrom(string $from): self {
		$this->from = $from;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getSubject(): string {
		return $this->subject;
	}

	/**
	 * @param string $subject
	 * @return AbstractMailQueueEntry
	 */
	public function setSubject(string $subject): self {
		$this->subject = $subject;
		return $this;
	}

}
