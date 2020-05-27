<?php declare(strict_types=1);

namespace ADT\MailQueue\Traits;


trait Identifier
{
	/**
	 * @ORM\Id
	 * @ORM\Column(type="integer")
	 * @ORM\GeneratedValue
	 * @var integer|null
	 */
	protected $id;

	/**
	 * @return integer
	 */
	final public function getId()
	{
		return $this->id;
	}

	public function __clone()
	{
		$this->id = NULL;
	}
}
