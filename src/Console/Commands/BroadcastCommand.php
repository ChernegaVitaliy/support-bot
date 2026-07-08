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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'broadcast',
    description: 'Send a broadcast message to users',
)]
class BroadcastCommand extends Command
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
            ->addArgument('message', InputArgument::REQUIRED, 'The message to send')
            ->addOption('lang', null, InputOption::VALUE_OPTIONAL, 'Filter by language (e.g. uk)')
            ->addOption('admins', null, InputOption::VALUE_NONE, 'Send only to admins')
            ->addOption('users', null, InputOption::VALUE_NONE, 'Send only to regular users')
            ->addOption('test', null, InputOption::VALUE_NONE, 'Test mode (do not send)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $message = $input->getArgument('message');
        $lang = $input->getOption('lang');
        $adminsOnly = $input->getOption('admins');
        $usersOnly = $input->getOption('users');
        $isTest = $input->getOption('test');

        $db = $this->container->get(DatabaseService::class);
        
        // Only get TelegramService if we are actually sending (not testing, or even testing usually needs it but here test mode just lists users)
        // Wait, execute logic says: if test, list users.
        // So we only need TelegramService if NOT test.
        
        // Fetch users manually to apply all filters
        $allUsers = $db->getAllUsers();
        $admins = $db->getAllAdmins();
        $adminIds = array_column($admins, 'user_id');

        $targets = [];

        foreach ($allUsers as $user) {
            $userId = $user['user_id'];
            $isAdmin = in_array($userId, $adminIds);

            if ($adminsOnly && !$isAdmin) continue;
            if ($usersOnly && $isAdmin) continue;
            if ($lang && ($user['language'] ?? 'uk') !== $lang) continue;

            $targets[] = $user;
        }

        $count = count($targets);
        $output->writeln("<info>Found $count recipients.</info>");

        if ($count === 0) {
            return Command::SUCCESS;
        }

        if ($isTest) {
            $output->writeln('<comment>TEST MODE. Showing first 5 recipients:</comment>');
            foreach (array_slice($targets, 0, 5) as $user) {
                $output->writeln("- {$user['first_name']} (@{$user['username']}) [ID: {$user['user_id']}]");
            }
            return Command::SUCCESS;
        }

        // If not test, we need TelegramService
        $telegram = $this->container->get(TelegramService::class);

        $output->writeln("Sending message: \"$message\"");
        
        $success = 0;
        $failed = 0;

        foreach ($targets as $user) {
            try {
                $telegram->sendMessage($user['user_id'], $message, 'HTML');
                $success++;
                if ($output->isVerbose()) {
                    $output->writeln("✅ Sent to {$user['user_id']}");
                }
            } catch (\Exception $e) {
                $failed++;
                if ($output->isVerbose()) {
                    $output->writeln("❌ Failed to {$user['user_id']}: " . $e->getMessage());
                }
            }
            usleep(100000); // 0.1s delay
        }

        $output->writeln("<info>Broadcast complete. Success: $success, Failed: $failed</info>");

        return Command::SUCCESS;
    }
}