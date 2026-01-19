<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class BroadcastCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/broadcast';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.broadcast', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): ?string
    {
        return 'owner';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);

        $this->sessionManager->setBroadcastSession($chatId, [
            'step' => 'type',
            'selected_languages' => [],
            'media' => [],
            'message_text' => null,
            'user_lang' => $language,
            'lang_page' => 0,
            'target_type' => 'all'
        ]);

        $messageText = "<b>" . $this->t('admin.broadcast.title', $language) . "</b>\n\n";
        $messageText .= "<b>" . $this->t('admin.broadcast.type_all', $language) . "</b> - " . $this->t('admin.broadcast.desc_all', $language) . "\n";
        $messageText .= "<b>" . $this->t('admin.broadcast.type_lang', $language) . "</b> - " . $this->t('admin.broadcast.desc_lang', $language) . "\n";
        $messageText .= "<b>" . $this->t('admin.broadcast.type_admins', $language) . "</b> - " . $this->t('admin.broadcast.desc_admins', $language) . "\n";
        $messageText .= "<b>" . $this->t('admin.broadcast.type_users', $language) . "</b> - " . $this->t('admin.broadcast.desc_users', $language) . "\n\n";
        $messageText .= "<i>" . $this->t('admin.broadcast.media_hint', $language) . "</i>";

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => $this->t('admin.broadcast.type_all', $language), 'callback_data' => 'broadcast_all'],
                ['text' => $this->t('admin.broadcast.type_lang', $language), 'callback_data' => 'broadcast_language']
            ],
            [
                ['text' => $this->t('admin.broadcast.type_admins', $language), 'callback_data' => 'broadcast_admins'],
                ['text' => $this->t('admin.broadcast.type_users', $language), 'callback_data' => 'broadcast_users']
            ],
            [
                ['text' => $this->t('common.cancel', $language), 'callback_data' => 'broadcast_cancel']
            ]
        ]);

        $this->telegram->sendMessage($chatId, $messageText, 'HTML', false, null, $keyboard);
    }
}
