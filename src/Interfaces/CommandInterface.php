<?php

namespace App\Interfaces;

use TelegramBot\Api\Types\Message;

interface CommandInterface
{
    public function getName(): string;

    public function getDescription(string $language = 'uk'): string;

    public function execute(Message $message, string $language = 'uk'): void;

    public function isAdminOnly(): bool;

    public function getRequiredRank(): ?string;
}
