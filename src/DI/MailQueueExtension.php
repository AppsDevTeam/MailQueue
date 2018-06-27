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
			'sendErrorHandler' => NULL,
			'onQueueDrained' => NULL,
			'lockTimeout' => 600,
			'limit' => 1000,
			'tempDir' => $this->getContainerBuilder()->parameters['tempDir'],
		]);

		if (!empty($config['messenger']) && !empty($config['mailer'])) {
			throw new \Nette\InvalidArgumentException('Cannot specify both mailer and messenger at the same time.');
		}

		if (empty($config['messenger']) && empty($config['mailer'])) {
			throw new \Nette\InvalidArgumentException('Please specify mailer or messenger service class (e.g. @ServiceClass).');
		}

		if (!is_a($config['queueEntityClass'], Entity\AbstractMailQueueEntry::class, TRUE)) {
			throw new \Nette\InvalidArgumentException('Invalid Queue entity class.');
		}

		// Queue service
		$service = $config['mailer'] ?: $config['messenger'];

		$queueService = $this->getContainerBuilder()
			->addDefinition($this->prefix('queue'))
			->setClass(Service\QueueService::class)
			->setArguments([
				$config,
			])
			->addSetup(
				$config['mailer']
					? '$service->setMailer(?)'
					: '$service->setMessenger(?)',
				[ $service, ]
			);

		if (!empty($config['sendErrorHandler'])) {
			$queueService->addSetup(
				'$service->setSendErrorHandler(?)',
				[ $config['sendErrorHandler'], ]
			);
		}

		if (!empty($config['onQueueDrained'])) {
			$queueService->addSetup(
				'$service->onQueueDrained[] = ?',
				[ $config['onQueueDrained'], ]
			);
		}

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
