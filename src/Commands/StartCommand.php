<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class StartCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/start';
    }

    public function getDescription(): string
    {
        return 'Почати роботу з ботом';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);
        $firstName = $this->getFirstName($message) ?? '';

        $this->db->addUser($userId, $this->getUsername($message), $firstName, $language);

        $text = $this->t('commands.start.welcome', $language);
        
        if ($this->isAdmin($userId)) {
            $currentRank = $this->getCurrentAdminRank($userId);
            // Translate the rank value itself if possible, or use as is
            $rankText = $this->t('commands.myrank.roles.' . $currentRank, $language);
            // Fallback if rank translation not found
            if ($rankText === 'commands.myrank.roles.' . $currentRank) {
                $rankText = $currentRank;
            }
            
            $text .= "\n" . $this->t('commands.myrank.title', $language, [$rankText]);
        }
        $text .= "\n" . $this->t('commands.help.hint', $language);

        $this->reply($chatId, $text);
    }
}
