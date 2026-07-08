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
    name: 'admin:list',
    description: 'List all administrators',
    aliases: ['admins']
)]
class AdminListCommand extends Command
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
        $admins = $db->getAllAdmins();
        $defaultOwnerId = $db->getDefaultOwnerId();

        $output->writeln('<info>👨‍💼 ADMINISTRATORS LIST</info>');

        if (empty($admins)) {
            $output->writeln('<comment>No administrators found.</comment>');
            return Command::SUCCESS;
        }

        $table = new Table($output);
        $table->setHeaders(['Rank', 'User ID', 'Username', 'Name', 'Added At']);

        foreach ($admins as $admin) {
            $rankEmoji = match($admin['rank']) {
                'owner' => '👑',
                'admin' => '⭐',
                'moderator' => '🛡️',
                default => '🔹'
            };

            $isDefault = ($admin['user_id'] === $defaultOwnerId) ? ' (Default)' : '';

            $table->addRow([
                $rankEmoji . ' ' . ucfirst($admin['rank']),
                $admin['user_id'] . $isDefault,
                $admin['username'] ? '@' . $admin['username'] : '-',
                $admin['first_name'] ?: '-',
                $admin['added_at']
            ]);
        }

        $table->render();
        $output->writeln('<info>Total: ' . count($admins) . '</info>');

        return Command::SUCCESS;
    }
}