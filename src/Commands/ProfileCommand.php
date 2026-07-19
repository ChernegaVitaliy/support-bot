<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class ProfileCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/profile';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.profile', $language);
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);

        $text = $message->getText() ?? '';
        $parts = explode(' ', $text);
        $identifier = $parts[1] ?? null;

        $viewingOther = false;

        if ($identifier !== null && $this->isAdmin($userId)) {
            $target = $this->db->getUserByIdentifier($identifier);

            if (!$target) {
                $this->reply($chatId, $this->t('errors.user_not_found', $language));
                return;
            }

            $viewingOther = true;
        } else {
            if ($identifier !== null && !$this->isAdmin($userId)) {
                $this->reply($chatId, $this->t('commands.profile.usage', $language));
                return;
            }

            $target = $this->db->getUserById($userId);
        }

        if (!$target) {
            $this->reply($chatId, $this->t('common.unknown_command', $language));
            return;
        }

        $user = $target;

        $titleKey = $viewingOther ? 'commands.profile.other_title' : 'commands.profile.title';
        $text = $this->t($titleKey, $language) . "\n\n";
        $text .= $this->t('commands.profile.id', $language, [$user['user_id']]) . "\n";
        
        $firstName = $user['first_name'] ?? 'N/A';
        $text .= $this->t('commands.profile.name', $language, [$firstName]) . "\n";

        if (!empty($user['username'])) {
            $text .= $this->t('commands.profile.username', $language, [$user['username']]) . "\n";
        }

        $text .= $this->t('commands.profile.language', $language, [$user['language']]) . "\n";
        $text .= $this->t('commands.profile.joined', $language, [$user['created_at']]) . "\n";

        if ($viewingOther && $this->isAdmin((string)$user['user_id'])) {
            $rank = $this->getCurrentAdminRank((string)$user['user_id']);
            $text .= "\n" . $this->t('commands.profile.rank', $language, [$rank]) . "\n";
        } elseif (!$viewingOther && $this->isAdmin($userId)) {
            $rank = $this->getCurrentAdminRank($userId);
            $text .= "\n" . $this->t('commands.profile.rank', $language, [$rank]) . "\n";
        }

        $this->reply($chatId, $text);
    }
}
