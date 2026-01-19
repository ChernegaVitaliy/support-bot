<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class ReportsCommand extends BaseCommand
{
    private const REPORTS_PER_PAGE = 10;

    public function getName(): string
    {
        return '/reports';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.reports', $language);
    }

    public function isAdminOnly(): bool
    {
        return true;
    }

    public function getRequiredRank(): string
    {
        return 'moderator';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $text = $message->getText() ?? '';

        if (strpos($text, '/reports ') === 0) {
            $parts = explode(' ', $text);
            $page = isset($parts[1]) ? (int)$parts[1] : 1;
            $this->showReportsPage($chatId, $page, $language);
            return;
        }

        $this->showReportsPage($chatId, 1, $language);
    }

    private function showReportsPage(string $chatId, int $page, string $language, ?int $editMessageId = null): void
    {
        $offset = ($page - 1) * self::REPORTS_PER_PAGE;
        $reports = $this->db->getReportsPaginated('pending', self::REPORTS_PER_PAGE, $offset);
        $totalCount = $this->db->getReportsCount('pending');
        $totalPages = (int)ceil($totalCount / self::REPORTS_PER_PAGE);

        if (empty($reports) && $page === 1) {
            $this->reply($chatId, $this->t('report.empty', $language));
            return;
        }

        if (empty($reports)) {
            $this->reply($chatId, $this->t('report.empty', $language));
            return;
        }

        $messageText = "<b>" . $this->t('report.pending', $language) . " (Сторінка $page/$totalPages):</b>\n\n";

        foreach ($reports as $report) {
            $statusEmoji = match($report['status']) {
                'pending' => '⏳',
                'accepted' => '✅',
                'rejected' => '❌',
                default => '❓'
            };

            $messageText .= "{$statusEmoji} <b>#{$report['id']}</b>\n";
            $messageText .= $this->t('report.reporter', $language) . ": <code>{$report['reporter_nick']}</code>\n";
            $messageText .= $this->t('report.violator', $language) . ": <code>{$report['reported_nick']}</code>\n";
            $messageText .= $this->t('common.reason', $language) . ": <code>{$report['reason']}</code>\n";
            $messageText .= $this->t('common.date', $language) . ": " . date("d.m.Y H:i", strtotime($report['created_at'])) . "\n";

            if (!empty($report['proof'])) {
                if ($report['proof_type'] === 'text') {
                    $messageText .= $this->t('report.proof', $language) . ": {$report['proof']}\n";
                } else {
                    $mediaCount = 0;
                    $decoded = json_decode($report['proof'], true);
                    if (is_array($decoded)) {
                        $mediaCount = count($decoded);
                    }
                    $messageText .= $this->t('report.media_added', $language, [$mediaCount]) . "\n";
                }
            }

            $messageText .= "\n🔍 <code>/report {$report['id']}</code>\n\n";
        }

        $messageText .= $this->t('report.view_usage_hint', $language);

        $keyboard = $this->buildPaginationKeyboard($page, $totalPages, $language);

        if ($editMessageId) {
            $this->telegram->editMessageText($chatId, $editMessageId, $messageText, 'HTML', $keyboard);
        } else {
            $this->telegram->sendMessage($chatId, $messageText, 'HTML', false, null, $keyboard);
        }
    }

    public function executeWithPage(string $chatId, int $page, int $messageId, string $language): void
    {
        $this->showReportsPage($chatId, $page, $language, $messageId);
    }

    private function buildPaginationKeyboard(int $page, int $totalPages, string $language): ?InlineKeyboardMarkup
    {
        $buttons = [];

        if ($totalPages > 1) {
            $row = [];

            if ($page > 1) {
                $row[] = ['text' => $this->t('common.previous', $language), 'callback_data' => "reports_page_" . ($page - 1)];
            }

            $row[] = ['text' => "$page/$totalPages", 'callback_data' => 'reports_current'];

            if ($page < $totalPages) {
                $row[] = ['text' => $this->t('common.next', $language), 'callback_data' => "reports_page_" . ($page + 1)];
            }

            $buttons[] = $row;
        }

        if (empty($buttons)) {
            return null;
        }

        return new InlineKeyboardMarkup($buttons);
    }
}
