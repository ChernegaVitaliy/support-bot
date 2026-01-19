<?php

namespace App\Console\Commands;

use App\Console\ServiceContainer;
use App\Services\TelegramService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SetupBotCommand extends Command
{
    private ServiceContainer $container;

    public function __construct(ServiceContainer $container)
    {
        parent::__construct();
        $this->container = $container;
    }

    protected function configure(): void
    {
        $this->setName('bot:setup')
            ->setDescription('Setup bot name, description and short description')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'Bot name')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'Bot description')
            ->addOption('short-description', null, InputOption::VALUE_OPTIONAL, 'Bot short description')
            ->addOption('language', 'l', InputOption::VALUE_OPTIONAL, 'Language code', 'uk');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $telegram = $this->container->get('telegram');
        $translator = $this->container->get('translator');

        $language = $input->getOption('language');
        $name = $input->getOption('name');
        $description = $input->getOption('description');
        $shortDescription = $input->getOption('short-description');

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

        if ($result) {
            $output->writeln('<info>Bot profile setup completed successfully</info>');
            return Command::SUCCESS;
        } else {
            $output->writeln('<error>Bot profile setup failed. Check logs for details.</error>');
            return Command::FAILURE;
        }
    }
}
