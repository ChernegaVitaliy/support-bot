<?php

namespace App\Commands;

use App\Interfaces\CommandInterface;
use App\Services\Logger;
use App\Services\TelegramService;
use App\Services\DatabaseService;
use App\Services\Translator;
use App\Services\SessionManager;
use TelegramBot\Api\Types\Message;

abstract class BaseCommand implements CommandInterface
{
    protected Logger $logger;
    protected TelegramService $telegram;
    protected DatabaseService $db;
    protected Translator $translator;
    protected SessionManager $sessionManager;
    protected array $config;
    protected \App\Console\ServiceContainer $container;

    public function __construct(\App\Console\ServiceContainer $container)
    {
        $this->container = $container;
        $this->logger = $container->get('logger');
        $this->telegram = $container->get('telegram');
        $this->db = $container->get('db');
        $this->translator = $container->get('translator');
        $this->sessionManager = $container->get('session_manager');
        $this->config = []; // Config is now in container if needed, but keeping array for backward compat if used locally
    }

    abstract public function getName(): string;

    public function getDescription(string $language = 'uk'): string
    {
        return 'No description';
    }

    abstract public function execute(Message $message, string $language = 'uk'): void;

    public function isAdminOnly(): bool
    {
        return false;
    }

    public function getRequiredRank(): ?string
    {
        return null;
    }

    protected function t(string $key, string $language = 'uk', array $params = []): string
    {
        return $this->translator->translate($key, $language, $params);
    }

    protected function reply(string $chatId, string $text, ?object $replyMarkup = null): void
    {
        $this->telegram->sendMessage($chatId, $text, 'HTML', false, null, $replyMarkup);
    }

    protected function isAdmin(string $userId): bool
    {
        return $this->db->isAdmin($userId);
    }

    protected function hasPermission(string $requiredRank, ?string $currentRank): bool
    {
        $ranks = ['owner' => 3, 'admin' => 2, 'moderator' => 1];
        $currentLevel = $ranks[$currentRank] ?? 0;
        $requiredLevel = $ranks[$requiredRank] ?? 0;

        return $currentLevel >= $requiredLevel;
    }

    protected function getCurrentAdminRank(string $userId): ?string
    {
        return $this->db->getAdminRank($userId);
    }

    protected function getChatId(Message $message): string
    {
        return (string)$message->getChat()->getId();
    }

    protected function getUserId(Message $message): string
    {
        $user = $message->getFrom();
        return $user ? (string)$user->getId() : '';
    }

    protected function getUsername(Message $message): ?string
    {
        $user = $message->getFrom();
        return $user ? $user->getUsername() : null;
    }

    protected function getFirstName(Message $message): ?string
    {
        $user = $message->getFrom();
        return $user ? $user->getFirstName() : null;
    }

    protected function detectLanguage(Message $message): string
    {
        $user = $message->getFrom();

        if ($user) {
            return $this->translator->detectLanguage(
                [
                    'id' => $user->getId(),
                    'language_code' => $user->getLanguageCode()
                ],
                $this->db,
                $this->telegram
            );
        }

        return 'uk';
    }
}
