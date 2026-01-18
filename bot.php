<?php
// Визначаємо базовий шлях до проекту
define('BASE_PATH', __DIR__);

// Підключаємо конфігурацію
$config = require_once BASE_PATH . '/config.php';

// Вимикаємо попередження про втрату точності для великих чисел
ini_set('precision', 16);
ini_set('serialize_precision', 16);

// Константи рівнів логування - з перевіркою на існування
if (!defined('LOG_DEBUG')) define('LOG_DEBUG', 0);
if (!defined('LOG_INFO')) define('LOG_INFO', 1);
if (!defined('LOG_WARNING')) define('LOG_WARNING', 2);
if (!defined('LOG_ERROR')) define('LOG_ERROR', 3);
if (!defined('LOG_ALERT')) define('LOG_ALERT', 4);

// Завантажуємо змінні оточення з .env файлу
$env_file = BASE_PATH . '/.env';
if (!file_exists($env_file)) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ФАЙЛ .ENV НЕ ЗНАЙДЕНО!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m📁 Створіть файл: $env_file\n📝 З вмістом:\nBOT_TOKEN=ваш_токен\nDB_PATH=шлях/до/бази.db\nLOG_FILE=шлях/до/логу.log\n\033[0m";
    die();
}

$env_vars = parse_ini_file($env_file);
$token = $env_vars['BOT_TOKEN'] ?? '';

// Перевірка токена
if (empty($token)) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ТОКЕН ПОРОЖНІЙ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Додайте BOT_TOKEN= у .env файл\n🎯 Отримайте токен у @BotFather\n\033[0m";
    die();
}

// Перевірка формату (наявність двокрапки)
if (strpos($token, ':') === false) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ В ТОКЕНІ НЕМАЄ ДВОКРАПКИ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Формат: 123456789:ABCdef...\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

// Розділяємо токен на частини
$parts = explode(':', $token);
if (count($parts) !== 2) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ЗАБАГАТО ДВОКРАПОК У ТОКЕНІ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Має бути тільки одна двокрапка\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

$numbers = $parts[0];
$letters = $parts[1];

// Перевірка цифрової частини
if (!is_numeric($numbers)) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ПЕРША ЧАСТИНА НЕ З ЦИФР!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Перша частина має бути цифрами\n📊 Знайдено: $numbers\n📏 Потрібно: 8-10 цифр\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

if (strlen($numbers) < 8 || strlen($numbers) > 10) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ НЕПРАВИЛЬНА КІЛЬКІСТЬ ЦИФР!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Неправильна кількість цифр\n📊 Знайдено: " . strlen($numbers) . " цифр\n📏 Потрібно: 8-10 цифр\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

// Перевірка буквеної частини
if (strlen($letters) < 30) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ЗАМАЛО СИМВОЛІВ У ДРУГІЙ ЧАСТИНІ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Замало символів\n📊 Знайдено: " . strlen($letters) . " символів\n📏 Потрібно: 35 символів\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

if (strlen($letters) > 35) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ЗАБАГАТО СИМВОЛІВ У ДРУГІЙ ЧАСТИНІ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Забагато символів\n📊 Знайдено: " . strlen($letters) . " символів\n📏 Потрібно: 35 символів\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

// Перевірка на спеціальні символи в другій частині
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $letters)) {
    $terminal_width = exec('tput cols 2>/dev/null') ?: 50;
    $line = str_repeat('─', $terminal_width);
    echo "\033[1;31m❌ ЗАБОРОНЕНІ СИМВОЛИ У ТОКЕНІ!\n\033[0m";
    echo "$line\n";
    echo "\033[1;33m🔧 Дозволені символи: A-Z, a-z, 0-9, _, -\n🎯 Отримайте новий токен у @BotFather\n\033[0m";
    die();
}

// Отримуємо шляхи з .env або використовуємо значення за замовчуванням
$db_path = $env_vars['DB_PATH'] ?? $config['database']['path'];
$log_file = $env_vars['LOG_FILE'] ?? $config['log_file'];

// Ініціалізуємо базу даних
require_once BASE_PATH . '/database.php';
$db = new Database($db_path);

$apiURL = "https://api.telegram.org/bot$token/";
$logFile = $log_file;

// Перевіряємо чи існує файл логу, якщо ні - створюємо
if (!file_exists($logFile)) {
    file_put_contents($logFile, "=== Bot Log Started ===\n");
}

// Завантажуємо переклади - ВИПРАВЛЕНИЙ СПИСОК МОВ
$languages = [];
$languageFiles = ['uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja', 'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro', 'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el', 'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'];

foreach ($languageFiles as $lang) {
    $filePath = BASE_PATH . "/languages/{$lang}.json";
    if (file_exists($filePath)) {
        $languages[$lang] = json_decode(file_get_contents($filePath), true);
    } else {
        logWarning("Файл мови не знайдено: $filePath");
    }
}

// Функція для отримання перекладу
function t($key, $lang = 'ru', $params = []) {
    global $languages;

    // Спочатку шукаємо в обраній мові, потім сам ключ (без резервної мови)
    $translation = $languages[$lang][$key] ?? $key;

    // Замінюємо параметри якщо вони є
    if (!empty($params)) {
        $translation = vsprintf($translation, $params);
    }

    return $translation;
}

// Допоміжні функції для роботи з рівнями логування
function getLogLevel($level_name) {
    $levels = [
        'DEBUG' => LOG_DEBUG,
        'INFO' => LOG_INFO,
        'WARNING' => LOG_WARNING,
        'ERROR' => LOG_ERROR,
        'ALERT' => LOG_ALERT
    ];
    return $levels[strtoupper($level_name)] ?? LOG_INFO;
}

function getLogLevelName($level) {
    $names = [
        LOG_DEBUG => 'DEBUG',
        LOG_INFO => 'INFO',
        LOG_WARNING => 'WARNING',
        LOG_ERROR => 'ERROR',
        LOG_ALERT => 'ALERT'
    ];
    return $names[$level] ?? 'INFO';
}

// Функція для ротації лог-файлів
function rotateLogFile($logFile, $backup_count = 5) {
    if (!file_exists($logFile)) return;

    // Видаляємо найстаріший backup
    $oldest_backup = $logFile . '.' . $backup_count;
    if (file_exists($oldest_backup)) {
        unlink($oldest_backup);
    }

    // Зсуваємо інші backups
    for ($i = $backup_count - 1; $i >= 1; $i--) {
        $old_file = $logFile . '.' . $i;
        $new_file = $logFile . '.' . ($i + 1);
        if (file_exists($old_file)) {
            rename($old_file, $new_file);
        }
    }

    // Перейменовуємо поточний лог
    rename($logFile, $logFile . '.1');
}

// Функція для запису логів з рівнями та автоматичним перемиканням
function writeLog($message, $level = LOG_INFO) {
    global $logFile, $config;

    // ВИПРАВЛЕННЯ: перевірка на null
    if (empty($logFile)) {
        $logFile = BASE_PATH . '/bot.log';
    }

    // Якщо debug_mode вимкнено - ігноруємо DEBUG повідомлення
    if (($config['debug_mode'] ?? false) === false && $level === LOG_DEBUG) {
        return;
    }

    $date = date("Y-m-d H:i:s");
    $level_name = getLogLevelName($level);

    // Кольори для різних рівнів логування
    $colors = [
        'DEBUG' => "\033[0;36m",     // Ціан
        'INFO' => "\033[0;32m",      // Зелений
        'WARNING' => "\033[1;33m",   // Жовтий
        'ERROR' => "\033[1;31m",     // Червоний
        'ALERT' => "\033[1;35m"      // Пурпурний
    ];

    $reset_color = "\033[0m";        // Скидання кольору
    $time_color = "\033[2;37m";      // ЯСКРАВО-СІРИЙ

    $color = $colors[$level_name] ?? "\033[0;37m"; // Білий за замовчуванням

    // Кольорове повідомлення для консолі - час сірим
    $colored_entry = "{$time_color}[$date]{$reset_color} {$color}[$level_name] $message{$reset_color}\n";

    // Звичайне повідомлення для файлу (без кольорів)
    $file_entry = "[$date] [$level_name] $message\n";

    // Додаткова перевірка перед використанням file_exists
    if (!empty($logFile) && file_exists($logFile) && filesize($logFile) > ($config['logging']['max_file_size'] ?? 10485760)) {
        rotateLogFile($logFile, $config['logging']['backup_count'] ?? 5);
    }

    // Запис у файл (без кольорів)
    if (!empty($logFile) && is_writable(dirname($logFile))) {
        file_put_contents($logFile, $file_entry, FILE_APPEND | LOCK_EX);
    }

    // Вивід в консоль (з кольорами)
    echo $colored_entry;
}

// Зручні функції для різних рівнів логування
function logDebug($message) {
    writeLog($message, LOG_DEBUG);
}

function logInfo($message) {
    writeLog($message, LOG_INFO);
}

function logWarning($message) {
    writeLog($message, LOG_WARNING);
}

function logError($message) {
    writeLog($message, LOG_ERROR);
}

function logAlert($message) {
    writeLog($message, LOG_ALERT);
}

// Функція для очищення команди від юзернейму бота
function cleanCommandFromBotUsername($text, $botUsername) {
    $clean_text = $text;

    // Видаляємо юзернейм бота якщо він є в команді
    if (preg_match("/^\/[a-zA-Z0-9_]+@$botUsername/i", $clean_text)) {
        $clean_text = preg_replace("/@$botUsername\s*/i", '', $clean_text);
        $clean_text = trim($clean_text);
        logDebug("CMD_CLEANED: $text -> $clean_text");
    }

    return $clean_text;
}

// Функція для отримання мови користувача через Telegram API
function getUserLanguageFromAPI($user_id) {
    global $apiURL;

    try {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiURL . "getChat?chat_id=" . strval($user_id ?? ''),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        $data = json_decode($response, true);
        if ($data && $data['ok'] && isset($data['result']['language_code'])) {
            return $data['result']['language_code'];
        }
    } catch (Exception $e) {
        logError("Помилка отримання мови з API: " . $e->getMessage());
    }

    return null;
}

// Функція для автоматичного визначення мови
function detectLanguage($from) {
    global $db;

    $user_id = strval($from['id'] ?? '');

    // 1. Отримуємо поточну мову з бази
    $current_language = $db->getUserLanguage($user_id);

    // 2. Визначаємо поточну мову з повідомлення
    $detected_language = autoDetectLanguage($from);

    // 3. Якщо мова змінилась - оновлюємо в базі
    if ($current_language !== $detected_language) {
        $db->updateUserLanguage($user_id, $detected_language);
        logInfo("🔄 Мова оновлена для $user_id: $current_language -> $detected_language");
    }

    return $detected_language;
}

// Допоміжна функція для автоматичного визначення
function autoDetectLanguage($from) {
    // 1. Пробуємо отримати мову через API Telegram
    if (isset($from['id'])) {
        $api_lang = getUserLanguageFromAPI(strval($from['id'] ?? ''));
        if ($api_lang) {
            logDebug("Мова з API: " . $api_lang);
            $lang_checks = [
                'uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja',
                'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro',
                'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el',
                'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'
            ];

            foreach ($lang_checks as $lang) {
                if (strpos($api_lang, $lang) === 0) {
                    return $lang;  // ⭐⭐⭐ ПОВЕРТАЄМО БЕЗ ПІДКРЕСЛЕННЯ ⭐⭐⭐
                }
            }
        }
    }

    // 2. Якщо API не повернув мову, пробуємо з повідомлення
    if (isset($from['language_code'])) {
        $lang_code = strtolower($from['language_code']);
        logDebug("Мова з повідомлення: " . $lang_code);

        $lang_checks = [
            'uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja',
            'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro',
            'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el',
            'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'
        ];

        foreach ($lang_checks as $lang) {
            if (strpos($lang_code, $lang) === 0) {
                return $lang;  // ⭐⭐⭐ ПОВЕРТАЄМО БЕЗ ПІДКРЕСЛЕННЯ ⭐⭐⭐
            }
        }
    }

    logDebug("Мова не визначена, використовується за замовчуванням");
    return 'uk'; // Мова за замовчуванням
}

// Отримуємо інформацію про бота при запуску
function getBotInfo() {
    global $apiURL;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "getMe",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($data && $data['ok']) {
        return $data['result'];
    }
    return null;
}

$botInfo = getBotInfo();
if (!$botInfo) {
    echo "\033[1;35m";
    echo "╔══════════════════════════════════════╗\n";
    echo "║           TELEGRAM BOT v2.0          ║\n";
    echo "║            ❌ ПОМИЛКА!               ║\n";
    echo "╚══════════════════════════════════════╝\n";
    echo "\033[0m";

    echo "\033[1;36m";
    echo "======================================================\n";
    echo "\033[0m";

    echo "\033[1;31m";
    echo "❌ Не вдалося отримати інформацію про бота!\n";
    echo "❗Перевірте інтернет-з'єднання\n";
    echo "\033[0m";

    echo "\033[1;36m";
    echo "======================================================\n";
    echo "\033[0m";
    die();
}

$botUsername = $botInfo['username'];
$botName = $botInfo['first_name'];
logInfo("Бот запущений: @$botUsername ($botName)");

// Підключаємо команди
require_once BASE_PATH . '/commands/user_commands.php';
require_once BASE_PATH . '/commands/admin_commands.php';
require_once BASE_PATH . '/commands/console_commands.php';

// Глобальні змінні для сесій
global $report_sessions, $broadcast_sessions, $admin_action_sessions;
$report_sessions = [];
$broadcast_sessions = [];

// Створюємо об'єкт консольних команд
$consoleCommands = new ConsoleCommands($db, $config, $botUsername);

// Основний код
logInfo("Бот запущений з автоматичним визначенням мови");
$last_update_id = 0;

echo "\033[1;35m";
echo "╔══════════════════════════════════════╗\n";
echo "║           TELEGRAM BOT v2.0          ║\n";
echo "║            🤖 ЗАПУЩЕНО!              ║\n";
echo "╚══════════════════════════════════════╝\n";
echo "\033[0m";

echo "\033[1;32m";
echo "✅ Support Bot запущений з автоматичним визначенням мови\n";
echo "🤖 Юзернейм бота: @$botUsername\n";
echo "🤖 Ім'я бота: $botName\n";
echo "🌍 Підтримувані мови: " . count($languageFiles) . "\n";
echo "📊 Рівень логування: " . ($config['logging']['level'] ?? 'INFO') . "\n";
echo "🔧 Debug mode: " . (($config['debug_mode'] ?? false) ? '🟢 Увімкнено' : '🔴 Вимкнено') . "\n";
echo "\033[0m";

echo "\033[1;36m";
echo "======================================================\n";
echo "🎯 Бот активний | Очікую повідомлення...\n";
echo "⏹️  Для зупинки натисни Ctrl+C\n";
echo "======================================================\n";
echo "\033[0m";

echo "\033[1;32m\n💻 КОНСОЛЬНИЙ РЕЖИМ АКТИВОВАНО!\n";
echo "📝 Введіть 'help' для списку команд\033[0m\n\n";

// Функція для відповіді на callback query
function answerCallbackQuery($callback_query_id, $text = "") {
    global $apiURL;

    $data = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'cache_time' => 1
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "answerCallbackQuery",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 2,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    logDebug("✅ Відповідь на callback: $text");
    return $result;
}

while (true) {
    // ================== КОНСОЛЬНІ КОМАНДИ ==================
    $read = [STDIN];
    $write = [];
    $except = [];
    $timeout = 0.1;

    if (stream_select($read, $write, $except, 0, 100000) > 0) {
        $consoleInput = trim(fgets(STDIN));
        if ($consoleInput) {
            $consoleCommands->handleCommand($consoleInput);
            continue;
        }
    }

    // ================== TELEGRAM ОНОВЛЕННЯ ==================
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiURL . "getUpdates?offset=" . ($last_update_id + 1) . "&timeout=1");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        if (strpos($error, 'timed out') === false && strpos($error, 'Operation timed out') === false) {
            logError("Помилка отримання оновлень: " . $error);
        }
        curl_close($ch);
        usleep(100000);
        continue;
    }

    curl_close($ch);
    $updates = json_decode($response, true);

    if (!empty($updates["result"])) {
        foreach ($updates["result"] as $update) {
            $last_update_id = $update["update_id"];

            if (isset($update["message"])) {
                $message = $update["message"];
                $chat_id = (string)$message["chat"]["id"];
                $chat_type = $message["chat"]["type"];
                $from = $message["from"];
                $text = $message["text"] ?? "";

                $user_lang = detectLanguage($from);

                if (strpos($text, '/cancel') === 0) {
                    logInfo("CANCEL для $chat_id: $text");
                    $clean_text = cleanCommandFromBotUsername($text, $botUsername);
                    handleCancelCommand($chat_id, $user_lang, $clean_text);
                    continue;
                }

                global $admin_action_sessions;
                if (isset($admin_action_sessions[$chat_id])) {
                    logInfo("АДМІН-СЕСІЯ для $chat_id: $text");
                    handleAdminActionSession($chat_id, $text, $from, $user_lang);
                    continue;
                }

                global $report_sessions;
                if (isset($report_sessions[$chat_id])) {
                    logInfo("СЕСІЯ РЕПОРТУ для $chat_id: $text");
                    $handled = handleReportSession($chat_id, $text, $from, $user_lang);
                    if ($handled) continue;
                }

                if (isInBroadcastSession($chat_id)) {
                    logDebug("СЕСІЯ РОЗСИЛКИ для $chat_id");
                    handleBroadcastSession($chat_id, $message, $from, $user_lang);
                    continue;
                }

                if ($chat_type === 'private') {
                    if (strpos($text, '/') === 0) {
                        $clean_text = cleanCommandFromBotUsername($text, $botUsername);
                        handleCommand($chat_id, $clean_text, $from, $chat_type);
                    }
                } else {
                    handleGroupMessage($chat_id, $text, $from, $chat_type);
                }
            }

            if (isset($update["callback_query"])) {
                $callback_query = $update["callback_query"];
                $callback_data = $callback_query['data'] ?? '';
                $chat_id = (string)$callback_query['message']['chat']['id'];
                $message_id = $callback_query['message']['message_id'];
                $from = $callback_query['from'];
                $callback_query_id = $callback_query['id'];

                logInfo("CALLBACK: $callback_data від $chat_id");

                try {
                    answerCallbackQuery($callback_query_id, "OK");

                    if (strpos($callback_data, 'broadcast_') === 0) {
                        handleBroadcastCallbackMessage($callback_query);
                    }
                    elseif (strpos($callback_data, 'admin_') === 0) {
                        $user_lang = detectLanguage($from);
                        handleAdminReportCallback($callback_data, $chat_id, $message_id, $from, $user_lang);
                    }
                    elseif (strpos($callback_data, 'report_') === 0) {
                        $user_lang = detectLanguage($from);
                        handleReportCallback($callback_data, $chat_id, $message_id, $from, $user_lang);
                    }
                    elseif (strpos($callback_data, 'accept_') === 0 || strpos($callback_data, 'reject_') === 0) {
                        $user_lang = detectLanguage($from);
                        handleAdminReportCallback($callback_data, $chat_id, $message_id, $from, $user_lang);
                    }

                    elseif (strpos($callback_data, 'profile_') === 0) {
                        $user_lang = detectLanguage($from);
                        handleProfileCallback($callback_data, $chat_id, $message_id, $from, $user_lang);
                    }

                    else {
                        logWarning("Невідомий callback: $callback_data");
                    }
                } catch (Exception $e) {
                    logError("Помилка callback: " . $e->getMessage());
                }

                $last_update_id = $update["update_id"];
                continue;
            }
        }
    }
    usleep(500000);
}

function readConsoleInput() {
    $read = [STDIN];
    $write = [];
    $except = [];

    if (stream_select($read, $write, $except, 0, 100000) > 0) {
        return trim(fgets(STDIN));
    }
    return null;
}

function sendMessage($chat_id, $text, $reply_markup = null, $disable_web_page_preview = false) {
    global $apiURL;

    // Конвертуємо chat_id в рядок для уникнення помилок з великими числами
    $chat_id = (string)$chat_id;

    logDebug("sendMessage до: $chat_id, текст: " . substr($text, 0, 50) . "...");

    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => 'HTML', // ⭐⭐⭐ ДОДАЄМО HTML ПАРСИНГ ⭐⭐⭐
        'disable_web_page_preview' => $disable_web_page_preview
    ];

    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "sendMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        logError("Помилка відправки: " . curl_error($ch));
    } else {
        logDebug("Повідомлення відправлено успішно");
    }
    curl_close($ch);

    return $result;
}

function deleteMessage($chat_id, $message_id) {
    global $apiURL;

    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "deleteMessage",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

// Оновлена функція для відправки медіа з кнопками
function sendMedia($chat_id, $file_id, $media_type, $caption = '', $reply_markup = null) {
    global $apiURL;

    $method = $media_type === 'photo' ? 'sendPhoto' : 'sendVideo';

    $data = [
        'chat_id' => $chat_id,
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];

    // Додаємо file_id в залежності від типу медіа
    if ($media_type === 'photo') {
        $data['photo'] = $file_id;
    } else {
        $data['video'] = $file_id;
    }

    // Додаємо кнопки якщо є
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . $method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $data,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    if ($result === false) {
        logError("Помилка відправки медіа: " . curl_error($ch));
    } else {
        logDebug("Медіа успішно відправлено до $chat_id з кнопками");
    }
    curl_close($ch);
}

// Оновлена функція для відправки групи медіа з кнопками
function sendMediaGroup($chat_id, $media_files, $caption = '', $reply_markup = null) {
    global $apiURL;

    $media_group = [];
    $first = true;

    foreach ($media_files as $index => $media) {
        $media_item = [
            'type' => $media['type'],
            'media' => $media['file_id']
        ];

        // Додаємо підпис тільки до першого елементу
        if ($first && $caption) {
            $media_item['caption'] = $caption;
            $media_item['parse_mode'] = 'HTML';
            $first = false;
        }

        $media_group[] = $media_item;
    }

    $data = [
        'chat_id' => $chat_id,
        'media' => json_encode($media_group)
    ];

    // Додаємо кнопки якщо є
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . "sendMediaGroup",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        logError("❌ Помилка відправки альбому: " . $result);
        return false;
    }

    logInfo("✅ Альбом успішно відправлений: " . count($media_files) . " файлів з кнопками");
    return true;
}

// Функція для відправки API запиту
function sendAPIRequest($method, $data) {
    global $apiURL;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiURL . $method,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        logError("Помилка API $method: HTTP $http_code");
        return false;
    }

    return $result;
}

// Функція для перевірки прав доступу
function hasPermission($required_rank, $current_rank) {
    logDebug("hasPermission: required=$required_rank, current=" . ($current_rank ?? 'NULL'));

    $ranks = ['owner' => 3, 'admin' => 2, 'moderator' => 1];
    $current_level = $ranks[$current_rank] ?? 0;
    $required_level = $ranks[$required_rank] ?? 0;

    $result = $current_level >= $required_level;

    logDebug("hasPermission result: $current_level >= $required_level = " . ($result ? 'true' : 'false'));
    return $result;
}

// Функція для обробки повідомлень у групах
function handleGroupMessage($chat_id, $text, $from, $chat_type) {
    global $botUsername, $db;

    $chat_id = (string)$chat_id;
    $clean_text = strip_tags($text);
    $clean_text = str_replace(['>', '<', '&gt;', '&lt;', '`'], '', $clean_text);
    $clean_text = trim($clean_text);

    logDebug("GROUP_RAW: $clean_text від $chat_id");

    if (strpos($clean_text, '/') === 0) {
        $user_id = strval($from['id'] ?? '');
        $username = $from['username'] ?? 'no_username';
        $first_name = $from['first_name'] ?? 'no_name';

        // Очищуємо команду від юзернейму бота
        $clean_text = cleanCommandFromBotUsername($clean_text, $botUsername);

        logInfo("GROUP_CMD $user_id (@$username - $first_name) в групі $chat_id: $clean_text");
        handleCommand($chat_id, $clean_text, $from, $chat_type);
    }
}

function handleCommand($chat_id, $text, $from, $chat_type) {
    global $db;

    // Конвертуємо chat_id в рядок для уникнення помилок з великими числами
    $chat_id = (string)$chat_id;

    // ОНОВЛЕННЯ ІНФОРМАЦІЇ АДМІНІВ - ТІЛЬКИ ПРИ ЗМІНІ ЮЗЕРНЕЙМУ
    if (isset($from['username']) && isset($from['id'])) {
        // Спочатку отримуємо поточні дані адміна
        $current_admin = $db->getAdminByUserId($from['id']);
        if ($current_admin) {
            $current_username = $current_admin['username'] ?? '';
            $new_username = $from['username'];

            // Перевіряємо чи змінився юзернейм
            if ($current_username !== $new_username) {
                $updated = $db->updateAdminInfo(strval($from['id'] ?? ''), $new_username, $from['first_name'] ?? '');
                if ($updated) {
                    logInfo("🔄 Оновлено юзернейм адміна {$from['id']}: {$current_username} -> @{$new_username}");
                }
            }
        }
    }

    $clean_text = strip_tags($text);
    $clean_text = str_replace(['>', '<', '&gt;', '&lt;', '`'], '', $clean_text);
    $clean_text = trim($clean_text);

    logInfo("CMD $chat_id: $clean_text");

    $user_id = strval($from['id'] ?? '');
    $db->addUser($user_id, $from['username'] ?? null, $from['first_name'] ?? null);

    // Визначаємо мову на льоту
    $user_lang = detectLanguage($from);

    // Перевіряємо права по ID користувача
    $is_admin = $db->isAdmin($user_id);
    $current_rank = $is_admin ? $db->getAdminRank($user_id) : null;

    logDebug("USER $user_id: admin=" . ($is_admin ? 'Y' : 'N') . " rank=" . ($current_rank ?? 'none'));

    // Список команд
    $parts = explode(' ', $clean_text);
    $command = $parts[0];

    logDebug("PROC $command parts:" . count($parts));

    // ⭐⭐⭐ СПОЧАТКУ ПЕРЕВІРЯЄМО КОМАНДУ /cancel ⭐⭐⭐
    if (strpos($command, '/cancel') === 0) {
        logInfo("CANCEL команда від $user_id: $clean_text");
        handleCancelCommand($chat_id, $user_lang, $clean_text);
        return;
    }

    // ОБРОБКА АДМІН-КОМАНД
    if ($is_admin && in_array($command, [
        '/addadmin', '/removeadmin', '/adminlist', '/setrank', '/debug',
        '/reports', '/report', '/accept', '/reject', '/broadcast'
    ])) {
        logInfo("ADMIN $user_id: $command");
        handleAdminCommand($chat_id, $clean_text, $from, $user_lang, $current_rank, $chat_type === 'private' ? null : $chat_id);
        return;
    }

    // Обробка звичайних команд
    logDebug("USER_CMD $user_id: $command");
    handleUserCommand($chat_id, $clean_text, $from, $user_lang, $is_admin, $current_rank);
}
?>