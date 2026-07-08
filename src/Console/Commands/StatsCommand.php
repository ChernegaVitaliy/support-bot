<?php

namespace App\Console\Commands;

use Psr\Container\ContainerInterface;
use App\Services\DatabaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'stats',
    description: 'Show bot statistics',
)]
class StatsCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $db = $this->container->get(DatabaseService::class);
        
        $stats = $db->getStats();
        $usersCount = $db->getUsersCount();
        $activeUsers = $db->getActiveUsersToday();

        $output->writeln('<info>📊 BOT STATISTICS</info>');
        
        $table = new Table($output);
        $table
            ->setHeaders(['Metric', 'Value'])
            ->setRows([
                ['Total Users', $usersCount],
                ['Active Users (Today)', $activeUsers],
                ['Total Admins', $stats['total_admins']],
                ['Total Reports', $stats['total_reports']],
                ['Pending Reports', $stats['pending_reports']],
                ['Accepted Reports', $stats['accepted_reports']],
                ['Rejected Reports', $stats['rejected_reports']],
            ]);
        $table->render();

        return Command::SUCCESS;
    }
}