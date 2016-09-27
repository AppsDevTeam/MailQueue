<?php

namespace ADT\MailQueue\Console;

use ADT\MailQueue\Service;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class ProcessCommand extends Command {
	/**
	 * {@inheritdoc}
	 */
	protected function configure() {
		$this
			->setName('mail-queue:process')
			->setDescription('Process messages in mail queue.')
			->setHelp(<<<EOT
Process messages in mail queue.
EOT
			);
	}

	/**
	 * {@inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var Service\QueueService $queue */
		$queue = $this->getHelper('container')->getByType(Service\QueueService::class);
		$queue->process($output);
	}
}
