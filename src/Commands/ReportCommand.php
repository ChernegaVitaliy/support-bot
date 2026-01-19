<?php

namespace App\Commands;

use App\Services\SessionManager;
use App\Services\ReportService;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class ReportCommand extends BaseCommand
{
    private ReportService $reportService;

    public function __construct(\App\Console\ServiceContainer $container)
    {
        parent::__construct($container);
        $this->reportService = $container->get('report_service');
    }

    public function getName(): string
    {
        return '/report';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.report', $language);
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);
        $text = $message->getText() ?? '';
        $parts = explode(' ', $text);

        // Check if it's a view command (e.g., /report 123)
        if (count($parts) > 1 && is_numeric($parts[1])) {
            // Check admin permissions
            if (!$this->isAdmin($userId)) {
                $this->reply($chatId, $this->t('errors.not_admin', $language));
                return;
            }

            // Check rank (moderator+)
            $currentRank = $this->getCurrentAdminRank($userId);
            if (!$this->hasPermission('moderator', $currentRank)) {
                $this->reply($chatId, $this->t('errors.no_permission', $language));
                return;
            }

            $reportId = (int)$parts[1];
            $this->reportService->showReportDetails($chatId, $reportId, $language);
            return;
        }

        // Default logic: Create new report
        $from = $message->getFrom();

        $this->sessionManager->setReportSession($chatId, [
            'step' => 1,
            'user_id' => (string)$from->getId(),
            'data' => [
                'media_files' => [],
                'text_proof' => ''
            ],
            'lang' => $language
        ]);

        $replyText = "<b>" . $this->t('report.title', $language) . "</b>\n\n" .
            $this->t('report.step_1', $language) . "\n" .
            "<code>" . $this->t('report.example', $language, ['VitalikBee11']) . "</code>\n\n" .
            $this->t('report.cancel_hint', $language);

        $this->reply($chatId, $replyText);
    }
}
