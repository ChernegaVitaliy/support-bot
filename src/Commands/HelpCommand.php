<?php

namespace App\Commands;

use TelegramBot\Api\Types\Message;

class HelpCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/help';
    }

    public function getDescription(): string
    {
        return 'Показати список команд';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        $chatId = $this->getChatId($message);
        $userId = $this->getUserId($message);
        $isAdmin = $this->isAdmin($userId);
        $currentRank = $this->getCurrentAdminRank($userId);

        $text = $this->t('commands.help.title', $language) . "\n";
        $text .= $this->t('commands.help.list', $language) . "\n";

        if ($isAdmin) {
            $text .= "\n" . $this->t('admin.title', $language) . " (" . ($currentRank ?? 'unknown') . "):\n";

            $text .= "/adminlist - " . $this->t('admin.commands_desc.list', $language) . "\n";
            $text .= "/reports - " . $this->t('admin.commands_desc.reports', $language) . "\n";
            $text .= "/report ID - " . $this->t('admin.commands_desc.view_report', $language) . "\n";
            $text .= "/accept ID - " . $this->t('admin.commands_desc.accept', $language) . "\n";
            $text .= "/reject ID - " . $this->t('admin.commands_desc.reject', $language) . "\n";

            if (in_array($currentRank, ['admin', 'owner'])) {
                $text .= "/addadmin - " . $this->t('admin.commands_desc.add', $language) . "\n";
                $text .= "/removeadmin - " . $this->t('admin.commands_desc.remove', $language) . "\n";
            }

            if ($currentRank === 'owner') {
                $text .= "/setrank - " . $this->t('admin.commands_desc.rank', $language) . "\n";
                $text .= "/debug - " . $this->t('admin.commands_desc.debug', $language) . "\n";
                $text .= "/broadcast - " . $this->t('admin.commands_desc.broadcast', $language) . "\n";
            }
        }

        $text .= "\n" . $this->t('common.cancel_desc', $language);

        $this->reply($chatId, $text);
    }
}
