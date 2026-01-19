<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class ReportsCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/reports';
    }

    public function getDescription(): string
    {
        return 'Список активних репортів';
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
        $reports = $this->db->getAllReports('pending');

        if (empty($reports)) {
            $this->reply($chatId, $this->t('report.empty', $language));
            return;
        }

        $messageText = "<b>" . $this->t('report.pending', $language) . ":</b>\n\n";

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

        $messageText .= $this->t('report.hint_use_id', $language);
        $this->reply($chatId, $messageText);
    }
}
