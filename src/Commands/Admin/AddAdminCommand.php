<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class AddAdminCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/addadmin';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.add_admin', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): ?string
    {
        return 'admin';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);
        $currentRank = $this->getCurrentAdminRank($userId);

        $text = $message->getText() ?? '';
        $parts = explode(' ', $text);

        if (count($parts) < 2) {
            $this->reply($chatId, $this->t('admin.add.usage', $language));
            return;
        }

        $identifier = $parts[1];
        $rank = $parts[2] ?? 'moderator';

        if (!in_array($rank, ['owner', 'admin', 'moderator'])) {
            $this->reply($chatId, $this->t('errors.invalid_format', $language));
            return;
        }

        if ($rank === 'owner' && $userId !== $this->db->getDefaultOwnerId()) {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        if ($currentRank === 'admin' && $rank === 'owner') {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        $admin = $this->db->getAdminByIdentifier($identifier);

        if ($admin) {
            $this->reply($chatId, $this->t('admin.add.exists', $language));
            return;
        }

        // Try to find by username first, then check if it's an ID
        $user = null;
        if (str_starts_with($identifier, '@')) {
             $user = $this->db->findUserByUsername(str_replace('@', '', $identifier));
        } elseif (is_numeric($identifier)) {
             $user = $this->db->getUserById($identifier);
        } else {
             $user = $this->db->findUserByUsername($identifier);
        }

        if (!$user) {
            $this->reply($chatId, $this->t('errors.user_not_found', $language));
            return;
        }

        if ($currentRank === 'admin' && $rank !== 'moderator') {
            $this->reply($chatId, $this->t('errors.no_permission', $language)); // Simplification
            return;
        }

        $result = $this->db->addAdmin($user['user_id'], $user['username'] ?? '', $user['first_name'] ?? '', $rank);

        if ($result) {
            $this->reply($chatId, $this->t('admin.add.success', $language, [$rank]));
            
            // Notify new admin
            try {
                $targetLanguage = $this->db->getUserLanguage((string)$user['user_id']) ?: 'uk';
                $this->telegram->sendMessage($user['user_id'], $this->t('admin.add.notification', $targetLanguage, [$rank]));
            } catch (\Exception $e) {
                // Ignore if cannot send message
            }
        } else {
            $this->reply($chatId, $this->t('admin.add.error', $language));
        }
    }
}
