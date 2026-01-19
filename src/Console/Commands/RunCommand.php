<?php

namespace App\Console\Commands;

use App\Bot;
use App\Console\ServiceContainer;
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
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<info>Starting Support Bot...</info>');
        
        // Bot class instantiates its own services.
        // We could refactor Bot to accept services from our container, 
        // but for now, we just run it as is.
        $bot = new Bot(); 
        $bot->run();

        return Command::SUCCESS;
    }
}