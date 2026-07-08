<?php

namespace App\Console\Commands;

use Psr\Container\ContainerInterface;
use App\Services\TelegramService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetupBotCommand extends Command
{
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setName('bot:setup')
            ->setDescription('Setup bot name, description, short description and register commands')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Bot name')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Bot description')
            ->addOption('short-description', null, InputOption::VALUE_OPTIONAL, 'Bot short description')
            ->addOption('register-commands', null, InputOption::VALUE_NONE, 'Register bot commands')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language code', 'uk');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $telegram = $this->container->get('telegram');
        $translator = $this->container->get('translator');
        $logger = $this->container->get('logger');

        $language = $input->getOption('language');
        $name = $input->getOption('name');
        $description = $input->getOption('description');
        $shortDescription = $input->getOption('short-description');
        $registerCommands = $input->getOption('register-commands');

        if (!$name) {
            $name = $translator->translate('bot.name', $language);
        }
        if (!$description) {
            $description = $translator->translate('bot.description', $language);
        }
        if (!$shortDescription) {
            $shortDescription = $translator->translate('bot.about', $language);
        }

        $output->writeln("Setting up bot profile for language: $language");
        $output->writeln("Name: $name");
        $output->writeln("Description: $description");
        $output->writeln("Short Description: $shortDescription");
        $output->writeln('');

        $result = true;

        if ($name) {
            $result = $telegram->setMyName($name, $language) && $result;
        }

        if ($description) {
            $result = $telegram->setMyDescription($description, $language) && $result;
        }

        if ($shortDescription) {
            $result = $telegram->setMyShortDescription($shortDescription, $language) && $result;
        }

        if ($registerCommands) {
            $output->writeln('Registering bot commands...');
            $commands = [];

            $commandClasses = [
                'App\Commands\AboutCommand',
                'App\Commands\StartCommand',
                'App\Commands\HelpCommand',
                'App\Commands\ReportCommand',
                'App\Commands\StatsCommand',
                'App\Commands\CancelCommand',
                'App\Commands\MyRankCommand',
                'App\Commands\ProfileCommand',
                'App\Commands\MyIdCommand',
                'App\Commands\Admin\ReportsCommand',
                'App\Commands\Admin\BroadcastCommand',
                'App\Commands\Admin\RejectReportCommand',
                'App\Commands\Admin\AcceptReportCommand',
                'App\Commands\Admin\DebugCommand',
                'App\Commands\Admin\SetRankCommand',
                'App\Commands\Admin\RemoveAdminCommand',
                'App\Commands\Admin\AddAdminCommand',
            ];

            foreach ($commandClasses as $className) {
                if (class_exists($className)) {
                    $command = new $className($this->container);
                    $commands[] = [
                        'command' => ltrim($command->getName(), '/'),
                        'description' => $command->getDescription($language)
                    ];
                }
            }

            if ($telegram->setMyCommands($commands, null, $language)) {
                $output->writeln('<info>Bot commands registered successfully</info>');
            } else {
                $output->writeln('<error>Failed to register bot commands</error>');
                $result = false;
            }
        }

        if ($result) {
            $output->writeln('<info>Bot setup completed successfully</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Bot setup failed. Check logs for details.</error>');
            return Command::FAILURE;
        }
    }
}
