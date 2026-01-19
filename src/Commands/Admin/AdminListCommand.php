<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class AdminListCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/adminlist';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.adminlist', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): ?string
    {
        return 'moderator';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $admins = $this->db->getAllAdmins();

        if (empty($admins)) {
            $this->reply($chatId, $this->t('admin.list.empty', $language));
            return;
        }

        $text = "👑 " . $this->t('admin.list.title', $language) . ":\n\n";

        foreach ($admins as $admin) {
            $icon = match($admin['rank']) {
                'owner' => '👑',
                'admin' => '⭐️',
                'moderator' => '🛡',
                default => '❓'
            };

            $rankName = match($admin['rank']) {
                'owner' => 'owner',
                'admin' => 'admin',
                'moderator' => 'moderator',
                default => $admin['rank']
            };

            $firstName = $admin['first_name'] ?? 'без імені';
            $username = $admin['username'] ? "@{$admin['username']}" : 'без username';
            $userId = $admin['user_id'] ?? 'не встановлено';

            $text .= "{$icon} {$rankName} • {$firstName} • {$username} • ID: {$userId}\n";
        }

        $this->reply($chatId, $text);
    }
}
