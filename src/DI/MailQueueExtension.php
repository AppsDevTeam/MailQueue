<?php

namespace ADT\MailQueue\DI;

use ADT\MailQueue\Console;
use ADT\MailQueue\Entity;
use ADT\MailQueue\Service;


class MailQueueExtension extends \Nette\DI\CompilerExtension {

	public function loadConfiguration() {
		$config = $this->validateConfig([
			'mailer' => NULL,
			'messenger' => NULL,
			'queueEntityClass' => Entity\MailQueueEntry::class,
			'autowireMailer' => FALSE,
		]);

		if (!empty($config['messenger']) && !empty($config['mailer'])) {
			throw new \Nette\InvalidArgumentException('Cannot specify both mailer and messenger at the same time.');
		}

		if (empty($config['messenger']) && empty($config['mailer'])) {
			throw new \Nette\InvalidArgumentException('Please specify mailer or messenger service.');
		}

		if (!is_a($config['queueEntityClass'], Entity\AbstractMailQueueEntry::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid Queue entity class.');
		}

		// Queue service
		$serviceName = $config['mailer'] ?: $config['messenger'];
		if (strrpos($serviceName, '@') !== 0) {
			throw new \Nette\InvalidArgumentException('Service name has to be prefixed with exactly one \'@\'.');
		}

		$this->getContainerBuilder()
			->addDefinition($this->prefix('queue'))
			->setClass(Service\QueueService::class)
			->setArguments([
				$this->getContainerBuilder()->parameters['tempDir'],
				$config['queueEntityClass']
			])
			->addSetup(
				$config['mailer']
					? '$service->setMailer($this->getService(?))'
					: '$service->setMessenger($this->getService(?))',
				[ ltrim($serviceName, '@') ]
			);

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

}