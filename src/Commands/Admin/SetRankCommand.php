<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class SetRankCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/setrank';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.set_rank', $language);
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
        $text = $message->getText() ?? '';
        $parts = explode(' ', $text);
        $currentUserId = $this->getUserId($message);
        $currentRank = $this->getCurrentAdminRank($currentUserId);

        if (count($parts) < 3) {
            $this->reply($chatId, $this->t('admin.rank.usage', $language));
            return;
        }

        $identifier = $parts[1];
        $newRank = $parts[2];

        if (!in_array($newRank, ['owner', 'admin', 'moderator'])) {
            $this->reply($chatId, $this->t('errors.invalid_format', $language));
            return;
        }

        $admin = $this->db->getAdminByIdentifier($identifier);

        if (!$admin) {
            $this->reply($chatId, $this->t('errors.user_not_found', $language));
            return;
        }

        if ($admin['user_id'] === $this->db->getDefaultOwnerId()) {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        $currentRankLevel = $this->getRankLevel($currentRank);
        $newRankLevel = $this->getRankLevel($newRank);
        $targetRankLevel = $this->getRankLevel($admin['rank']);
        $isDefaultOwner = ($currentUserId === $this->db->getDefaultOwnerId());

        if (!$isDefaultOwner && $newRankLevel >= $currentRankLevel) {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        if (!$isDefaultOwner && $targetRankLevel >= $currentRankLevel) {
            $this->reply($chatId, $this->t('errors.no_permission', $language));
            return;
        }

        $result = $this->db->setAdminRank($admin['user_id'], $newRank, $currentUserId);

        if ($result) {
            $this->reply($chatId, $this->t('admin.rank.success', $language, [$newRank]));
             // Notify user
            try {
                $this->telegram->sendMessage($admin['user_id'], $this->t('admin.rank.notification', $language, [$newRank]));
            } catch (\Exception $e) {
                // Ignore
            }
        } else {
            $this->reply($chatId, $this->t('admin.rank.error', $language));
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
