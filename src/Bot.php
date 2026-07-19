<?php

namespace App;

use App\Config\Config;
use App\Services\Logger;
use App\Services\DatabaseService;
use App\Services\TelegramService;
use App\Services\Translator;
use App\Services\SessionManager;
use App\Interfaces\CommandInterface;
use TelegramBot\Api\Types\Update;
use Psr\Container\ContainerInterface;

class Bot
{
    private ContainerInterface $container;
    private Config $config;
    private Logger $logger;
    private DatabaseService $db;
    private TelegramService $telegram;
    private Translator $translator;
    private SessionManager $sessionManager;
    private array $commands = [];
    private int $lastUpdateId = 0;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

        $this->config = $container->get('config');
        $this->logger = $container->get('logger');
        $this->db = $container->get('db');
        $this->telegram = $container->get('telegram');
        $this->translator = $container->get('translator');
        $this->sessionManager = $container->get('session_manager');

        $this->registerDefaultCommands();

        $this->displayBanner();
    }

    private function registerDefaultCommands(): void
    {
        // Register all available commands
        $this->commands[] = new \App\Commands\AboutCommand($this->container);
        $this->commands[] = new \App\Commands\StartCommand($this->container);
        $this->commands[] = new \App\Commands\HelpCommand($this->container);
        $this->commands[] = new \App\Commands\ReportCommand($this->container);
        $this->commands[] = new \App\Commands\StatsCommand($this->container);
        $this->commands[] = new \App\Commands\CancelCommand($this->container);
        $this->commands[] = new \App\Commands\MyRankCommand($this->container);
        $this->commands[] = new \App\Commands\ProfileCommand($this->container);
        $this->commands[] = new \App\Commands\MyIdCommand($this->container);

        // Admin commands
        $this->commands[] = new \App\Commands\Admin\ReportsCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\BroadcastCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\RejectReportCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\AcceptReportCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\DebugCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\SetRankCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\RemoveAdminCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\AddAdminCommand($this->container);
        $this->commands[] = new \App\Commands\Admin\AdminListCommand($this->container);
    }

    private function displayBanner(): void
    {
        $this->logger->info("=====================================");
        $this->logger->info("   Support Bot Started Successfully");
        $this->logger->info("=====================================");
    }

    private function getCommand(string $commandName): ?CommandInterface
    {
        foreach ($this->commands as $command) {
            if ($command->getName() === $commandName) {
                return $command;
            }
        }
        return null;
    }

    private function setBotProfileInfo(): void
    {
        try {
            $language = 'uk';

            $botDescription = $this->translator->translate('bot.description', $language);
            $botShortDescription = $this->translator->translate('bot.about', $language);

            $this->telegram->setMyDescription($botDescription, $language);
            $this->telegram->setMyShortDescription($botShortDescription, $language);

            $this->logger->info("Bot profile information set from translations");
        } catch (\Exception $e) {
            $this->logger->warning("Could not set bot profile information: " . $e->getMessage());
        }
    }

    private function registerBotCommands(): void
    {
        try {
            $language = 'uk'; // Default language for commands
            $commands = [];

            // Get all registered commands
            foreach ($this->commands as $command) {
                $commands[] = [
                    'command' => ltrim($command->getName(), '/'),
                    'description' => $command->getDescription($language)
                ];
            }

            // Register commands for Ukrainian
            if ($this->telegram->setMyCommands($commands, null, $language)) {
                $this->logger->info("Bot commands registered for language: $language");
            } else {
                $this->logger->warning("Failed to register bot commands for language: $language");
            }
        } catch (\Exception $e) {
            $this->logger->warning("Could not register bot commands: " . $e->getMessage());
        }
    }

    public function run(): void
    {
        while (true) {
            $this->processConsoleInput();
            $this->processUpdates();

            usleep(500000);

            $this->logger->checkAndRotate(
                $this->config->getMaxFileSize(),
                $this->config->getBackupCount()
            );
        }
    }

    private function processConsoleInput(): void
    {
        $read = [STDIN];
        $write = [];
        $except = [];

        if (stream_select($read, $write, $except, 0, 100000) > 0) {
            $consoleInput = trim(fgets(STDIN));
            if ($consoleInput) {
                $this->handleConsoleCommand($consoleInput);
            }
        }
    }

    private function handleConsoleCommand(string $input): void
    {
        $parts = explode(' ', $input);
        $command = $parts[0];

        switch ($command) {
            case 'help':
                $this->logger->info("=== Консольні команди ===");
                $this->logger->info("help - показати цю допомогу");
                $this->logger->info("stats - показати статистику");
                $this->logger->info("users - показати кількість користувачів");
                $this->logger->info("admins - показати список адмінів");
                $this->logger->info("exit - вийти");
                break;

            case 'stats':
                $stats = $this->db->getStats();
                $this->logger->info("=== Статистика ===");
                $this->logger->info("Користувачів: {$stats['total_users']}");
                $this->logger->info("Адмінів: {$stats['total_admins']}");
                $this->logger->info("Репортів: {$stats['total_reports']}");
                $this->logger->info("Очікують: {$stats['pending_reports']}");
                $this->logger->info("Прийнято: {$stats['accepted_reports']}");
                $this->logger->info("Відхилено: {$stats['rejected_reports']}");
                break;

            case 'users':
                $this->logger->info("Користувачів: " . $this->db->getUsersCount());
                break;

            case 'admins':
                $admins = $this->db->getAllAdmins();
                $this->logger->info("=== Список адмінів ===");
                foreach ($admins as $admin) {
                    $icon = match($admin['rank']) {
                        'owner' => '👑',
                        'admin' => '🛡️',
                        'moderator' => '🔧',
                        default => '❓'
                    };
                    $this->logger->info("{$icon} {$admin['username']} ({$admin['rank']})");
                }
                break;

            case 'sessions':
                $this->logger->info("=== Активні сесії ===");
                $this->logger->info("Репорти: " . $this->sessionManager->getActiveReportSessionsCount());
                $this->logger->info("Розсилки: " . $this->sessionManager->getActiveBroadcastSessionsCount());
                $this->logger->info("Адмін-дії: " . $this->sessionManager->getActiveAdminSessionsCount());
                break;

            case 'exit':
            case 'quit':
                $this->logger->info("Завершення роботи...");
                exit(0);

            default:
                $this->logger->warning("Невідома консольна команда: $command");
        }
    }

    private function processUpdates(): void
    {
        try {
            $updates = $this->telegram->getUpdates($this->lastUpdateId + 1, 100, 1);

            if (empty($updates)) {
                return;
            }

            foreach ($updates as $update) {
                $this->lastUpdateId = $update->getUpdateId();

                if ($update->getMessage()) {
                    $this->handleMessage($update->getMessage());
                }

                if ($update->getCallbackQuery()) {
                    $this->handleCallbackQuery($update->getCallbackQuery(), $language ?? 'uk');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("Помилка обробки оновлень: " . $e->getMessage());
        }
    }

    private function handleMessage(\TelegramBot\Api\Types\Message $message): void
    {
        $chatId = (string)$message->getChat()->getId();
        $chatType = $message->getChat()->getType();
        $from = $message->getFrom();

        if (!$from) {
            return;
        }

        $userId = (string)$from->getId();
        $username = $from->getUsername();
        $firstName = $from->getFirstName();
        $text = $message->getText() ?? '';

        $language = $this->translator->detectLanguage(
            [
                'id' => $from->getId(),
                'language_code' => $from->getLanguageCode()
            ],
            $this->db,
            $this->telegram
        );

        $this->db->addUser($userId, $username, $firstName, $language);

        $cleanText = $this->telegram->cleanCommandFromBotUsername($text);

        if ($chatType === 'private' && strpos($cleanText, '/') === 0) {
            $this->executeCommand($cleanText, $message, $language);
        } elseif ($chatType === 'private') {
            $this->handlePrivateMessage($message, $language);
        } elseif ($this->sessionManager->hasReportSession($userId) ||
                 $this->sessionManager->hasBroadcastSession($userId) ||
                 $this->sessionManager->hasAdminActionSession($userId)) {
            $this->handlePrivateMessage($message, $language);
        } else {
            $this->handleGroupMessage($message, $language);
        }
    }

    private function executeCommand(string $commandText, \TelegramBot\Api\Types\Message $message, string $language): void
    {
        $parts = explode(' ', $commandText);
        $commandName = $parts[0];
        $command = $this->getCommand($commandName);

        if (!$command) {
            $this->logger->warning("Невідома команда: $commandName");
            return;
        }

        $userId = (string)$message->getFrom()->getId();

        if ($command->isAdminOnly() && !$this->db->isAdmin($userId)) {
            $chatId = (string)$message->getChat()->getId();
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.not_admin', $language));
            return;
        }

        $requiredRank = $command->getRequiredRank();
        if ($requiredRank) {
            $currentRank = $this->db->getAdminRank($userId);
            if (!$this->hasPermission($requiredRank, $currentRank)) {
                $chatId = (string)$message->getChat()->getId();
                $this->telegram->sendMessage($chatId, $this->translator->translate('errors.no_permission', $language));
                return;
            }
        }

        try {
            $command->execute($message, $language);
        } catch (\Exception $e) {
            $this->logger->error("Помилка виконання команди $commandName: " . $e->getMessage());
            $chatId = (string)$message->getChat()->getId();
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.command_failed', $language));
        }
    }

    private function handlePrivateMessage(\TelegramBot\Api\Types\Message $message, string $language): void
    {
        $chatId = (string)$message->getChat()->getId();
        $text = $message->getText() ?? '';
        $userId = (string)$message->getFrom()->getId();

        if ($this->sessionManager->hasReportSession($userId)) {
            $this->container->get('session_handler')->handleReportSession($message, $language);
            return;
        }

        if ($this->sessionManager->hasBroadcastSession($userId)) {
            $this->handleBroadcastSession($message, $language);
            return;
        }

        if ($this->sessionManager->hasAdminActionSession($userId)) {
            $this->container->get('session_handler')->handleAdminActionSession($message, $language);
            return;
        }

        if (strpos($text, '/') === 0) {
            $cleanText = $this->telegram->cleanCommandFromBotUsername($text);
            $this->executeCommand($cleanText, $message, $language);
        }
    }

    private function handleGroupMessage(\TelegramBot\Api\Types\Message $message, string $language): void
    {
        $text = $message->getText() ?? '';
        $cleanText = strip_tags($text);
        $cleanText = str_replace(['>', '<', '&gt;', '&lt;', '`'], '', $cleanText);
        $cleanText = trim($cleanText);

        if (strpos($cleanText, '/') === 0) {
            $cleanText = $this->telegram->cleanCommandFromBotUsername($cleanText);
            $this->executeCommand($cleanText, $message, $language);
        }
    }

    private function handleBroadcastCallback(string $callbackData, string $chatId, int $messageId, string $language, string $userId): void
    {
        $session = $this->sessionManager->getBroadcastSession($userId);

        if (!$session) {
            return;
        }

        switch ($callbackData) {
            case 'broadcast_all':
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, [
                    'step' => 'message',
                    'selected_languages' => ['all'],
                    'target_type' => 'all'
                ]));
                $this->telegram->editMessageText($chatId, $messageId,
                    "<b>" . $this->translator->translate('admin.broadcast.selected_all', $language) . "</b>\n\n" .
                    $this->translator->translate('admin.broadcast.send_message', $language) . "\n\n" .
                    "<i>" . $this->translator->translate('admin.broadcast.media_album_hint', $language) . "</i>"
                );
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_language':
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, [
                    'step' => 'language',
                    'target_type' => 'all'
                ]));
                $this->showLanguageSelection($chatId, $messageId, $session, $language, $userId);
                break;

            case 'broadcast_admins':
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, [
                    'step' => 'message',
                    'selected_languages' => ['all'],
                    'target_type' => 'admins'
                ]));
                $this->telegram->editMessageText($chatId, $messageId,
                    "<b>" . $this->translator->translate('admin.broadcast.selected_admins', $language) . "</b>\n\n" .
                    $this->translator->translate('admin.broadcast.send_message', $language) . "\n\n" .
                    "<i>" . $this->translator->translate('admin.broadcast.media_album_hint', $language) . "</i>"
                );
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_users':
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, [
                    'step' => 'message',
                    'selected_languages' => ['all'],
                    'target_type' => 'users'
                ]));
                $this->telegram->editMessageText($chatId, $messageId,
                    "<b>" . $this->translator->translate('admin.broadcast.selected_users', $language) . "</b>\n\n" .
                    $this->translator->translate('admin.broadcast.send_message', $language) . "\n\n" .
                    "<i>" . $this->translator->translate('admin.broadcast.media_album_hint', $language) . "</i>"
                );
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_cancel':
                $this->sessionManager->clearBroadcastSession($userId);
                $this->telegram->editMessageText($chatId, $messageId, "<b>" . $this->translator->translate('admin.broadcast.cancelled', $language) . "</b>");
                break;

            case 'broadcast_add_photo':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, ['awaiting_media_type' => 'photo']));
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.add_photo_title', $language) . "</b>\n\n" . $this->translator->translate('admin.broadcast.send_photo', $language));
                break;

            case 'broadcast_add_video':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $this->sessionManager->setBroadcastSession($userId, array_merge($session, ['awaiting_media_type' => 'video']));
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.add_video_title', $language) . "</b>\n\n" . $this->translator->translate('admin.broadcast.send_video', $language));
                break;

            case 'broadcast_view_media':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $this->showMediaGallery($chatId, $session, $language);
                break;

            case 'broadcast_clear_media':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $session['media'] = [];
                $this->sessionManager->setBroadcastSession($userId, $session);
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.media_cleared', $language) . "</b>");
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_back_to_media':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_finish_media':
                $session = $this->sessionManager->getBroadcastSession($userId);
                unset($session['awaiting_media_type']);
                if (empty($session['message_text']) && empty($session['media'])) {
                    $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.error', $language) . "</b>\n\n" . $this->translator->translate('admin.broadcast.no_content', $language));
                    return;
                }
                $session['step'] = 'confirm';
                $this->sessionManager->setBroadcastSession($userId, $session);
                $this->showBroadcastPreview($chatId, $session, $language);
                break;

            case 'broadcast_confirm':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $this->executeBroadcast($chatId, $session, $language);
                $this->sessionManager->clearBroadcastSession($userId);
                $this->telegram->editMessageText($chatId, $messageId, "<b>" . $this->translator->translate('admin.broadcast.started', $language) . "</b>");
                break;

            case 'broadcast_cancel_final':
                $this->sessionManager->clearBroadcastSession($userId);
                $this->telegram->editMessageText($chatId, $messageId, "<b>" . $this->translator->translate('admin.broadcast.cancelled', $language) . "</b>");
                break;

            case 'broadcast_edit':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $session['step'] = 'message';
                $this->sessionManager->setBroadcastSession($userId, $session);
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('admin.broadcast.editing', $language) . "</b>\n\n" . $this->translator->translate('admin.broadcast.send_new_message', $language));
                $this->showMediaControls($chatId, $session, $language);
                break;

            case 'broadcast_lang_next':
                $session = $this->sessionManager->getBroadcastSession($userId);
                $targetType = $session['target_type'] ?? 'all';
                $targetName = match($targetType) {
                    'admins' => $this->translator->translate('admin.broadcast.admins_target', $language),
                    'users' => $this->translator->translate('admin.broadcast.users_target', $language),
                    default => $this->translator->translate('admin.broadcast.all_target', $language)
                };
                if (empty($session['selected_languages'])) {
                    $session['selected_languages'] = ['all'];
                    $langsText = $this->translator->translate('admin.broadcast.all_target', $language) . " $targetName";
                } else {
                    $langsText = implode(', ', array_map([$this, 'getLanguageName'], $session['selected_languages'])) . " ($targetName)";
                }
                $session['step'] = 'message';
                $this->sessionManager->setBroadcastSession($userId, $session);
                $this->telegram->editMessageText($chatId, $messageId,
                    "<b>" . $this->translator->translate('admin.broadcast.selected_for', $language) . ":</b> $langsText\n\n" .
                    $this->translator->translate('admin.broadcast.send_message', $language)
                );
                $this->showMediaControls($chatId, $session, $language);
                break;

            default:
                if (strpos($callbackData, 'broadcast_lang_') === 0) {
                    $selectedLang = substr($callbackData, 15);
                    if ($callbackData === 'broadcast_lang_prev') {
                        $session = $this->sessionManager->getBroadcastSession($userId);
                        $currentPage = $session['lang_page'] ?? 0;
                        if ($currentPage > 0) {
                            $session['lang_page'] = $currentPage - 1;
                            $this->sessionManager->setBroadcastSession($userId, $session);
                        }
                        $this->showLanguageSelection($chatId, $messageId, $session, $language, $userId);
                    } elseif ($callbackData === 'broadcast_lang_next_page') {
                        $session = $this->sessionManager->getBroadcastSession($userId);
                        $currentPage = $session['lang_page'] ?? 0;
                        $allLanguages = ['uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja', 'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro', 'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el', 'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'];
                        $languagesPerPage = 8;
                        $totalPages = ceil(count($allLanguages) / $languagesPerPage);
                        if ($currentPage < $totalPages - 1) {
                            $session['lang_page'] = $currentPage + 1;
                            $this->sessionManager->setBroadcastSession($userId, $session);
                        }
                        $this->showLanguageSelection($chatId, $messageId, $session, $language, $userId);
                    } else {
                        $this->handleLanguageSelection($chatId, $messageId, $selectedLang, $language, $userId);
                    }
                }
        }
    }

    private function handleLanguageSelection(string $chatId, int $messageId, string $selectedLang, string $language, string $userId): void
    {
        $session = $this->sessionManager->getBroadcastSession($userId);
        $selectedLangs = $session['selected_languages'] ?? [];

        if (in_array($selectedLang, $selectedLangs)) {
            $selectedLangs = array_diff($selectedLangs, [$selectedLang]);
        } else {
            $selectedLangs[] = $selectedLang;
        }

        $session['selected_languages'] = $selectedLangs;
        $this->sessionManager->setBroadcastSession($userId, $session);
        $this->showLanguageSelection($chatId, $messageId, $session, $language);
    }

    private function showLanguageSelection(string $chatId, int $messageId, array $session, string $language): void
    {
        $allLanguages = ['uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja', 'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro', 'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el', 'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'];
        $currentPage = $session['lang_page'] ?? 0;
        $languagesPerPage = 8;
        $targetType = $session['target_type'] ?? 'all';

        $targetName = match($targetType) {
            'admins' => $this->translator->translate('admin.broadcast.admins_target', $language),
            'users' => $this->translator->translate('admin.broadcast.users_target', $language),
            default => $this->translator->translate('admin.broadcast.all_target', $language)
        };

        $totalPages = ceil(count($allLanguages) / $languagesPerPage);
        $startIndex = $currentPage * $languagesPerPage;
        $pageLanguages = array_slice($allLanguages, $startIndex, $languagesPerPage);

        $message = "<b>" . $this->translator->translate('common.select_languages_broadcast', $language) . " $targetName:</b>\n\n";

        $selectedLangs = $session['selected_languages'] ?? [];
        if (!empty($selectedLangs)) {
            $selectedText = implode(', ', array_map([$this, 'getLanguageName'], $selectedLangs));
            $message .= "<b>" . $this->translator->translate('common.selected', $language) . ":</b> $selectedText\n\n";
        }

        $message .= "<i>" . $this->translator->translate('common.page', $language) . " " . ($currentPage + 1) . " " . $this->translator->translate('common.of', $language) . " $totalPages</i>\n";
        $message .= "<i>" . $this->translator->translate('common.select_multiple_languages', $language) . "</i>\n\n";
        $message .= "<b>" . $this->translator->translate('common.broadcast_or_send_all', $language) . " $targetName</b>";

        $keyboard = [];
        $row = [];
        foreach ($pageLanguages as $langCode) {
            $isSelected = in_array($langCode, $selectedLangs);
            $row[] = ['text' => ($isSelected ? '[x] ' : '[ ] ') . $this->getLanguageName($langCode), 'callback_data' => 'broadcast_lang_' . $langCode];
            if (count($row) == 2) {
                $keyboard[] = $row;
                $row = [];
            }
        }
        if (!empty($row)) {
            $keyboard[] = $row;
        }

        $navButtons = [];
        if ($currentPage > 0) {
            $navButtons[] = ['text' => $this->translator->translate('common.previous_page', $language), 'callback_data' => 'broadcast_lang_prev'];
        }
        $navButtons[] = ['text' => $this->translator->translate('common.next', $language), 'callback_data' => 'broadcast_lang_next'];
        if ($currentPage < $totalPages - 1) {
            $navButtons[] = ['text' => $this->translator->translate('common.next_page', $language), 'callback_data' => 'broadcast_lang_next_page'];
        }
        $keyboard[] = $navButtons;
        $keyboard[] = [['text' => $this->translator->translate('common.cancel', $language), 'callback_data' => 'broadcast_cancel']];

        $this->telegram->editMessageText($chatId, $messageId, $message, 'HTML', new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($keyboard));
    }

    private function getLanguageName(string $langCode): string
    {
        $languageNames = [
            'uk' => 'Українська',
            'ru' => 'Русский',
            'en' => 'English',
            'es' => 'Español',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'it' => 'Italiano',
            'pt' => 'Português',
            'zh' => '中文',
        ];
        return $languageNames[$langCode] ?? $langCode;
    }

    private function showMediaGallery(string $chatId, array $session, string $language): void
    {
        $media = $session['media'] ?? [];
        $mediaCount = count($media);

        if ($mediaCount === 0) {
            $this->telegram->sendMessage($chatId, $this->translator->translate('common.no_media_added', $language));
            return;
        }

        $message = "<b>" . $this->translator->translate('common.media_gallery', $language) . "</b>\n\n";
        $message .= $this->translator->translate('common.total_files', $language) . ": $mediaCount\n\n";

        $keyboard = [];
        $photos = array_filter($media, fn($m) => $m['type'] === 'photo');
        $videos = array_filter($media, fn($m) => $m['type'] === 'video');

        $photoCount = count($photos);
        $videoCount = count($videos);

        if ($photoCount > 0) {
            $message .= "<b>" . $this->translator->translate('common.photos', $language) . ":</b> $photoCount\n";
        }
        if ($videoCount > 0) {
            $message .= "<b>" . $this->translator->translate('common.videos', $language) . ":</b> $videoCount\n";
        }

        $message .= "\n<i>" . $this->translator->translate('common.media_album_will_be_sent', $language) . "</i>";

        $keyboard[] = [
            ['text' => $this->translator->translate('common.clear_all', $language), 'callback_data' => 'broadcast_clear_media'],
            ['text' => $this->translator->translate('common.back', $language), 'callback_data' => 'broadcast_back_to_media']
        ];

        $this->telegram->sendMessage($chatId, $message, 'HTML', false, null, new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($keyboard));
    }

    private function showMediaControls(string $chatId, array $session, string $language): void
    {
        $mediaCount = count($session['media'] ?? []);
        $photosCount = count(array_filter($session['media'] ?? [], fn($m) => $m['type'] === 'photo'));
        $videosCount = count(array_filter($session['media'] ?? [], fn($m) => $m['type'] === 'video'));

        $message = "<b>" . $this->translator->translate('common.media_management', $language) . "</b>\n\n";

        if (!empty($session['message_text'])) {
            $message .= "<b>" . $this->translator->translate('common.text', $language) . ":</b> " . substr($session['message_text'], 0, 100) . (strlen($session['message_text']) > 100 ? "..." : "") . "\n\n";
        }

        $message .= "<b>" . $this->translator->translate('common.media_statistics', $language) . ":</b>\n";
        $message .= $this->translator->translate('common.photos', $language) . ": $photosCount " . $this->translator->translate('common.items', $language) . "\n";
        $message .= $this->translator->translate('common.videos', $language) . ": $videosCount " . $this->translator->translate('common.items', $language) . "\n";
        $message .= $this->translator->translate('common.total', $language) . ": $mediaCount " . $this->translator->translate('common.files', $language) . "\n\n";

        if ($mediaCount > 0) {
            $message .= "<i>" . $this->translator->translate('admin.broadcast.media_album_hint', $language) . "</i>\n\n";
        }

        $message .= $this->translator->translate('common.choose_action', $language);

        $keyboardArray = [];
        $keyboardArray[] = [
            ['text' => $this->translator->translate('common.add_photo', $language), 'callback_data' => 'broadcast_add_photo'],
            ['text' => $this->translator->translate('common.add_video', $language), 'callback_data' => 'broadcast_add_video']
        ];

        if ($mediaCount > 0) {
            $keyboardArray[] = [
                ['text' => $this->translator->translate('common.media_management', $language), 'callback_data' => 'broadcast_view_media'],
                ['text' => $this->translator->translate('common.clear_all', $language), 'callback_data' => 'broadcast_clear_media']
            ];
        }

        $keyboardArray[] = [
            ['text' => $this->translator->translate('common.finish_adding', $language), 'callback_data' => 'broadcast_finish_media']
        ];

        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup($keyboardArray);

        $this->telegram->sendMessage($chatId, $message, 'HTML', false, null, $keyboard);
    }

    private function showBroadcastPreview(string $chatId, array $session, string $language): void
    {
        $previewMessage = "<b>" . $this->translator->translate('admin.broadcast.preview', $language) . "</b>\n\n";

        if (in_array('all', $session['selected_languages'])) {
            $previewMessage .= "<b>" . $this->translator->translate('common.recipients', $language) . ":</b> " . $this->translator->translate('common.all_users', $language) . "\n";
        } else {
            $langsText = implode(', ', array_map([$this, 'getLanguageName'], $session['selected_languages']));
            $previewMessage .= "<b>" . $this->translator->translate('common.languages', $language) . ":</b> $langsText\n";
        }

        $previewMessage .= "<b>" . $this->translator->translate('common.text', $language) . ":</b> " . ($session['message_text'] ?: $this->translator->translate('common.none', $language)) . "\n";

        $mediaCount = count($session['media'] ?? []);
        $photosCount = count(array_filter($session['media'] ?? [], fn($m) => $m['type'] === 'photo'));
        $videosCount = count(array_filter($session['media'] ?? [], fn($m) => $m['type'] === 'video'));

        $previewMessage .= "<b>" . $this->translator->translate('common.media', $language) . ":</b> $mediaCount " . $this->translator->translate('common.files', $language) . " ($photosCount " . $this->translator->translate('common.photos', $language) . ", $videosCount " . $this->translator->translate('common.videos', $language) . ")\n\n";
        $previewMessage .= "<i>" . $this->translator->translate('admin.broadcast.media_album_hint', $language) . "</i>\n\n";

        $previewMessage .= "<b>" . $this->translator->translate('common.everything_correct_confirm', $language) . "</b>";

        $keyboard = new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([
            [
                ['text' => $this->translator->translate('common.yes_start_broadcast', $language), 'callback_data' => 'broadcast_confirm'],
                ['text' => $this->translator->translate('common.edit', $language), 'callback_data' => 'broadcast_edit']
            ],
            [
                ['text' => $this->translator->translate('common.cancel', $language), 'callback_data' => 'broadcast_cancel_final']
            ]
        ]);

        $this->telegram->sendMessage($chatId, $previewMessage, 'HTML', false, null, $keyboard);
    }

    private function executeBroadcast(string $chatId, array $session, string $language): void
    {
        $this->logger->info("Запуск розсилки для тип: " . ($session['target_type'] ?? 'all'));
        
        $users = [];
        $allUsers = $this->db->getAllUsers();
        $targetType = $session['target_type'] ?? 'all';

        foreach ($allUsers as $user) {
            $userId = $user['user_id'];
            $isAdmin = $this->db->isAdmin($userId);
            $userLang = $this->db->getUserLanguage($userId) ?? 'uk';

            $userMatchesType = false;
            if ($targetType === 'admins' && $isAdmin) {
                $userMatchesType = true;
            } elseif ($targetType === 'users' && !$isAdmin) {
                $userMatchesType = true;
            } elseif ($targetType === 'all') {
                $userMatchesType = true;
            }

            if (!$userMatchesType) continue;

            $userMatchesLanguage = false;
            if (in_array('all', $session['selected_languages'])) {
                $userMatchesLanguage = true;
            } else {
                $userMatchesLanguage = in_array($userLang, $session['selected_languages']);
            }

            if (!$userMatchesLanguage) continue;

            $users[] = $user;
        }

        $totalUsers = count($users);

        if ($totalUsers === 0) {
            $targetName = match($targetType) {
                'admins' => $this->translator->translate('admin.broadcast.no_admins', $language),
                'users' => $this->translator->translate('admin.broadcast.no_users', $language),
                default => $this->translator->translate('admin.broadcast.no_users', $language)
            };
            $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('admin.broadcast.error', $language) . "</b>\n\n$targetName");
            return;
        }

        $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('admin.broadcast.progress', $language) . "</b>\n\n" . $this->translator->translate('common.progress', $language) . ": 0/$totalUsers\n" . $this->translator->translate('common.successful', $language) . ": 0");

        $success = 0;
        $failed = 0;
        $current = 0;

        foreach ($users as $user) {
            $current++;
            $userChatId = $user['user_id'];

            try {
                if (!empty($session['media'])) {
                    $inputMedia = $this->prepareMediaForSending($session['media'], $session['message_text'] ?? '');
                    $this->logger->debug("Відправка медіа групи користувачу: $userChatId, кількість: " . $inputMedia->count());
                    $this->telegram->sendMediaGroup($userChatId, $inputMedia, false, null);
                } else {
                    $this->telegram->sendMessage($userChatId, $session['message_text'], 'HTML');
                }
                $success++;
            } catch (\Exception $e) {
                $this->logger->error("Помилка відправки користувачу $userChatId: " . $e->getMessage());
                $failed++;
            }

            if ($current % 5 === 0 || $current === $totalUsers) {
                $progressMessage = "<b>" . $this->translator->translate('admin.broadcast.progress', $language) . "</b>\n\n" .
                    $this->translator->translate('common.progress', $language) . ": $current/$totalUsers\n" .
                    $this->translator->translate('common.successful', $language) . ": $success\n" .
                    $this->translator->translate('common.errors', $language) . ": $failed";
                $this->telegram->sendMessage($chatId, $progressMessage, 'HTML');
            }

            usleep(150000);
        }

        $targetName = match($targetType) {
            'admins' => $this->translator->translate('admin.broadcast.admins_target', $language),
            'users' => $this->translator->translate('admin.broadcast.users_target', $language),
            default => $this->translator->translate('admin.broadcast.all_target', $language)
        };

        $finalMessage = "<b>" . $this->translator->translate('admin.broadcast.completed', $language) . " $targetName!</b>\n\n" .
            $this->translator->translate('common.successful', $language) . ": $success\n" .
            $this->translator->translate('common.errors', $language) . ": $failed\n" .
            $this->translator->translate('common.total', $language) . ": $totalUsers";
        $this->telegram->sendMessage($chatId, $finalMessage, 'HTML');
    }

    private function handleAdminCallback(string $callbackData, string $chatId, int $messageId, string $language): void
    {
        $this->logger->debug("Обробка admin callback: $callbackData");
    }

    private function handleReportCallback(string $callbackData, string $chatId, int $messageId, string $language, string $userId): void
    {
        $session = $this->sessionManager->getReportSession($userId);

        if (!$session) {
            return;
        }

        switch ($callbackData) {
            case 'report_complete':
                $reportId = $this->container->get('report_service')->saveReport($session, $language);
                if ($reportId) {
                    $this->sessionManager->clearReportSession($userId);
                    $this->telegram->sendMessage($chatId, $this->translator->translate('report.success', $language, [$reportId]));
                }
                break;

            case 'report_add_photo':
                $this->telegram->sendMessage($chatId, $this->translator->translate('report.instruction_photo', $language));
                break;

            case 'report_add_video':
                $this->telegram->sendMessage($chatId, $this->translator->translate('report.instruction_video', $language));
                break;

            case 'report_cancel':
                $this->sessionManager->clearReportSession($userId);
                $this->telegram->sendMessage($chatId, $this->translator->translate('report_creation_cancelled', $language));
                break;
        }
    }

    private function handleReportActionCallback(string $callbackData, string $chatId, int $messageId, string $language, string $userId): void
    {
        $this->container->get('callback_handler')->handleReportActionCallback($callbackData, $chatId, $messageId, $language, $userId);
    }

    private function handleReportsPageCallback(string $callbackData, string $chatId, int $messageId, string $language): void
    {
        $parts = explode('_', $callbackData);
        if (count($parts) >= 3 && $parts[0] === 'reports' && $parts[1] === 'page') {
            $page = (int)$parts[2];
            $this->container->get('reports_command')->executeWithPage($chatId, $page, $messageId, $language);
        }
    }

    private function handleDebugCallback(string $callbackData, string $chatId, int $messageId, string $language, string $userId): void
    {
        if (!$this->db->isAdmin($userId)) {
            return;
        }

        if ($this->db->getAdminRank($userId) !== 'owner') {
            return;
        }

        $parts = explode('_', $callbackData);
        if (count($parts) >= 3 && $parts[0] === 'debug' && $parts[1] === 'set') {
            $newLevel = $parts[2];
            $config = $this->container->get('config');
            $config->setLogLevel($newLevel);

            $currentLevel = $config->getLogLevel();
            $keyboard = $this->buildDebugKeyboard($currentLevel, $language);

            $this->telegram->editMessageText($chatId, $messageId,
                $this->translator->translate('admin.debug.current', $language, [$currentLevel]),
                'HTML',
                $keyboard
            );

            $this->telegram->sendMessage($chatId,
                $this->translator->translate('admin.debug.changed', $language, [$newLevel]),
                'HTML'
            );
        }
    }

    private function buildDebugKeyboard(string $currentLevel, string $language): \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup
    {
        $levels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];
        $buttons = [];

        foreach ($levels as $level) {
            $indicator = ($level === $currentLevel) ? '✅ ' : '';
            $buttons[] = ['text' => $indicator . $level, 'callback_data' => "debug_set_$level"];
        }

        return new \TelegramBot\Api\Types\Inline\InlineKeyboardMarkup([$buttons]);
    }

    private function handleProfileCallback(string $callbackData, string $chatId, int $messageId, string $language): void
    {
        $this->logger->debug("Обробка profile callback: $callbackData");
    }

    private function handleCallbackQuery(\TelegramBot\Api\Types\CallbackQuery $callbackQuery, string $language): void
    {
        $callbackData = $callbackQuery->getData();
        $chatId = (string)$callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $callbackQueryId = $callbackQuery->getId();
        $userId = (string)$callbackQuery->getFrom()->getId();

        $from = $callbackQuery->getFrom();
        if ($from) {
            $language = $this->translator->detectLanguage(
                [
                    'id' => $from->getId(),
                    'language_code' => $from->getLanguageCode()
                ],
                $this->db,
                $this->telegram
            );
        }

        $parts = explode('_', $callbackData);
        $type = $parts[0];

        $hasSession = false;
        if ($type === 'broadcast') {
            $hasSession = $this->sessionManager->hasBroadcastSession($userId);
        } elseif ($type === 'report' || $type === 'accept' || $type === 'reject') {
            $hasSession = $this->sessionManager->hasReportSession($userId) ||
                          $this->sessionManager->hasAdminActionSession($userId);
        } elseif ($type === 'debug') {
            $hasSession = true;
        }

        if (!$hasSession) {
            $this->telegram->answerCallbackQuery($callbackQueryId, '');
            return;
        }

        $answerText = $this->getCallbackAnswer($callbackData, $language);
        $this->telegram->answerCallbackQuery($callbackQueryId, $answerText);

        switch ($type) {
            case 'broadcast':
                $this->handleBroadcastCallback($callbackData, $chatId, $messageId, $language, $userId);
                break;
            case 'report':
                $this->handleReportCallback($callbackData, $chatId, $messageId, $language, $userId);
                break;
            case 'accept':
            case 'reject':
                $this->handleReportActionCallback($callbackData, $chatId, $messageId, $language, $userId);
                break;
            case 'reports':
                $this->handleReportsPageCallback($callbackData, $chatId, $messageId, $language);
                break;
            case 'admin':
                $this->handleAdminCallback($callbackData, $chatId, $messageId, $language);
                break;
            case 'profile':
                $this->handleProfileCallback($callbackData, $chatId, $messageId, $language);
                break;
            case 'debug':
                $this->handleDebugCallback($callbackData, $chatId, $messageId, $language, $userId);
                break;
            default:
                $this->logger->warning("Невідомий тип callback: $type");
        }
    }

    private function getCallbackAnswer(string $callbackData, string $language): string
    {
        $parts = explode('_', $callbackData);
        $type = $parts[0];

        switch ($type) {
            case 'broadcast':
                return $this->getBroadcastCallbackAnswer($callbackData, $language);
            case 'report':
                return $this->getReportCallbackAnswer($callbackData, $language);
            case 'accept':
                return $this->translator->translate('report.callbacks.accepted', $language);
            case 'reject':
                return $this->translator->translate('report.callbacks.rejected', $language);
            case 'admin':
                return $this->translator->translate('callbacks.admin_action', $language);
            case 'profile':
                return $this->translator->translate('callbacks.profile_updated', $language);
            default:
                return $this->translator->translate('callbacks.processed', $language);
        }
    }

    private function getBroadcastCallbackAnswer(string $callbackData, string $language): string
    {
        switch ($callbackData) {
            case 'broadcast_all':
                return $this->translator->translate('callbacks.broadcast_all', $language);
            case 'broadcast_admins':
                return $this->translator->translate('callbacks.broadcast_admins', $language);
            case 'broadcast_users':
                return $this->translator->translate('callbacks.broadcast_users', $language);
            case 'broadcast_cancel':
            case 'broadcast_cancel_final':
                return $this->translator->translate('callbacks.broadcast_cancelled', $language);
            case 'broadcast_edit':
                return $this->translator->translate('callbacks.broadcast_editing', $language);
            case 'broadcast_confirm':
                return $this->translator->translate('callbacks.broadcast_confirm', $language);
            case 'broadcast_add_photo':
                return $this->translator->translate('callbacks.broadcast_photo', $language);
            case 'broadcast_add_video':
                return $this->translator->translate('callbacks.broadcast_video', $language);
            case 'broadcast_clear_media':
                return $this->translator->translate('callbacks.broadcast_clear', $language);
            case 'broadcast_lang_next':
                return $this->translator->translate('callbacks.language_selected', $language);
            default:
                return $this->translator->translate('callbacks.processed', $language);
        }
    }

    private function getReportCallbackAnswer(string $callbackData, string $language): string
    {
        switch ($callbackData) {
            case 'report_complete':
                return $this->translator->translate('report.callbacks.complete', $language);
            case 'report_cancel':
                return $this->translator->translate('report.callbacks.cancel', $language);
            case 'report_add_photo':
                return $this->translator->translate('report.callbacks.add_photo', $language);
            case 'report_add_video':
                return $this->translator->translate('report.callbacks.add_video', $language);
            default:
                return $this->translator->translate('callbacks.processed', $language);
        }
    }

    private function handleBroadcastSession(\TelegramBot\Api\Types\Message $message, string $language): void
    {
        $chatId = (string)$message->getChat()->getId();
        $userId = (string)$message->getFrom()->getId();
        $session = $this->sessionManager->getBroadcastSession($userId);

        if (!$session) {
            return;
        }

        if ($message->getText() === '/cancel') {
            $this->sessionManager->clearBroadcastSession($userId);
            $this->telegram->sendMessage($chatId, $this->translator->translate('admin.broadcast.cancelled', $language));
            return;
        }

        $awaitingMediaType = $session['awaiting_media_type'] ?? null;
        $step = $session['step'] ?? 'type';

        if ($awaitingMediaType === 'photo') {
            $photo = $message->getPhoto();
            if ($photo) {
                $fileId = $photo[count($photo) - 1]->getFileId();
                $session['media'] = $session['media'] ?? [];
                $session['media'][] = ['type' => 'photo', 'file_id' => $fileId];
                unset($session['awaiting_media_type']);
                $this->sessionManager->setBroadcastSession($userId, $session);

                $mediaCount = count($session['media']);
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.successfully_added', $language) . "</b> " .
                    $this->translator->translate('common.photo', $language) . "!\n" .
                    $this->translator->translate('common.total_media_count', $language, [$mediaCount]));
                $this->showMediaControls($chatId, $session, $language);
                return;
            } else {
                $this->telegram->sendMessage($chatId, $this->translator->translate('admin.broadcast.send_photo', $language));
                return;
            }
        }

        if ($awaitingMediaType === 'video') {
            $video = $message->getVideo();
            if ($video) {
                $fileId = $video->getFileId();
                $session['media'] = $session['media'] ?? [];
                $session['media'][] = ['type' => 'video', 'file_id' => $fileId];
                unset($session['awaiting_media_type']);
                $this->sessionManager->setBroadcastSession($userId, $session);

                $mediaCount = count($session['media']);
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.successfully_added', $language) . "</b> " .
                    $this->translator->translate('common.video', $language) . "!\n" .
                    $this->translator->translate('common.total_media_count', $language, [$mediaCount]));
                $this->showMediaControls($chatId, $session, $language);
                return;
            } else {
                $this->telegram->sendMessage($chatId, $this->translator->translate('admin.broadcast.send_video', $language));
                return;
            }
        }

        if ($step === 'message' || empty($session['message_text'])) {
            $text = $message->getText() ?? '';
            if (!empty($text)) {
                $session['message_text'] = $text;
                $this->sessionManager->setBroadcastSession($userId, $session);
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.text_saved', $language) . "</b>\n\n" .
                    $this->translator->translate('common.add_more_media_or_finish', $language));
                $this->showMediaControls($chatId, $session, $language);
            }
        }
    }

    private function prepareMediaForSending(array $media, string $messageText): \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia
    {
        $inputMedia = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
        $caption = !empty($messageText) ? $messageText : null;

        foreach ($media as $index => $item) {
            if ($item['type'] === 'photo') {
                $photo = new \TelegramBot\Api\Types\InputMedia\InputMediaPhoto();
                $photo->setMedia($item['file_id']);
                $photo->setType('photo');
                if ($index === 0 && $caption) {
                    $photo->setCaption($caption);
                    $photo->setParseMode('HTML');
                }
                $inputMedia->addItem($photo);
            } elseif ($item['type'] === 'video') {
                $video = new \TelegramBot\Api\Types\InputMedia\InputMediaVideo();
                $video->setMedia($item['file_id']);
                $video->setType('video');
                if ($index === 0 && $caption) {
                    $video->setCaption($caption);
                    $video->setParseMode('HTML');
                }
                $inputMedia->addItem($video);
            }
        }

        return $inputMedia;
    }

    private function hasPermission(string $requiredRank, ?string $currentRank): bool
    {
        $ranks = ['owner' => 3, 'admin' => 2, 'moderator' => 1];
        $currentLevel = $ranks[$currentRank] ?? 0;
        $requiredLevel = $ranks[$requiredRank] ?? 0;

        return $currentLevel >= $requiredLevel;
    }

    public function getConfig(): Config
    {
        return $this->config;
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getDb(): DatabaseService
    {
        return $this->db;
    }

    public function getTelegram(): TelegramService
    {
        return $this->telegram;
    }

    public function getTranslator(): Translator
    {
        return $this->translator;
    }

    public function getSessionManager(): SessionManager
    {
        return $this->sessionManager;
    }
}
