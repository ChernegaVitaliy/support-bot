<?php

namespace App\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;

class Logger
{
    private MonologLogger $logger;
    private bool $debugMode;
    private string $logFile;

    public function __construct(string $logFile, string $logLevel = 'INFO')
    {
        $this->logFile = $logFile;
        $this->debugMode = ($logLevel === 'DEBUG');

        $this->ensureLogFileExists();

        $level = $this->convertLogLevel($logLevel);

        $this->logger = new MonologLogger('telegram-bot');
        $this->logger->pushHandler(new StreamHandler($logFile, $level));
    }

    private function ensureLogFileExists(): void
    {
        if (!file_exists($this->logFile)) {
            $dir = dirname($this->logFile);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($this->logFile, "=== Bot Log Started ===\n");
        }
    }

    private function convertLogLevel(string $level): Level
    {
        return match(strtoupper($level)) {
            'DEBUG' => Level::Debug,
            'INFO' => Level::Info,
            'WARNING' => Level::Warning,
            'ERROR' => Level::Error,
            'ALERT' => Level::Critical,
            default => Level::Info
        };
    }

    public function debug(string $message): void
    {
        if (!$this->debugMode) {
            return;
        }
        $this->logger->debug($message);
        $this->outputToConsole('DEBUG', $message, "\033[0;36m");
    }

    public function info(string $message): void
    {
        $this->logger->info($message);
        $this->outputToConsole('INFO', $message, "\033[0;32m");
    }

    public function warning(string $message): void
    {
        $this->logger->warning($message);
        $this->outputToConsole('WARNING', $message, "\033[1;33m");
    }

    public function error(string $message): void
    {
        $this->logger->error($message);
        $this->outputToConsole('ERROR', $message, "\033[1;31m");
    }

    public function alert(string $message): void
    {
        $this->logger->critical($message);
        $this->outputToConsole('ALERT', $message, "\033[1;35m");
    }

    private function outputToConsole(string $level, string $message, string $color): void
    {
        $date = date("Y-m-d H:i:s");
        $timeColor = "\033[2;37m";
        $resetColor = "\033[0m";

        $coloredEntry = "{$timeColor}[$date]{$resetColor} {$color}[$level] $message{$resetColor}\n";
        echo $coloredEntry;
    }

    public function rotateLogFile(int $backupCount = 5): void
    {
        if (!file_exists($this->logFile)) {
            return;
        }

        $oldestBackup = $this->logFile . '.' . $backupCount;
        if (file_exists($oldestBackup)) {
            unlink($oldestBackup);
        }

        for ($i = $backupCount - 1; $i >= 1; $i--) {
            $oldFile = $this->logFile . '.' . $i;
            $newFile = $this->logFile . '.' . ($i + 1);
            if (file_exists($oldFile)) {
                rename($oldFile, $newFile);
            }
        }

        rename($this->logFile, $this->logFile . '.1');
        $this->ensureLogFileExists();
    }

    public function getLogFilePath(): string
    {
        return $this->logFile;
    }

    public function checkAndRotate(int $maxSize, int $backupCount): void
    {
        if (file_exists($this->logFile) && filesize($this->logFile) > $maxSize) {
            $this->rotateLogFile($backupCount);
        }
    }
}
