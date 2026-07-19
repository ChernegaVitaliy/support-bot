<?php

namespace App\Console\Commands;

use Psr\Container\ContainerInterface;
use App\Services\DatabaseService;
use App\Services\TelegramService;
use App\Services\Translator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'admin:add',
    description: 'Add a new administrator',
)]
class AddAdminCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('identifier', InputArgument::REQUIRED, 'User ID or @Username')
            ->addArgument('rank', InputArgument::OPTIONAL, 'Rank (owner, admin, moderator)', 'moderator');
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

        $userId = null;
        $username = null;
        $firstName = 'Admin';

        if (is_numeric($identifier)) {
            $userId = $identifier;
            $user = $db->getUserById($userId);
            if ($user) {
                $username = $user['username'];
                $firstName = $user['first_name'];
            }
        } elseif (str_starts_with($identifier, '@')) {
            $username = substr($identifier, 1);
            $user = $db->findUserByUsername($username);
            if ($user) {
                $userId = $user['user_id'];
                $firstName = $user['first_name'];
            } else {
                $output->writeln("<error>User $identifier not found in database. They must interact with the bot first.</error>");
                return Command::FAILURE;
            }
        } else {
            $output->writeln('<error>Invalid identifier format.</error>');
            return Command::FAILURE;
        }

        if ($db->isAdmin($userId)) {
            $output->writeln('<comment>User is already an admin.</comment>');
            return Command::FAILURE;
        }

        if ($db->addAdmin($userId, $username ?? '', $firstName, $rank)) {
            $output->writeln("<info>✅ Admin added successfully!</info>");
            $output->writeln("ID: $userId, Rank: $rank");

            $this->notifyUser($userId, $rank, $output);

            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to add admin.</error>');
            return Command::FAILURE;
        }
    }

    private function notifyUser(string $userId, string $rank, OutputInterface $output): void
    {
        try {
            $telegram = $this->container->get(TelegramService::class);
            $translator = $this->container->get(Translator::class);
            $message = $translator->translate('admin.add.notification', 'uk', [$rank]);
            $telegram->sendMessage($userId, $message);
            $output->writeln('<info>📨 Notification sent to user.</info>');
        } catch (\Exception $e) {
            $output->writeln('<comment>⚠️ Could not notify user: ' . $e->getMessage() . '</comment>');
        }
    }
}