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

    public function setReportSession(string $userId, array $data): void
    {
        $this->reportSessions[$userId] = $data;
        $this->logger->debug("Сесія репорту встановлена для $userId");
    }

    public function getReportSession(string $userId): ?array
    {
        return $this->reportSessions[$userId] ?? null;
    }

    public function hasReportSession(string $userId): bool
    {
        return isset($this->reportSessions[$userId]);
    }

    public function clearReportSession(string $userId): void
    {
        unset($this->reportSessions[$userId]);
        $this->logger->debug("Сесія репорту очищена для $userId");
    }

    public function setBroadcastSession(string $userId, array $data): void
    {
        $this->broadcastSessions[$userId] = $data;
        $this->logger->debug("Сесія розсилки встановлена для $userId");
    }

    public function getBroadcastSession(string $userId): ?array
    {
        return $this->broadcastSessions[$userId] ?? null;
    }

    public function hasBroadcastSession(string $userId): bool
    {
        return isset($this->broadcastSessions[$userId]);
    }

    public function clearBroadcastSession(string $userId): void
    {
        unset($this->broadcastSessions[$userId]);
        $this->logger->debug("Сесія розсилки очищена для $userId");
    }

    public function setAdminActionSession(string $userId, array $data): void
    {
        $this->adminActionSessions[$userId] = $data;
        $this->logger->debug("Адмін-сесія встановлена для $userId");
    }

    public function getAdminActionSession(string $userId): ?array
    {
        return $this->adminActionSessions[$userId] ?? null;
    }

    public function hasAdminActionSession(string $userId): bool
    {
        return isset($this->adminActionSessions[$userId]);
    }

    public function clearAdminActionSession(string $userId): void
    {
        unset($this->adminActionSessions[$userId]);
        $this->logger->debug("Адмін-сесія очищена для $userId");
    }

    public function clearAllSessions(string $userId): void
    {
        $this->clearReportSession($userId);
        $this->clearBroadcastSession($userId);
        $this->clearAdminActionSession($userId);
        $this->logger->debug("Всі сесії очищені для $userId");
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
