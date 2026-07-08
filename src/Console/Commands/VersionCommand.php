<?php

namespace App\Console\Commands;

use App\Config\Config;
use Psr\Container\ContainerInterface;
use App\Services\TelegramService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:version',
    description: 'Show bot version info',
)]
class VersionCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = $this->container->get(Config::class);
        $telegram = $this->container->get(TelegramService::class);

        $output->writeln('<info>🤖 SUPPORT BOT</info>');
        $output->writeln('Version: 2.0.0');
        $output->writeln('Bot Username: @' . $telegram->getBotUsername());
        $output->writeln('Default Owner ID: ' . $config->getDefaultOwnerId());
        
        return Command::SUCCESS;
    }
}