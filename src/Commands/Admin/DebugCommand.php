<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class DebugCommand extends BaseCommand
{
    private const LOG_LEVELS = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

    public function getName(): string
    {
        return '/debug';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.debug', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): string
    {
        return 'owner';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $text = $message->getText() ?? '';
        $parts = explode(' ', trim($text));
        $config = $this->container->get('config');

        if (count($parts) >= 2) {
            $requestedLevel = strtoupper($parts[1]);
            if (in_array($requestedLevel, self::LOG_LEVELS)) {
                $config->setLogLevel($requestedLevel);
                $this->container->get('logger')->setLevel($requestedLevel);
                $this->reply($chatId, $this->t('admin.debug.changed', $language, [$requestedLevel]));
                return;
            } else {
                $this->reply($chatId, $this->t('admin.debug.invalid', $language, [implode(', ', self::LOG_LEVELS)]));
                return;
            }
        }

        $currentLevel = $config->getLogLevel();
        $keyboard = $this->buildLevelKeyboard($currentLevel, $language);

        $this->telegram->sendMessage(
            $chatId,
            $this->t('admin.debug.current', $language, [$currentLevel]),
            'HTML',
            false,
            null,
            $keyboard
        );
    }

    private function buildLevelKeyboard(string $currentLevel, string $language): InlineKeyboardMarkup
    {
        $buttons = [];

        foreach (self::LOG_LEVELS as $level) {
            $indicator = ($level === $currentLevel) ? '✅ ' : '';
            $buttons[] = ['text' => $indicator . $level, 'callback_data' => "debug_set_$level"];
        }

        return new InlineKeyboardMarkup([$buttons]);
    }
}
