<?php

namespace App\Console\Commands;

use Psr\Container\ContainerInterface;
use App\Services\DatabaseService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'user:find',
    description: 'Find users by ID, username or name',
)]
class FindUserCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->addArgument('query', InputArgument::REQUIRED, 'Search query (@username, ID, name, or *)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $query = $input->getArgument('query');
        $db = $this->container->get(DatabaseService::class);
        $users = [];

        if ($query === '*') {
            $users = $db->getAllUsers();
        } elseif (is_numeric($query)) {
            $user = $db->getUserById($query);
            if ($user) $users[] = $user;
        } elseif (str_starts_with($query, '@')) {
            $username = substr($query, 1);
            $user = $db->findUserByUsername($username);
            if ($user) $users[] = $user;
        } else {
             $stmt = $db->query("SELECT * FROM users WHERE first_name LIKE ?", true, ["%{$query}%"]);
             $users = $stmt ?: [];
        }

        if (empty($users)) {
            $output->writeln('<comment>No users found.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['User ID', 'Username', 'Name', 'Language', 'Created At']);

        foreach ($users as $user) {
            $table->addRow([
                $user['user_id'],
                $user['username'] ? '@' . $user['username'] : '-',
                $user['first_name'] ?: '-',
                $user['language'] ?? '?',
                $user['created_at'] ?? '?'
            ]);
        }

        $table->render();
        $output->writeln('<info>Found: ' . count($users) . '</info>');

        return Command::SUCCESS;
    }
}