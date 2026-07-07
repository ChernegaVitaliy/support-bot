<?php

namespace App\Handlers;

use App\Services\Logger;
use App\Services\TelegramService;
use App\Services\DatabaseService;
use App\Services\Translator;
use App\Services\SessionManager;
use App\Services\ReportService;
use App\Services\BroadcastService;
use TelegramBot\Api\Types\CallbackQuery;

class CallbackHandler
{
    private Logger $logger;
    private TelegramService $telegram;
    private DatabaseService $db;
    private Translator $translator;
    private SessionManager $sessionManager;
    private ReportService $reportService;
    private BroadcastService $broadcastService;

    public function __construct(
        Logger $logger,
        TelegramService $telegram,
        DatabaseService $db,
        Translator $translator,
        SessionManager $sessionManager,
        ReportService $reportService,
        BroadcastService $broadcastService
    ) {
        $this->logger = $logger;
        $this->telegram = $telegram;
        $this->db = $db;
        $this->translator = $translator;
        $this->sessionManager = $sessionManager;
        $this->reportService = $reportService;
        $this->broadcastService = $broadcastService;
    }

    public function handle(CallbackQuery $callbackQuery, string $language): void
    {
        $callbackData = $callbackQuery->getData();
        $chatId = (string)$callbackQuery->getMessage()->getChat()->getId();
        $messageId = $callbackQuery->getMessage()->getMessageId();
        $callbackQueryId = $callbackQuery->getId();
        $userId = (string)$callbackQuery->getFrom()->getId();

        $this->telegram->answerCallbackQuery($callbackQueryId, "OK");

        $parts = explode('_', $callbackData);
        $type = $parts[0];

        switch ($type) {
            case 'report':
                $this->handleReportCallback($callbackData, $chatId, $userId, $language);
                break;
            case 'accept':
            case 'reject':
                $this->handleReportActionCallback($callbackData, $chatId, $messageId, $language, $userId);
                break;
            // Add other types as needed
        }
    }

    public function handleReportCallback(string $callbackData, string $chatId, string $userId, string $language): void
    {
        $session = $this->sessionManager->getReportSession($userId);
        if (!$session) return;

        switch ($callbackData) {
            case 'report_complete':
                $reportId = $this->reportService->saveReport($session, $language);
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

    public function handleReportActionCallback(string $callbackData, string $chatId, int $messageId, string $language, string $userId): void
    {
        $session = $this->sessionManager->getAdminActionSession($userId);

        if ($session && isset($session['type']) && $session['type'] === 'report_comment') {
            $parts = explode('_', $callbackData);
            $action = $parts[0];
            $reportId = (int)$parts[1];
            $comment = trim($callbackData);

            $report = $this->db->getReportById($reportId);
            if (!$report || $report['status'] !== 'pending') {
                $this->sessionManager->clearAdminActionSession($userId);
                return;
            }

            $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';
            $this->db->updateReportStatus($reportId, $newStatus, $comment, $chatId);

            $this->telegram->editMessageText($chatId, $messageId,
                $this->translator->translate("report.status_updated", $language, [$reportId, $this->translator->translate("report.$newStatus", $language)])
            );

            if (!empty($report['user_id'])) {
                $statusText = $this->translator->translate("report.$newStatus", $language);
                $notifyText = $this->translator->translate("report.notification_status_changed", $language, [$reportId, $statusText]);
                if (!empty($comment)) {
                    $notifyText .= "\n" . $this->translator->translate('report.admin_comment', $language, [$comment]);
                }
                $this->telegram->sendMessage($report['user_id'], $notifyText);
            }

            $this->sessionManager->clearAdminActionSession($userId);
            return;
        }

        $parts = explode('_', $callbackData);
        $action = $parts[0];
        $reportId = (int)$parts[1];

        $report = $this->db->getReportById($reportId);
        if (!$report || $report['status'] !== 'pending') return;

        $this->sessionManager->setAdminActionSession($userId, [
            'type' => 'report_comment',
            'report_id' => $reportId,
            'action' => $action,
            'message_id' => $messageId
        ]);

        $actionText = ($action === 'accept')
            ? $this->translator->translate('report.buttons.accept', $language)
            : $this->translator->translate('report.buttons.reject', $language);

        $this->telegram->sendMessage($chatId,
            $this->translator->translate('report.process.enter_comment', $language) . "\n\n" .
            "<b>$actionText</b> #$reportId"
        );
    }
}
