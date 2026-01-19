<?php

namespace App\Config;

use Dotenv\Dotenv;

class Config
{
    private array $config;
    private string $basePath;
    private array $logLevels = ['DEBUG', 'INFO', 'WARNING', 'ERROR'];

    public function __construct(string $basePath = null)
    {
        $this->basePath = $basePath ?? dirname(__DIR__, 2);
        $this->loadEnv();
        $this->loadConfig();
    }

    private function loadEnv(): void
    {
        $envFile = $this->basePath . '/.env';

        if (!file_exists($envFile)) {
            $this->printError('.ENV FILE NOT FOUND!');
            $terminalWidth = exec('tput cols 2>/dev/null') ?: 50;
            $line = str_repeat('─', $terminalWidth);
            echo "\033[1;31m❌ ФАЙЛ .ENV НЕ ЗНАЙДЕНО!\n\033[0m";
            echo "$line\n";
            echo "\033[1;33m📁 Створіть файл: $envFile\n📝 З вмістом:\nBOT_TOKEN=ваш_токен\nDB_PATH=шлях/до/бази.db\nLOG_FILE=шлях/до/логу.log\n\033[0m";
            exit(1);
        }

        $dotenv = Dotenv::createImmutable($this->basePath);
        $dotenv->load();
        $dotenv->required(['BOT_TOKEN']);
    }

    private function loadConfig(): void
    {
        $configFile = $this->basePath . '/config.php';

        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [
                'logging' => [
                    'level' => 'INFO',
                    'max_file_size' => 10485760,
                    'backup_count' => 5,
                    'colors_in_file' => false,
                ],
            ];
        }
    }

    private function saveConfig(): void
    {
        $configFile = $this->basePath . '/config.php';
        $content = "<?php\n\nreturn " . var_export($this->config, true) . ";\n";
        file_put_contents($configFile, $content);
    }

    private function getEnv(string $key, $default = null)
    {
        return $_ENV[$key] ?? $_SERVER[$key] ?? $default;
    }

    public function get(string $key, $default = null)
    {
        $envValue = $this->getEnv($key);
        if ($envValue !== null) {
            return $envValue;
        }

        if (strpos($key, '.') !== false) {
            $parts = explode('.', $key);
            $value = $this->config;

            foreach ($parts as $part) {
                if (!isset($value[$part])) {
                    return $default;
                }
                $value = $value[$part];
            }

            return $value;
        }

        return $this->config[$key] ?? $default;
    }

    public function getBotToken(): string
    {
        $token = $this->getEnv('BOT_TOKEN', '');

        if (empty($token)) {
            $this->printError('TOKEN IS EMPTY!');
            exit(1);
        }

        if (strpos($token, ':') === false) {
            $this->printError('TOKEN DOES NOT CONTAIN A COLON!');
            exit(1);
        }

        $parts = explode(':', $token);

        if (count($parts) !== 2) {
            $this->printError('TOO MANY COLONS IN TOKEN!');
            exit(1);
        }

        $numbers = $parts[0];
        $letters = $parts[1];

        if (!is_numeric($numbers)) {
            $this->printError('FIRST PART IS NOT NUMBERS!');
            exit(1);
        }

        if (strlen($numbers) < 8 || strlen($numbers) > 10) {
            $this->printError('INCORRECT NUMBER OF DIGITS!');
            exit(1);
        }

        if (strlen($letters) !== 35) {
            $this->printError('INCORRECT TOKEN LENGTH!');
            exit(1);
        }

        return $token;
    }

    public function getLogFile(): string
    {
        return $this->getEnv('LOG_FILE', 'bot.log');
    }

    public function getLogLevel(): string
    {
        return $this->get('logging.level', 'INFO');
    }

    public function toggleLogLevel(): string
    {
        $currentLevel = $this->getLogLevel();
        $currentIndex = array_search($currentLevel, $this->logLevels);
        $nextIndex = ($currentIndex + 1) % count($this->logLevels);
        $newLevel = $this->logLevels[$nextIndex];
        $this->setLogLevel($newLevel);
        return $newLevel;
    }

    public function setLogLevel(string $level): void
    {
        if (in_array($level, $this->logLevels)) {
            $this->config['logging']['level'] = $level;
            unset($this->config['debug_mode']);
            $this->saveConfig();
        }
    }

    public function getMaxFileSize(): int
    {
        return $this->get('logging.max_file_size', 10485760);
    }

    public function getBackupCount(): int
    {
        return $this->get('logging.backup_count', 5);
    }

    public function getDefaultOwnerId(): string
    {
        return $this->getEnv('DEFAULT_OWNER_ID', '');
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function getLanguagesPath(): string
    {
        return $this->basePath . '/languages';
    }

    public function getDbPath(): string
    {
        return $this->getEnv('DB_PATH', 'bot.db');
    }

    private function printError(string $message): void
    {
        $terminalWidth = exec('tput cols 2>/dev/null') ?: 50;
        $line = str_repeat('─', $terminalWidth);
        echo "\033[1;31m❌ $message\n\033[0m";
        echo "$line\n";
        echo "\033[1;33m🎯 Отримайте токен у @BotFather\n\033[0m";
    }
}
