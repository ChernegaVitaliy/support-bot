<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class RemoveAdminCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/removeadmin';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.remove_admin', $language);
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
        $text = $message->getText() ?? '';
        $parts = explode(' ', $text);
        $currentUserId = $this->getUserId($message);
        $currentRank = $this->getCurrentAdminRank($currentUserId);

        if (count($parts) < 2) {
            $this->reply($chatId, $this->t('admin.remove.usage', $language));
            return;
        }

        $identifier = $parts[1];
        $admin = $this->db->getAdminByIdentifier($identifier);

        if (!$admin) {
            $this->reply($chatId, $this->t('errors.user_not_found', $language));
            return;
        }

        if ($admin['user_id'] === $this->db->getDefaultOwnerId()) {
            $this->reply($chatId, $this->t('admin.remove.cannot_remove_owner', $language));
            return;
        }

        if ($admin['user_id'] == $currentUserId) {
            $this->reply($chatId, $this->t('admin.remove.cannot_remove_self', $language));
            return;
        }

        $targetRankLevel = $this->getRankLevel($admin['rank']);
        $currentRankLevel = $this->getRankLevel($currentRank);
        $isDefaultOwner = ($currentUserId === $this->db->getDefaultOwnerId());

        if (!$isDefaultOwner && $currentRankLevel <= $targetRankLevel) {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        $result = $this->db->removeAdmin($admin['user_id'], $currentUserId);

        if ($result) {
            $this->reply($chatId, $this->t('admin.remove.success', $language));
             // Notify removed admin
            try {
                $targetLanguage = $this->db->getUserLanguage((string)$admin['user_id']) ?: 'uk';
                $this->telegram->sendMessage($admin['user_id'], $this->t('admin.remove.notification', $targetLanguage));
            } catch (\Exception $e) {
                // Ignore
            }
        } else {
            $this->reply($chatId, $this->t('admin.remove.error', $language));
        }
    }

    private function getRankLevel(string $rank): int
    {
        return match($rank) {
            'owner' => 3,
            'admin' => 2,
            'moderator' => 1,
            default => 0
        };
    }
}
