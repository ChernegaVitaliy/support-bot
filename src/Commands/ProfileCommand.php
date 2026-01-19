<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class ProfileCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/profile';
    }

    public function getDescription(): string
    {
        return 'Профіль користувача';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);

        $user = $this->db->getUserById($userId);

        if (!$user) {
            $this->reply($chatId, $this->t('common.unknown_command', $language));
            return;
        }

        $text = $this->t('commands.profile.title', $language) . "\n\n";
        $text .= $this->t('commands.profile.id', $language, [$user['user_id']]) . "\n";
        
        $firstName = $user['first_name'] ?? 'N/A';
        $text .= $this->t('commands.profile.name', $language, [$firstName]) . "\n";

        if (!empty($user['username'])) {
            $text .= $this->t('commands.profile.username', $language, [$user['username']]) . "\n";
        }

        $text .= $this->t('commands.profile.language', $language, [$user['language']]) . "\n";
        $text .= $this->t('commands.profile.joined', $language, [$user['created_at']]) . "\n";

        if ($this->isAdmin($userId)) {
            $rank = $this->getCurrentAdminRank($userId);
            $text .= "\n" . $this->t('commands.profile.rank', $language, [$rank]) . "\n";
        }

        $this->reply($chatId, $text);
    }
}
