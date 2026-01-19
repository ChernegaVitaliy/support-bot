<?php

namespace App\Services;

use TelegramBot\Api\BotApi;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Update;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;
use TelegramBot\Api\Types\InputMedia\InputMedia;
use TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia;
use TelegramBot\Api\Types\Chat;

class TelegramService
{
    private BotApi $bot;
    private Logger $logger;
    private ?string $botUsername = null;
    private ?string $botName = null;

    public function __construct(string $token, Logger $logger)
    {
        $this->bot = new BotApi($token);
        $this->logger = $logger;

        $this->initializeBotInfo();
    }

    private function initializeBotInfo(): void
    {
        try {
            $botInfo = $this->bot->getMe();
            $this->botUsername = $botInfo->getUsername();
            $this->botName = $botInfo->getFirstName();
            $this->logger->debug("Бот запущений: @{$this->botUsername} ({$this->botName})");
        } catch (\Exception $e) {
            $this->logger->error("Не вдалося отримати інформацію про бота: " . $e->getMessage());
            throw $e;
        }
    }

    public function getBotUsername(): ?string
    {
        return $this->botUsername;
    }

    public function getBotName(): ?string
    {
        return $this->botName;
    }

    public function sendMessage(
        int|string $chatId,
        string $text,
        ?string $parseMode = 'HTML',
        bool $disableWebPagePreview = false,
        ?int $replyToMessageId = null,
        ?InlineKeyboardMarkup $replyMarkup = null
    ): ?Message {
        try {
            $chatId = (string)$chatId;
            $this->logger->debug("sendMessage до: $chatId, текст: " . substr($text, 0, 50) . "...");

            $message = $this->bot->sendMessage(
                $chatId,
                $text,
                $parseMode,
                $disableWebPagePreview,
                $replyToMessageId,
                $replyMarkup
            );

            $this->logger->debug("Повідомлення відправлено успішно");
            return $message;
        } catch (\Exception $e) {
            $this->logger->error("Помилка відправки повідомлення: " . $e->getMessage());
            return null;
        }
    }

    public function sendPhoto(
        int|string $chatId,
        string $photo,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        ?InlineKeyboardMarkup $replyMarkup = null
    ): ?Message {
        try {
            $chatId = (string)$chatId;
            return $this->bot->sendPhoto($chatId, $photo, $caption, null, $replyMarkup, false, $parseMode);
        } catch (\Exception $e) {
            $this->logger->error("Помилка відправки фото: " . $e->getMessage());
            return null;
        }
    }

    public function sendVideo(
        int|string $chatId,
        string $video,
        ?string $caption = null,
        ?string $parseMode = 'HTML',
        ?InlineKeyboardMarkup $replyMarkup = null
    ): ?Message {
        try {
            $chatId = (string)$chatId;
            return $this->bot->sendVideo($chatId, $video, null, $caption, null, $replyMarkup, false, false, $parseMode);
        } catch (\Exception $e) {
            $this->logger->error("Помилка відправки відео: " . $e->getMessage());
            return null;
        }
    }

    public function sendMediaGroup(
        int|string $chatId,
        \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia $media,
        ?bool $disableNotification = false,
        ?int $replyToMessageId = null
    ): ?array {
        try {
            $chatId = (string)$chatId;
            $this->logger->debug("sendMediaGroup до: $chatId, кількість медіа: " . count($media));
            return $this->bot->sendMediaGroup($chatId, $media, $disableNotification, $replyToMessageId);
        } catch (\Exception $e) {
            $this->logger->error("Помилка відправки групи медіа: " . $e->getMessage());
            return null;
        }
    }

    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        try {
            $chatId = (string)$chatId;
            $this->bot->deleteMessage($chatId, $messageId);
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Помилка видалення повідомлення: " . $e->getMessage());
            return false;
        }
    }

    public function editMessageText(
        int|string $chatId,
        int $messageId,
        string $text,
        ?string $parseMode = 'HTML',
        ?InlineKeyboardMarkup $replyMarkup = null
    ): ?Message {
        try {
            $chatId = (string)$chatId;
            return $this->bot->editMessageText($chatId, $messageId, $text, $parseMode, null, $replyMarkup);
        } catch (\Exception $e) {
            $this->logger->error("Помилка редагування повідомлення: " . $e->getMessage());
            return null;
        }
    }

    public function editMessageReplyMarkup(
        int|string $chatId,
        int $messageId,
        ?InlineKeyboardMarkup $replyMarkup = null
    ): ?Message {
        try {
            $chatId = (string)$chatId;
            return $this->bot->editMessageReplyMarkup($chatId, $messageId, $replyMarkup);
        } catch (\Exception $e) {
            $this->logger->error("Помилка редагування клавіатури повідомлення: " . $e->getMessage());
            return null;
        }
    }

    public function answerCallbackQuery(
        string $callbackQueryId,
        ?string $text = null,
        bool $showAlert = false,
        string $url = '',
        int $cacheTime = 0
    ): bool {
        try {
            $this->bot->answerCallbackQuery($callbackQueryId, $text, $showAlert, $url, $cacheTime);
            $this->logger->debug("✅ Відповідь на callback: $text");
            return true;
        } catch (\Exception $e) {
            $this->logger->error("Помилка відповіді на callback: " . $e->getMessage());
            return false;
        }
    }

    public function getUpdates(int $offset = 0, int $limit = 100, int $timeout = 0, array $allowedUpdates = []): array
    {
        try {
            return $this->bot->getUpdates($offset, $limit, $timeout, $allowedUpdates);
        } catch (\Exception $e) {
            $this->logger->error("Помилка отримання оновлень: " . $e->getMessage());
            return [];
        }
    }

    public function getChat(int|string $chatId): mixed
    {
        try {
            $chatId = (string)$chatId;
            return $this->bot->getChat($chatId);
        } catch (\Exception $e) {
            $this->logger->error("Помилка отримання інформації про чат: " . $e->getMessage());
            return null;
        }
    }

    public function cleanCommandFromBotUsername(string $text, ?string $botUsername = null): string
    {
        $cleanText = $text;
        $botUsername = $botUsername ?? $this->botUsername;

        if ($botUsername && preg_match("/^\/[a-zA-Z0-9_]+@$botUsername/i", $cleanText)) {
            $cleanText = preg_replace("/@$botUsername\s*/i", '', $cleanText);
            $cleanText = trim($cleanText);
            $this->logger->debug("CMD_CLEANED: $text -> $cleanText");
        }

        return $cleanText;
    }

    public function getUserLanguageFromApi(string $userId): ?string
    {
        try {
            $chat = $this->bot->getChat($userId);

            if ($chat) {
                $languageCode = null;

                if (method_exists($chat, 'getLanguageCode')) {
                    $languageCode = $chat->getLanguageCode();
                } elseif (property_exists($chat, 'languageCode')) {
                    $languageCode = $chat->languageCode;
                } elseif (method_exists($chat, 'getNaturalLanguageCode')) {
                    $languageCode = $chat->getNaturalLanguageCode();
                }

                if ($languageCode) {
                    return $languageCode;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Помилка отримання мови з API: " . $e->getMessage());
        }

        return null;
    }

    public function getBot(): BotApi
    {
        return $this->bot;
    }
}
