<?php

namespace ADT\MailQueue\DI;

use ADT\MailQueue\Console;
use ADT\MailQueue\Entity;
use ADT\MailQueue\Service;


class MailQueueExtension extends \Nette\DI\CompilerExtension {

	/** @var string */
	protected $mailerClass;

	/** @var string */
	protected $messengerClass;

	public function loadConfiguration() {
		$config = $this->validateConfig([
			'mailer' => \Nette\Mail\IMailer::class,
			'messenger' => NULL,
			'queueEntityClass' => Entity\MailQueueEntry::class,
			'autowireMailer' => FALSE,
		]);

		if (!empty($config['messenger']) && !empty($config['mailer'])) {
			throw new \Nette\InvalidArgumentException('Cannot specify both mailer and messenger at the same time.');
		}

		if (empty($config['messenger']) && !is_a($config['mailer'], \Nette\Mail\IMailer::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid mailer class.');
		}

		if (!empty($config['messenger']) && !is_a($config['messenger'], Service\IMessenger::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid messenger class.');
		}

		if (!is_a($config['queueEntityClass'], Entity\AbstractMailQueueEntry::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid Queue entity class.');
		}

		// Queue service
		$this->mailerClass = $config['mailer'];
		$this->messengerClass = $config['messenger'];

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
		$serviceClass = $this->mailerClass ?: $this->messengerClass;

		$serviceName = $this->getContainerBuilder()
			->getByType($serviceClass);

		if (!$serviceName) {
			throw new \Nette\InvalidArgumentException('No service of type ' . $serviceClass . ' found.');
		}

		$serviceDefinition = $this->getContainerBuilder()
			->getDefinition($this->prefix('queue'));

		$serviceDefinition->addSetup(
			$this->mailerClass
				? '$service->setMailer($this->getService(?))'
				: '$service->setMessenger($this->getService(?))',
			[ $serviceName ]
		);
	}


}