<?php

namespace ADT\MailQueue\DI;

use ADT\MailQueue\Console;
use ADT\MailQueue\Entity;
use ADT\MailQueue\Service;


class MailQueueExtension extends \Nette\DI\CompilerExtension {

	/** @var string */
	protected $outboundMailerType;

	public function loadConfiguration() {
		$config = $this->validateConfig([
			'mailer' => \Nette\Mail\IMailer::class,
			'queueEntityClass' => Entity\MailQueueEntry::class,
			'autowireMailer' => FALSE,
		]);

		if (!is_a($config['mailer'], \Nette\Mail\IMailer::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid mailer class.');
		}

		if (!is_a($config['queueEntityClass'], Entity\AbstractMailQueueEntry::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid Queue entity class.');
		}

		// Queue service
		$this->outboundMailerType = $config['mailer'];
		$this->getContainerBuilder()
			->addDefinition($this->prefix('queue'))
			->setClass(Service\QueueService::class)
			->setArguments([
				$this->getContainerBuilder()->parameters['tempDir'],
				$config['queueEntityClass']
			]);

		// Mailer service
		$this->getContainerBuilder()
			->addDefinition($this->prefix('mailer'))
			->setClass(Service\QueueMailer::class)
			->setAutowired($config['autowireMailer']);

		// Process command
		$this->getContainerBuilder()
			->addDefinition($this->prefix('command.process'))
			->setClass(Console\ProcessCommand::class)
			->addTag('kdyby.console.command');
	}

	public function beforeCompile() {
		$serviceName = $this->getContainerBuilder()
			->getByType($this->outboundMailerType);

		if (!$serviceName) {
			throw new \Nette\InvalidArgumentException('No service of type ' . $this->outboundMailerType . ' found.');
		}

		$this->getContainerBuilder()
			->getDefinition($this->prefix('queue'))
			->addSetup('$service->setOutboundMailer($this->getService(?))', [ $serviceName ]);
	}


}