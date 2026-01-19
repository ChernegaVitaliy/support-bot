<?php

namespace App\Console\Commands;

use App\Console\ServiceContainer;
use App\Services\DatabaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'admin:rank',
    description: 'Set administrator rank',
)]
class SetRankCommand extends Command
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifier', InputArgument::REQUIRED, 'User ID or @Username')
            ->addArgument('rank', InputArgument::REQUIRED, 'New Rank (owner, admin, moderator)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getArgument('identifier');
        $rank = strtolower($input->getArgument('rank'));

        if (!in_array($rank, ['owner', 'admin', 'moderator'])) {
            $output->writeln('<error>Invalid rank. Allowed: owner, admin, moderator</error>');
            return Command::FAILURE;
        }

        $db = $this->container->get(DatabaseService::class);
        $admin = $db->getAdminByIdentifier($identifier);

        if (!$admin) {
            $output->writeln('<error>Admin not found.</error>');
            return Command::FAILURE;
        }

        if ($admin['user_id'] === $db->getDefaultOwnerId()) {
            $output->writeln('<error>Cannot change rank of default owner.</error>');
            return Command::FAILURE;
        }

        if ($db->setAdminRank($admin['user_id'], $rank)) {
            $output->writeln("<info>✅ Rank updated to '$rank' for user {$admin['user_id']}.</info>");
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to update rank.</error>');
            return Command::FAILURE;
        }
    }
}