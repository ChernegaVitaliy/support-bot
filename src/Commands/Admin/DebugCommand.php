<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class DebugCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/debug';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.debug', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): string
    {
        return 'owner';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        // Note: Actual debug switching logic is not implemented in Config yet.
        $this->reply($chatId, $this->t('admin.debug.title', $language));
    }
}
