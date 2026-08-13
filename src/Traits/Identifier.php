<?php declare(strict_types=1);

namespace ADT\MailQueue\Traits;

use Doctrine\ORM\Mapping as ORM;


trait Identifier
{
	/** @var integer|null */
	#[ORM\Id]
	#[ORM\Column(type: 'integer')]
	#[ORM\GeneratedValue]
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
