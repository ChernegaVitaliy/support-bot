<?php

namespace App\Handlers;

use App\Services\Logger;
use App\Services\TelegramService;
use App\Services\DatabaseService;
use App\Services\Translator;
use App\Services\SessionManager;
use App\Services\ReportService;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class SessionHandler
{
    private Logger $logger;
    private TelegramService $telegram;
    private DatabaseService $db;
    private Translator $translator;
    private SessionManager $sessionManager;
    private ReportService $reportService;

    public function __construct(
        Logger $logger,
        TelegramService $telegram,
        DatabaseService $db,
        Translator $translator,
        SessionManager $sessionManager,
        ReportService $reportService
    )
    {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->db = $db;
        $this->translator = $translator;
        $this->sessionManager = $sessionManager;
        $this->reportService = $reportService;
    }

    public function handleReportSession(Message $message, string $language): void
    {
        $chatId = (string)$message->getChat()->getId();
        $userId = (string)$message->getFrom()->getId();
        $text = $message->getText() ?? '';
        $session = $this->sessionManager->getReportSession($userId);

        if ($text === '/cancel') {
            $this->sessionManager->clearReportSession($userId);
            $this->telegram->sendMessage($chatId, $this->translator->translate('report_creation_cancelled', $language));
            return;
        }

        if (!$session) return;

        switch ($session['step']) {
            case 1:
                $this->handleReportStep1($chatId, $userId, $text, $language);
                break;
            case 2:
                $this->handleReportStep2($chatId, $userId, $text, $language);
                break;
            case 3:
                $this->handleReportStep3($chatId, $userId, $text, $language);
                break;
            case 4:
                $this->handleReportStep4($message, $userId, $language);
                break;
        }
    }

    private function handleReportStep1(string $chatId, string $userId, string $text, string $language): void
    {
        if (empty(trim($text))) {
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.invalid_format', $language));
            return;
        }

        $session = $this->sessionManager->getReportSession($userId);
        $session['data']['reporter_nick'] = trim($text);
        $session['step'] = 2;
        $this->sessionManager->setReportSession($userId, $session);

        $replyText = "<b>" . $this->translator->translate('report.step_2', $language) . "</b>\n" .
            "<code>" . $this->translator->translate('report.example', $language, ['Danylchik123']) . "</code>\n\n" .
            $this->translator->translate('report.cancel_hint', $language);

        $this->telegram->sendMessage($chatId, $replyText, 'HTML');
    }

    private function handleReportStep2(string $chatId, string $userId, string $text, string $language): void
    {
        if (empty(trim($text))) {
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.invalid_format', $language));
            return;
        }

        $session = $this->sessionManager->getReportSession($userId);
        $session['data']['reported_nick'] = trim($text);
        $session['step'] = 3;
        $this->sessionManager->setReportSession($userId, $session);

        $reasons = $this->translator->translate('report.reasons_list', $language);

        $replyText = "<b>" . $this->translator->translate('report.step_3', $language) . "</b>\n\n" .
            $reasons . "\n\n" .
            "<code>" . $this->translator->translate('report.example', $language, ['1.1']) . "</code>\n\n" .
            $this->translator->translate('report.cancel_hint', $language);

        $this->telegram->sendMessage($chatId, $replyText, 'HTML');
    }

    private function handleReportStep3(string $chatId, string $userId, string $text, string $language): void
    {
        if (empty(trim($text))) {
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.invalid_format', $language));
            return;
        }

        $session = $this->sessionManager->getReportSession($userId);
        $session['data']['reason'] = trim($text);
        $session['step'] = 4;
        $this->sessionManager->setReportSession($userId, $session);

        $replyText = "<b>" . $this->translator->translate('report.step_4', $language) . "</b>\n" .
            $this->translator->translate('report.media_hint', $language) . "\n\n" .
            $this->translator->translate('report.cancel_hint', $language);

        $this->telegram->sendMessage($chatId, $replyText, 'HTML');
        $this->showReportCompletionButtons($chatId, $session, $language);
    }

    private function handleReportStep4(Message $message, string $userId, string $language): void
    {
        $chatId = (string)$message->getChat()->getId();
        $text = $message->getText();
        $session = $this->sessionManager->getReportSession($userId);
        
        if ($text && in_array(strtolower($text), ['готово', 'done', 'завершити', 'skip'])) {
            $this->completeReport($chatId, $userId, $session, $language);
            return;
        }

        $mediaAdded = false;

        if ($message->getPhoto()) {
            $photos = $message->getPhoto();
            $fileId = end($photos)->getFileId();
            $session['data']['media_files'][] = ['type' => 'photo', 'file_id' => $fileId];
            $mediaAdded = true;
        }

        if ($message->getVideo()) {
            $fileId = $message->getVideo()->getFileId();
            $session['data']['media_files'][] = ['type' => 'video', 'file_id' => $fileId];
            $mediaAdded = true;
        }

        if (!$mediaAdded && $text && !empty(trim($text))) {
            $session['data']['text_proof'] = trim($text);
            $mediaAdded = true;
        }

        if ($mediaAdded) {
            $this->sessionManager->setReportSession($userId, $session);
        }

        $this->showReportCompletionButtons($chatId, $session, $language);
    }

    private function showReportCompletionButtons(string $chatId, array $session, string $language): void
    {
        $mediaCount = count($session['data']['media_files'] ?? []);
        $hasTextProof = !empty($session['data']['text_proof']);

        $message = "<b>" . $this->translator->translate('report.title', $language) . "</b>\n\n";

        if ($mediaCount > 0) {
            $message .= $this->translator->translate('report.media_added', $language, [$mediaCount]);
            if ($hasTextProof) {
                $message .= "\n" . $this->translator->translate('common.success', $language);
            }
            $message .= "\n\n";
        } elseif ($hasTextProof) {
            $message .= $this->translator->translate('common.success', $language) . "\n\n";
        }

        $message .= $this->translator->translate('report.cancel_hint', $language);

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => $this->translator->translate('common.next', $language), 'callback_data' => 'report_complete'],
                ['text' => $this->translator->translate('report.buttons.add_photo', $language), 'callback_data' => 'report_add_photo']
            ],
            [
                ['text' => $this->translator->translate('report.buttons.add_video', $language), 'callback_data' => 'report_add_video'],
                ['text' => $this->translator->translate('common.cancel', $language), 'callback_data' => 'report_cancel']
            ]
        ]);

        $this->telegram->sendMessage($chatId, $message, 'HTML', false, null, $keyboard);
    }

    private function completeReport(string $chatId, string $userId, array $session, string $language): void
    {
        $reportId = $this->reportService->saveReport($session, $language);

        if ($reportId) {
            $this->sessionManager->clearReportSession($userId);
            $this->telegram->sendMessage($chatId, $this->translator->translate('report.success', $language, [$reportId]));
        } else {
            $this->telegram->sendMessage($chatId, $this->translator->translate('errors.save_error', $language));
        }
    }

    public function handleAdminActionSession(Message $message, string $language): void
    {
        $chatId = (string)$message->getChat()->getId();
        $userId = (string)$message->getFrom()->getId();
        $text = $message->getText() ?? '';

        if ($text === '/cancel') {
            $this->sessionManager->clearAdminActionSession($userId);
            $this->telegram->sendMessage($chatId, $this->translator->translate('report.process.cancel', $language));
            return;
        }

        $session = $this->sessionManager->getAdminActionSession($userId);
        if (!$session || $session['type'] !== 'report_comment') return;

        $reportId = $session['report_id'];
        $action = $session['action'];
        $messageId = $session['message_id'];
        $comment = trim($text);

        $report = $this->db->getReportById($reportId);
        if (!$report || $report['status'] !== 'pending') {
            $this->sessionManager->clearAdminActionSession($userId);
            return;
        }

        $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';
        $this->db->updateReportStatus($reportId, $newStatus, $comment, $chatId);

        $statusText = $this->translator->translate("report.$newStatus", $language);
        $updatedText = $this->translator->translate("report.status_updated", $language, [$reportId, $statusText]);
        if (!empty($comment)) {
            $updatedText .= "\n" . $this->translator->translate('report.admin_comment', $language, [$comment]);
        }

        $this->telegram->editMessageText($chatId, $messageId, $updatedText);

        if (!empty($report['user_id'])) {
            $notifyText = $this->translator->translate("report.notification_status_changed", $language, [$reportId, $statusText]);
            if (!empty($comment)) {
                $notifyText .= "\n" . $this->translator->translate('report.admin_comment', $language, [$comment]);
            }
            $this->telegram->sendMessage($report['user_id'], $notifyText);
        }

        $this->sessionManager->clearAdminActionSession($userId);
        $this->telegram->sendMessage($chatId, $this->translator->translate("report.process.complete", $language));
    }
}
