<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class MyIdCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/myid';
    }

    public function getDescription(): string
    {
        return 'Показати ваш ID та інформацію';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = (string)$message->getFrom()->getId();
        $username = $message->getFrom()->getUsername() ?? $this->t('common.not_specified', $language);
        $firstName = $message->getFrom()->getFirstName() ?? $this->t('common.not_specified', $language);

        // t() now handles sprintf inside if params are passed, but here we construct it manually?
        // Wait, BaseCommand::t calls translator->translate. 
        // My new Translator::translate uses vsprintf if params provided.
        // So I can pass array.
        
        $userInfo = $this->t('commands.myid.text', $language, [
            $userId,
            $firstName,
            $username
        ]);

        $this->reply($chatId, $userInfo);
    }
}
