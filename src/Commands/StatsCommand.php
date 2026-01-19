<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class StatsCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/stats';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.stats', $language);
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);
        $isAdmin = $this->isAdmin($userId);
        $currentRank = $this->getCurrentAdminRank($userId);

        $stats = $this->db->getStats();

        $text = $this->t('commands.stats.title', $language) . "\n";
        $text .= $this->t('commands.stats.users', $language, [$stats['total_users']]) . "\n";
        $text .= $this->t('commands.stats.admins', $language, [$stats['total_admins']]);

        if ($isAdmin) {
             $rankText = $this->t('commands.myrank.roles.' . $currentRank, $language);
             if ($rankText === 'commands.myrank.roles.' . $currentRank) $rankText = $currentRank;
             
             $text .= "\n" . $this->t('commands.myrank.title', $language, [$rankText]);
        } else {
             $text .= "\n" . $this->t('commands.myrank.roles.user', $language);
        }

        if (isset($stats['total_reports'])) {
            $text .= "\n\n" . $this->t('commands.stats.reports_details', $language, [$stats['total_reports'], $stats['pending_reports']]);
        }

        $this->reply($chatId, $text);
    }
}
