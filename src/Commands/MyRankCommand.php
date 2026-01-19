<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class MyRankCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/myrank';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.my_rank', $language);
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);

        $rankKey = 'user';
        if ($this->isAdmin($userId)) {
            $rankKey = $this->getCurrentAdminRank($userId);
        }

        $roleName = $this->t('commands.myrank.roles.' . $rankKey, $language);
        if ($roleName === 'commands.myrank.roles.' . $rankKey) {
            $roleName = $rankKey;
        }

        $messageText = $this->t('commands.myrank.title', $language, [$roleName]) . "\n";
        
        $desc = $this->t('commands.myrank.desc.' . $rankKey, $language);
        $messageText .= $this->t('commands.myrank.permissions', $language, [$desc]);

        $this->reply($chatId, $messageText);
    }
}
