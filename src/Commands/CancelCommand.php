<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class CancelCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/cancel';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.cancel', $language);
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);

        $this->sessionManager->clearAllSessions($chatId);

        $this->reply($chatId, $this->t('common.action_cancelled', $language));
    }
}
