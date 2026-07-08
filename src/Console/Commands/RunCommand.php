<?php

namespace App\Console\Commands;

use App\Bot;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:run',
    description: 'Runs the Telegram Bot in long-polling mode',
)]
class RunCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting Support Bot...</info>');
        
        $bot = $this->container->get(Bot::class);
        $bot->run();

        return Command::SUCCESS;
    }
}