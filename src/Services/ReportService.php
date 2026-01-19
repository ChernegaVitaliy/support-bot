<?php

namespace App\Services;

use App\Services\Logger;
use App\Services\TelegramService;
use App\Services\DatabaseService;
use App\Services\Translator;
use TelegramBot\Api\Types\Inline\InlineKeyboardMarkup;

class ReportService
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

    public function showReportDetails(string $chatId, int $reportId, string $language): void
    {
        $report = $this->db->getReportById($reportId);

        if (!$report) {
            $this->telegram->sendMessage($chatId, $this->translator->translate('report.not_found', $language, [$reportId]));
            return;
        }

        $statusEmoji = match($report['status']) {
            'pending' => '⏳',
            'accepted' => '✅',
            'rejected' => '❌',
            default => '❓'
        };

        $caption = "{$statusEmoji} <b>#{$report['id']}</b>\n\n";
        $caption .= "<b>" . $this->translator->translate('report.reporter', $language) . ":</b> <code>{$report['reporter_nick']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('report.violator', $language) . ":</b> <code>{$report['reported_nick']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('common.reason', $language) . ":</b> <code>{$report['reason']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('common.date', $language) . ":</b> " . date("d.m.Y H:i", strtotime($report['created_at'])) . "\n";
        $caption .= "<b>" . $this->translator->translate('common.status', $language) . ":</b> " . $this->getStatusText($report['status'], $language) . "\n";

        if (!empty($report['admin_notes'])) {
            $caption .= "<b>" . $this->translator->translate('report.admin_comment', $language, [$report['admin_notes']]) . "</b>\n";
        }

        if (!empty($report['proof']) && $report['proof_type'] === 'text') {
            $caption .= "<b>" . $this->translator->translate('report.proof', $language) . ":</b> {$report['proof']}\n";
        }

        $keyboard = null;
        if ($report['status'] === 'pending') {
            $keyboard = new InlineKeyboardMarkup([
                [
                    ['text' => $this->translator->translate('report.buttons.accept', $language), 'callback_data' => "accept_{$reportId}_0"],
                    ['text' => $this->translator->translate('report.buttons.reject', $language), 'callback_data' => "reject_{$reportId}_0"]
                ]
            ]);
        }

        $mediaFiles = [];
        if ($report['proof_type'] === 'multiple_media' || $report['proof_type'] === 'media') {
            $decoded = json_decode($report['proof'], true);
            if (is_array($decoded)) {
                $mediaFiles = $decoded;
            }
        }

        if (count($mediaFiles) === 1) {
            $item = $mediaFiles[0];
            if ($item['type'] === 'photo') {
                $this->telegram->sendPhoto($chatId, $item['file_id'], $caption, 'HTML', $keyboard);
            } else {
                $this->telegram->sendVideo($chatId, $item['file_id'], $caption, 'HTML', $keyboard);
            }
        } elseif (count($mediaFiles) > 1) {
            $inputMedia = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
            foreach ($mediaFiles as $index => $item) {
                if ($item['type'] === 'photo') {
                    $media = new \TelegramBot\Api\Types\InputMedia\InputMediaPhoto();
                } else {
                    $media = new \TelegramBot\Api\Types\InputMedia\InputMediaVideo();
                }
                $media->setType($item['type']);
                $media->setMedia($item['file_id']);

                if ($index === 0) {
                    $media->setCaption($caption);
                    $media->setParseMode('HTML');
                }
                $inputMedia->addItem($media);
            }

            $this->telegram->sendMediaGroup($chatId, $inputMedia);
            if ($keyboard) {
                $this->telegram->sendMessage($chatId, "<b>" . $this->translator->translate('common.choose_action', $language) . "</b>", 'HTML', false, null, $keyboard);
            }
        } else {
            $this->telegram->sendMessage($chatId, $caption, 'HTML', false, null, $keyboard);
        }
    }

    private function getStatusText(string $status, string $language): string
    {
        return match($status) {
            'pending' => $this->translator->translate('report.pending', $language),
            'accepted' => $this->translator->translate('report.accepted', $language),
            'rejected' => $this->translator->translate('report.rejected', $language),
            default => $status
        };
    }

    public function saveReport(array $session, string $language): ?int
    {
        $reportId = $this->db->addReport(
            $session['user_id'],
            $session['data']['reporter_nick'],
            $session['data']['reported_nick'],
            $session['data']['reason'],
            !empty($session['data']['media_files']) ? json_encode($session['data']['media_files']) : ($session['data']['text_proof'] ?? null),
            !empty($session['data']['media_files']) ? 'multiple_media' : 'text',
            null
        );

        if ($reportId) {
            $this->logger->info("Створено репорт #{$reportId}");
            $this->notifyAdmins($reportId, $session, $language);
        }

        return $reportId;
    }

    private function notifyAdmins(int $reportId, array $session, string $language): void
    {
        $admins = $this->db->getAllAdmins();

        $caption = "<b>#{$reportId}</b>\n\n";
        $caption .= "<b>" . $this->translator->translate('report.reporter', $language) . ":</b> <code>{$session['data']['reporter_nick']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('report.violator', $language) . ":</b> <code>{$session['data']['reported_nick']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('common.reason', $language) . ":</b> <code>{$session['data']['reason']}</code>\n";
        $caption .= "<b>" . $this->translator->translate('common.date', $language) . ":</b> " . date("d.m.Y H:i") . "\n";
        $caption .= "<b>" . $this->translator->translate('common.status', $language) . ":</b> " . $this->translator->translate('report.pending', $language) . "\n";

        if (!empty($session['data']['text_proof'])) {
            $caption .= "<b>" . $this->translator->translate('report.proof', $language) . ":</b> {$session['data']['text_proof']}\n";
        }

        $keyboard = new InlineKeyboardMarkup([
            [
                ['text' => $this->translator->translate('report.buttons.accept', $language), 'callback_data' => "accept_{$reportId}_0"],
                ['text' => $this->translator->translate('report.buttons.reject', $language), 'callback_data' => "reject_{$reportId}_0"]
            ]
        ]);

        $mediaFiles = $session['data']['media_files'] ?? [];

        foreach ($admins as $admin) {
            try {
                if (!empty($admin['user_id'])) {
                    if (count($mediaFiles) === 1) {
                        $item = $mediaFiles[0];
                        if ($item['type'] === 'photo') {
                            $this->telegram->sendPhoto($admin['user_id'], $item['file_id'], $caption, 'HTML', $keyboard);
                        } else {
                            $this->telegram->sendVideo($admin['user_id'], $item['file_id'], $caption, 'HTML', $keyboard);
                        }
                    } elseif (count($mediaFiles) > 1) {
                        $inputMedia = new \TelegramBot\Api\Types\InputMedia\ArrayOfInputMedia();
                        foreach ($mediaFiles as $index => $item) {
                            if ($item['type'] === 'photo') {
                                $media = new \TelegramBot\Api\Types\InputMedia\InputMediaPhoto();
                            } else {
                                $media = new \TelegramBot\Api\Types\InputMedia\InputMediaVideo();
                            }
                            $media->setType($item['type']);
                            $media->setMedia($item['file_id']);

                            if ($index === 0) {
                                $media->setCaption($caption);
                                $media->setParseMode('HTML');
                            }
                            $inputMedia->addItem($media);
                        }

                        $this->telegram->sendMediaGroup($admin['user_id'], $inputMedia);
                        $this->telegram->sendMessage($admin['user_id'], "<b>" . $this->translator->translate('common.choose_action', $language) . "</b>", 'HTML', false, null, $keyboard);
                    } else {
                        $this->telegram->sendMessage($admin['user_id'], $caption, 'HTML', false, null, $keyboard);
                    }
                }
            } catch (\Exception $e) {
                // Ignore
            }
        }
    }
}
