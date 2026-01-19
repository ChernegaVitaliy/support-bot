<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class AboutCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/about';
    }

    public function getDescription(): string
    {
        return 'Про бота';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $this->reply($chatId, $this->t('commands.about.text', $language));
    }
}
