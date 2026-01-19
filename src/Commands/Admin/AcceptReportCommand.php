<?php

namespace App\Commands\Admin;

use App\Commands\BaseCommand;
use TelegramBot\Api\Types\Message;

class AcceptReportCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/accept';
    }

    public function getDescription(string $language = 'uk'): string
    {
        return $this->translator->translate('commands.descriptions.accept_report', $language);
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
            $this->reply($chatId, $this->t('report.process.accept_usage', $language));
            return;
        }

        $reportId = (int)$parts[1];
        $comment = implode(' ', array_slice($parts, 2));

        $report = $this->db->getReportById($reportId);

        if (!$report) {
            $this->reply($chatId, $this->t('report.not_found', $language, [$reportId]));
            return;
        }

        if ($report['status'] !== 'pending') {
            $this->reply($chatId, $this->t('report.process.already_processed', $language));
            return;
        }

        $result = $this->db->updateReportStatus($reportId, 'accepted', $comment, $userId);

        if ($result) {
            $this->reply($chatId, $this->t('report.process.success_accept', $language, [$reportId]));
            $this->logger->info("Репорт #{$reportId} прийнято адміном $userId");
            
            // Notify user
            try {
                if (!empty($report['user_id'])) {
                    $statusText = $this->t('report.accepted', $language);
                    $notifyText = $this->t('report.status_changed', $language, [$reportId, $statusText]);
                    if (!empty($comment)) {
                        $notifyText .= "\n" . $this->t('report.admin_comment', $language, [$comment]);
                    }
                    $this->telegram->sendMessage($report['user_id'], $notifyText);
                }
            } catch (\Exception $e) {
                // Ignore
            }
        } else {
            $this->reply($chatId, $this->t('report.process.error', $language));
        }
    }
}
