<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class RejectReportCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/reject';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.reject_report', $language);
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
        $parts = explode(' ', $text);
        $userId = $this->getUserId($message);

        if (count($parts) < 2) {
            $this->reply($chatId, $this->t('report.process.reject_usage', $language));
            return;
        }

        $reportId = (int)$parts[1];
        $reason = implode(' ', array_slice($parts, 2));

        $report = $this->db->getReportById($reportId);

        if (!$report) {
            $this->reply($chatId, $this->t('report.not_found', $language, [$reportId]));
            return;
        }

        if ($report['status'] !== 'pending') {
            $this->reply($chatId, $this->t('report.process.already_processed', $language));
            return;
        }

        $result = $this->db->updateReportStatus($reportId, 'rejected', $reason, $userId);

        if ($result) {
            $this->reply($chatId, $this->t('report.process.success_reject', $language, [$reportId]));
            $this->logger->info("Репорт #{$reportId} відхилено адміном $userId");
            
            // Notify user
            try {
                $statusText = $this->t('report.rejected', $language);
                $notifyText = $this->t('report.status_changed', $language, [$reportId, $statusText]);
                if (!empty($reason)) {
                    $notifyText .= "\n" . $this->t('report.admin_comment', $language, [$reason]);
                }
                $this->telegram->sendMessage($report['user_id'], $notifyText);
            } catch (\Exception $e) {
                // Ignore
            }
        } else {
            $this->reply($chatId, $this->t('report.process.error', $language));
        }
    }
}
