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

        $this->telegram->answerCallbackQuery($callbackQueryId, "OK");

        $parts = explode('_', $callbackData);
        $type = $parts[0];

        switch ($type) {
            case 'report':
                $this->handleReportCallback($callbackData, $chatId, $language);
                break;
            case 'accept':
            case 'reject':
                $this->handleReportActionCallback($callbackData, $chatId, $messageId, $language);
                break;
            // Add other types as needed
        }
    }

    private function handleReportCallback(string $callbackData, string $chatId, string $language): void
    {
        $session = $this->sessionManager->getReportSession($chatId);
        if (!$session) return;

        switch ($callbackData) {
            case 'report_complete':
                $reportId = $this->reportService->saveReport($session, $language);
                if ($reportId) {
                    $this->sessionManager->clearReportSession($chatId);
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
                $this->sessionManager->clearReportSession($chatId);
                $this->telegram->sendMessage($chatId, $this->translator->translate('report_creation_cancelled', $language));
                break;
        }
    }

    private function handleReportActionCallback(string $callbackData, string $chatId, int $messageId, string $language): void
    {
        $parts = explode('_', $callbackData);
        $action = $parts[0]; // accept or reject
        $reportId = (int)$parts[1];

        $report = $this->db->getReportById($reportId);
        if (!$report || $report['status'] !== 'pending') return;

        $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';
        $this->db->updateReportStatus($reportId, $newStatus);

        $this->telegram->editMessageText($chatId, $messageId, 
            $this->translator->translate("report.status_updated", $language, [$reportId, $this->translator->translate("report.$newStatus", $language)])
        );
        
        // Notify reporter
        $this->telegram->sendMessage($report['user_id'], 
            $this->translator->translate("report.notification_status_changed", $language, [$reportId, $this->translator->translate("report.$newStatus", $language)])
        );
    }
}
