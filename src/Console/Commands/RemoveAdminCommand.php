<?php

namespace App\Console\Commands;

use App\Console\ServiceContainer;
use App\Services\DatabaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

#[AsCommand(
    name: 'admin:remove',
    description: 'Remove an administrator',
)]
class RemoveAdminCommand extends Command
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->addArgument('identifier', InputArgument::REQUIRED, 'User ID or @Username');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $identifier = $input->getArgument('identifier');
        $db = $this->container->get(DatabaseService::class);
        $admin = $db->getAdminByIdentifier($identifier);

        if (!$admin) {
            $output->writeln('<error>Admin not found.</error>');
            return Command::FAILURE;
        }

        if ($admin['user_id'] === $db->getDefaultOwnerId()) {
            $output->writeln('<error>Cannot remove default owner.</error>');
            return Command::FAILURE;
        }

        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Are you sure you want to remove admin {$admin['user_id']} (@{$admin['username']})? (y/n) ",
            false
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Operation cancelled.');
            return Command::SUCCESS;
        }

        if ($db->removeAdmin($admin['user_id'])) {
            $output->writeln('<info>✅ Admin removed successfully.</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to remove admin.</error>');
            return Command::FAILURE;
        }
    }
}