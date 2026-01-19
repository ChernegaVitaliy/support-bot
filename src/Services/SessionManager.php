<?php

namespace App\Services;

class SessionManager
{
    private array $reportSessions = [];
    private array $broadcastSessions = [];
    private array $adminActionSessions = [];
    private Logger $logger;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    public function setReportSession(string $chatId, array $data): void
    {
        $this->reportSessions[$chatId] = $data;
        $this->logger->debug("Сесія репорту встановлена для $chatId");
    }

    public function getReportSession(string $chatId): ?array
    {
        return $this->reportSessions[$chatId] ?? null;
    }

    public function hasReportSession(string $chatId): bool
    {
        return isset($this->reportSessions[$chatId]);
    }

    public function clearReportSession(string $chatId): void
    {
        unset($this->reportSessions[$chatId]);
        $this->logger->debug("Сесія репорту очищена для $chatId");
    }

    public function setBroadcastSession(string $chatId, array $data): void
    {
        $this->broadcastSessions[$chatId] = $data;
        $this->logger->debug("Сесія розсилки встановлена для $chatId");
    }

    public function getBroadcastSession(string $chatId): ?array
    {
        return $this->broadcastSessions[$chatId] ?? null;
    }

    public function hasBroadcastSession(string $chatId): bool
    {
        return isset($this->broadcastSessions[$chatId]);
    }

    public function clearBroadcastSession(string $chatId): void
    {
        unset($this->broadcastSessions[$chatId]);
        $this->logger->debug("Сесія розсилки очищена для $chatId");
    }

    public function setAdminActionSession(string $chatId, array $data): void
    {
        $this->adminActionSessions[$chatId] = $data;
        $this->logger->debug("Адмін-сесія встановлена для $chatId");
    }

    public function getAdminActionSession(string $chatId): ?array
    {
        return $this->adminActionSessions[$chatId] ?? null;
    }

    public function hasAdminActionSession(string $chatId): bool
    {
        return isset($this->adminActionSessions[$chatId]);
    }

    public function clearAdminActionSession(string $chatId): void
    {
        unset($this->adminActionSessions[$chatId]);
        $this->logger->debug("Адмін-сесія очищена для $chatId");
    }

    public function clearAllSessions(string $chatId): void
    {
        $this->clearReportSession($chatId);
        $this->clearBroadcastSession($chatId);
        $this->clearAdminActionSession($chatId);
        $this->logger->debug("Всі сесії очищені для $chatId");
    }

    public function getActiveReportSessionsCount(): int
    {
        return count($this->reportSessions);
    }

    public function getActiveBroadcastSessionsCount(): int
    {
        return count($this->broadcastSessions);
    }

    public function getActiveAdminSessionsCount(): int
    {
        return count($this->adminActionSessions);
    }

    public function getActiveSessionsCount(): int
    {
        return $this->getActiveReportSessionsCount() +
               $this->getActiveBroadcastSessionsCount() +
               $this->getActiveAdminSessionsCount();
    }
}
