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
    name: 'admin:rank',
    description: 'Set administrator rank',
)]
class SetRankCommand extends Command
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

            $this->notifyUser($admin['user_id'], $rank, $output);

            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Failed to update rank.</error>');
            return Command::FAILURE;
        }
    }

    private function notifyUser(int|string $userId, string $rank, OutputInterface $output): void
    {
        try {
            $telegram = $this->container->get(TelegramService::class);
            $translator = $this->container->get(Translator::class);
            $message = $translator->translate('admin.rank.notification', 'uk', [$rank]);
            $telegram->sendMessage($userId, $message);
            $output->writeln('<info>📨 Notification sent to user.</info>');
        } catch (\Exception $e) {
            $output->writeln('<comment>⚠️ Could not notify user: ' . $e->getMessage() . '</comment>');
        }
    }
}