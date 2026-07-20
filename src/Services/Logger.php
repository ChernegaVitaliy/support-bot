<?php

namespace App\Services;

use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Psr\Log\LoggerInterface;

class Logger implements LoggerInterface
{
    private MonologLogger $logger;
    private bool $debugMode;
    private string $logFile;
    private Level $currentLevel;

    public function __construct(string $logFile, string $logLevel = 'INFO')
    {
        $this->logFile = $logFile;

        $this->ensureLogFileExists();

        $this->applyLevel($logLevel);
    }

    /**
     * Updates the active log level of the running logger immediately,
     * without requiring a bot restart.
     */
    public function setLevel(string $logLevel): void
    {
        $this->applyLevel($logLevel);
    }

    private function applyLevel(string $logLevel): void
    {
        $level = $this->convertLogLevel($logLevel);
        $this->debugMode = ($logLevel === 'DEBUG');
        $this->currentLevel = $level;

        $this->logger = new MonologLogger('telegram-bot');
        $this->logger->pushHandler(new StreamHandler($this->logFile, $level));
    }

    private function isHandling(Level $level): bool
    {
        return $level->value >= $this->currentLevel->value;
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

    public function debug(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Debug, fn() => $this->logger->debug($message, $context), 'DEBUG', (string)$message, "\033[0;36m");
    }

    public function info(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Info, fn() => $this->logger->info($message, $context), 'INFO', (string)$message, "\033[0;32m");
    }

    public function warning(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Warning, fn() => $this->logger->warning($message, $context), 'WARNING', (string)$message, "\033[1;33m");
    }

    public function error(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Error, fn() => $this->logger->error($message, $context), 'ERROR', (string)$message, "\033[1;35m");
    }

    public function emergency(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Emergency, fn() => $this->logger->emergency($message, $context), 'EMERGENCY', (string)$message, "\033[1;35m");
    }

    public function alert(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Alert, fn() => $this->logger->critical($message, $context), 'ALERT', (string)$message, "\033[1;35m");
    }

    public function critical(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Critical, fn() => $this->logger->critical($message, $context), 'CRITICAL', (string)$message, "\033[1;35m");
    }

    public function notice(string|\Stringable $message, array $context = []): void
    {
        $this->write(Level::Notice, fn() => $this->logger->notice($message, $context), 'NOTICE', (string)$message, "\033[0;32m");
    }

    public function log($level, string|\Stringable $message, array $context = []): void
    {
        $monologLevel = $this->convertLogLevel((string)$level);
        $this->write($monologLevel, fn() => $this->logger->log($level, $message, $context), (string)$level, (string)$message, "\033[0;37m");
    }

    private function write(Level $level, callable $fileWrite, string $label, string $message, string $color): void
    {
        if (!$this->isHandling($level)) {
            return;
        }

        $fileWrite();
        $this->outputToConsole($label, $message, $color);
    }

    public function __call(string $name, array $arguments): void
    {
        if (method_exists($this->logger, $name)) {
            $this->logger->$name(...$arguments);
        }
    }

    private function outputToConsole(string $level, string $message, string $color): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

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
