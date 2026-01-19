<?php

namespace App\Services;

use App\Services\Logger;
use App\Services\TelegramService;
use App\Services\DatabaseService;
use App\Services\Translator;

class BroadcastService
{
    private Logger $logger;
    private TelegramService $telegram;
    private DatabaseService $db;
    private Translator $translator;

    public function __construct(
        Logger $logger,
        TelegramService $telegram,
        DatabaseService $db,
        Translator $translator
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->db = $db;
        $this->translator = $translator;
    }

    public function executeBroadcast(string $chatId, array $session, string $language): void
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
            $this->telegram->sendMessage($chatId, "❌ <b>" . $this->translator->translate('broadcast_error', $language) . "</b>\n\n" . $this->translator->translate('broadcast_no_users', $language));
            return;
        }

        $this->telegram->sendMessage($chatId, "📊 <b>" . $this->translator->translate('broadcast_progress', $language) . "</b>\n\n" . $this->translator->translate('progress', $language) . ": 0/".$totalUsers);

        $success = 0;
        $failed = 0;
        $current = 0;

        foreach ($users as $user) {
            $current++;
            $userChatId = $user['user_id'];

            try {
                if (!empty($session['media'])) {
                    $inputMedia = $this->prepareMediaForSending($session['media'], $session['message_text'] ?? '');
                    $this->telegram->sendMediaGroup($userChatId, $inputMedia);
                } else {
                    $this->telegram->sendMessage($userChatId, $session['message_text'], 'HTML');
                }
                $success++;
            } catch (\Exception $e) {
                $failed++;
            }

            if ($current % 10 === 0 || $current === $totalUsers) {
                // Update progress occasionally
            }

            usleep(50000); // 20 messages per second limit
        }

        $finalMessage = "🎉 <b>" . $this->translator->translate('broadcast_completed', $language) . "</b>\n\n✅ " . 
            $this->translator->translate('successful', $language) . ": $success\n❌ " . 
            $this->translator->translate('errors', $language) . ": $failed\n👥 " . 
            $this->translator->translate('total', $language) . ": $totalUsers";
        $this->telegram->sendMessage($chatId, $finalMessage, 'HTML');
    }

    private function prepareMediaForSending(array $media, string $messageText): \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia
    {
        $inputMedia = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
        $caption = !empty($messageText) ? $messageText : null;

        foreach ($media as $index => $item) {
            if ($item['type'] === 'photo') {
                $m = new \TelegramBot\Api\Types\InputMedia\InputMediaPhoto();
                $m->setMedia($item['file_id']);
            } else {
                $m = new \TelegramBot\Api\Types\InputMedia\InputMediaVideo();
                $m->setMedia($item['file_id']);
            }
            
            if ($index === 0 && $caption) {
                $m->setCaption($caption);
                $m->setParseMode('HTML');
            }
            $inputMedia->addItem($m);
        }

        return $inputMedia;
    }
}
