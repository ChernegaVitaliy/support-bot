<?php

class ConsoleCommands {
    private $db;
    private $config;
    private $botUsername;
    private $languageFiles;

    public function __construct($db, $config, $botUsername) {
        $this->db = $db;
        $this->config = $config;
        $this->botUsername = $botUsername;
        $this->languageFiles = ['uk', 'ru', 'en', 'es', 'de', 'fr', 'it', 'pt', 'zh', 'ja', 'ko', 'ar', 'fa', 'tr', 'pl', 'nl', 'cs', 'sr', 'bg', 'ro', 'hu', 'fi', 'sv', 'da', 'nb', 'hi', 'id', 'vi', 'th', 'el', 'he', 'hr', 'sk', 'uz', 'ms', 'kk', 'ca', 'be'];
    }

    // 🔧 МЕТОД ДЛЯ ОТРИМАННЯ ДЕФОЛТНОГО ВЛАСНИКА
    private function getDefaultOwnerId() {
        $env_path = __DIR__ . '/../.env';
        $default_owner_id = "5720736515"; // значення за замовчуванням
        
        if (file_exists($env_path)) {
            $env_vars = parse_ini_file($env_path);
            $default_owner_id = $env_vars['DEFAULT_OWNER_ID'] ?? $default_owner_id;
        }
        
        return $default_owner_id;
    }

    public function handleCommand($input) {
        $parts = explode(' ', $input);
        $command = strtolower($parts[0]);
        $params = array_slice($parts, 1);

        $methodName = 'command' . ucfirst($command);
        if (method_exists($this, $methodName)) {
            $this->$methodName($params);
        } else {
            $this->commandUnknown($command);
        }
    }

    private function getTerminalWidth() {
        $width = 80;

        if (function_exists('shell_exec')) {
            $stty = shell_exec('stty size 2>/dev/null');
            if ($stty) {
                $parts = explode(' ', $stty);
                if (isset($parts[1])) {
                    $width = (int)$parts[1];
                }
            }

            if ($width === 80) {
                $tput = shell_exec('tput cols 2>/dev/null');
                if ($tput) {
                    $width = (int)$tput;
                }
            }
        }

        if ($width === 80 && isset($_SERVER['COLUMNS'])) {
            $width = (int)$_SERVER['COLUMNS'];
        }

        $width = max(40, min($width, 200));

        return $width;
    }

    private function commandHelp($params) {
        $terminal_width = $this->getTerminalWidth();

        echo "\033[1;36m\n📋 ДОСТУПНІ КОМАНДИ:\n";
        echo str_repeat("─", $terminal_width) . "\n\033[0m";

        $commands = [
            "status      - Статус бота",
            "adminlist   - Детальний список адмінів",
            "addadmin    - Додати адміна",
            "removeadmin - Видалити адміна",
            "setrank     - Змінити ранг адміна",
            "broadcast   - Розсилка повідомлень",
            "finduser    - Пошук користувачів",
            "version     - Інформація про бота",
            "restart     - Гаряче перезавантаження",
            "reload      - Перезавантажити конфіг",
            "clear       - Очистити консоль",
            "exit        - Вийти з бота"
        ];

        foreach ($commands as $command) {
            echo "\033[1;37m" . $command . "\033[0m\n";
        }

        echo "\033[1;36m" . str_repeat("─", $terminal_width) . "\033[0m\n";
    }

    private function commandStatus($params) {
        $memory = round(memory_get_usage(true) / 1024 / 1024, 2);
        $uptime = time() - filectime(__FILE__);
        $usersCount = $this->db->getUsersCount();
        $stats = $this->db->getStats();
        $default_owner_id = $this->getDefaultOwnerId();

        $terminal_width = $this->getTerminalWidth();
        $max_line_length = $terminal_width - 4;

        echo "\033[1;32m\n📊 СТАТУС БОТА:\n";

        $lines = [
            "├─ 💾 Пам'ять: {$memory}MB",
            "├─ 🕒 Аптайм: " . gmdate("H:i:s", $uptime),
            "├─ 👥 Користувачі: {$usersCount}",
            "├─ 👨‍💼 Адміни: {$stats['total_admins']}",
            "├─ 👑 Дефолт власник: {$default_owner_id}",
            "├─ 📨 Репорти: {$stats['total_reports']}",
            "│   ├─ ⏳ Очікують: {$stats['pending_reports']}",
            "│   ├─ ✅ Прийнято: {$stats['accepted_reports']}",
            "│   └─ ❌ Відхилено: {$stats['rejected_reports']}",
            "└─ 🟢 Статус: Активний"
        ];

        foreach ($lines as $line) {
            if (strlen($line) > $max_line_length) {
                $line = substr($line, 0, $max_line_length - 3) . "...";
            }
            echo $line . "\n";
        }

        echo "\033[0m";
    }

    private function commandAdminlist($params) {
        $admins = $this->db->getAllAdmins();
        $default_owner_id = $this->getDefaultOwnerId();

        $terminal_width = $this->getTerminalWidth();
        $line_char = "─";

        echo "\033[1;35m\n👨‍💼 ДЕТАЛЬНИЙ СПИСОК АДМІНІСТРАТОРІВ:\n";
        echo str_repeat($line_char, $terminal_width) . "\n";

        if (empty($admins)) {
            echo "└─ Адміністраторів не знайдено\n";
            return;
        }

        foreach ($admins as $index => $admin) {
            $number = $index + 1;
            $total = count($admins);

            $is_default_owner = ($admin['user_id'] === $default_owner_id);
            $default_badge = $is_default_owner ? " \033[1;31m⚡\033[0m" : "";

            $rank_emoji = match($admin['rank']) {
                'owner' => '👑',
                'admin' => '⭐',
                'moderator' => '🛡️',
                default => '🔹'
            };

            $rank_color = match($admin['rank']) {
                'owner' => "\033[1;33m",
                'admin' => "\033[1;36m",
                'moderator' => "\033[1;32m",
                default => "\033[1;37m"
            };

            echo "{$rank_color}{$rank_emoji} АДМІН #{$number}/{$total}{$default_badge}\033[0m\n";
            
            if ($is_default_owner) {
                echo "├─ \033[1;31m⚡ ГОЛОВНИЙ ВЛАСНИК (НЕЗАЧИПНИЙ)\033[0m\n";
            }
            
            echo "├─ \033[1;37mID:\033[0m {$admin['user_id']}\n";
            echo "├─ \033[1;37mІм'я:\033[0m " . ($admin['first_name'] ?: 'Не вказано') . "\n";
            echo "├─ \033[1;37mUsername:\033[0m " . ($admin['username'] ? "@{$admin['username']}" : 'Не вказано') . "\n";
            echo "├─ \033[1;37mРанг:\033[0m {$rank_color}{$admin['rank']}\033[0m\n";
            echo "└─ \033[1;37mДоданий:\033[0m " . date("d.m.Y H:i", strtotime($admin['added_at'])) . "\n";

            if ($index < count($admins) - 1) {
                echo str_repeat($line_char, $terminal_width) . "\n";
            }
        }

        echo str_repeat($line_char, $terminal_width) . "\n";
        echo "📊 \033[1;32mВсього адміністраторів: " . count($admins) . "\033[0m\n";

        $rank_stats = [];
        foreach ($admins as $admin) {
            $rank = $admin['rank'];
            $rank_stats[$rank] = ($rank_stats[$rank] ?? 0) + 1;
        }

        echo "📈 \033[1;33mСтатистика:\033[0m ";
        $stats_parts = [];
        foreach ($rank_stats as $rank => $count) {
            $rank_emoji = match($rank) {
                'owner' => '👑',
                'admin' => '⭐',
                'moderator' => '🛡️',
                default => '🔹'
            };
            $stats_parts[] = "{$rank_emoji} {$rank}: {$count}";
        }
        echo implode(', ', $stats_parts) . "\n";
    }

    private function commandAddadmin($params) {
        if (count($params) < 2) {
            echo "\033[1;31m❌ Використання: addadmin [user_id/@username] [rank]\033[0m\n";
            echo "   Доступні ранги: owner, admin, moderator\n";
            echo "   Приклади:\n";
            echo "   addadmin 123456789 owner\n";
            echo "   addadmin @username moderator\n";
            return;
        }

        $identifier = $params[0];
        $rank = strtolower($params[1]);

        $allowed_ranks = ['owner', 'admin', 'moderator'];
        if (!in_array($rank, $allowed_ranks)) {
            echo "\033[1;31m❌ Невірний ранг! Доступні: " . implode(', ', $allowed_ranks) . "\033[0m\n";
            return;
        }

        echo "\033[1;36m👨‍💼 ДОДАВАННЯ АДМІНА...\n";
        echo "├─ Ідентифікатор: {$identifier}\n";
        echo "├─ Ранг: {$rank}\n";
        echo "└─ Перевіряю...\033[0m\n";

        $user_id = null;
        $username = null;
        $first_name = "Admin";

        if (is_numeric($identifier)) {
            $user_id = $identifier;
            echo "🔹 Використовую числовий ID: {$user_id}\n";

            $user = $this->db->getUserById($user_id);
            if ($user) {
                $first_name = $user['first_name'] ?? $first_name;
                $username = $user['username'] ?? $username;
            }
        } elseif (strpos($identifier, '@') === 0) {
            $username = substr($identifier, 1);
            echo "🔹 Використовую username: @{$username}\n";
            echo "❕ Для username потрібен chat_id - спробую знайти...\n";

            $user = $this->findUserByUsername($username);
            if ($user) {
                $user_id = $user['user_id'];
                $first_name = $user['first_name'] ?? $first_name;
                echo "✅ Знайдено user_id: {$user_id}\n";
            } else {
                echo "\033[1;31m❌ Користувача @{$username} не знайдено в базі!\n";
                echo "💡 Користувач повинен спочатку написати боту\033[0m\n";
                return;
            }
        } else {
            echo "\033[1;31m❌ Невірний формат ідентифікатора!\n";
            echo "💡 Використовуйте: 123456789 або @username\033[0m\n";
            return;
        }

        if ($this->db->isAdmin($user_id)) {
            $existing_admin = $this->db->getAdminByUserId($user_id);
            echo "\033[1;33m⚠️  Цей користувач вже є адміном!\n";
            echo "├─ Поточний ранг: {$existing_admin['rank']}\n";
            echo "├─ Username: " . ($existing_admin['username'] ? "@{$existing_admin['username']}" : 'Не вказано') . "\n";
            echo "└─ Доданий: {$existing_admin['added_at']}\033[0m\n";

            echo "Оновити ранг на '{$rank}'? (y/n): ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);
            fclose($handle);

            if (trim(strtolower($line)) === 'y') {
                if ($existing_admin['rank'] === $rank) {
                    echo "\033[1;33m⚠️  Адмін вже має ранг '{$rank}'!\033[0m\n";
                    return;
                }

                $result = $this->db->setAdminRank($user_id, $rank);
                if ($result) {
                    echo "\033[1;32m✅ РАНГ ОНОВЛЕНО!\n";
                    echo "├─ User ID: {$user_id}\n";
                    echo "├─ Username: " . ($username ? "@{$username}" : 'Не вказано') . "\n";
                    echo "├─ Новий ранг: {$rank}\n";
                    echo "└─ Статус: Активний\033[0m\n";

                    $this->sendAdminNotification($user_id, "rank_updated", $rank);
                } else {
                    echo "\033[1;31m❌ Не вдалося оновити ранг!\033[0m\n";
                }
            }
            return;
        }

        $result = $this->db->addAdmin($user_id, $username, $first_name, $rank);

        if ($result) {
            echo "\033[1;32m✅ АДМІНА УСПІШНО ДОДАНО!\n";
            echo "├─ User ID: {$user_id}\n";
            echo "├─ Username: " . ($username ? "@{$username}" : 'Не вказано') . "\n";
            echo "├─ Ім'я: " . ($first_name ?: 'Не вказано') . "\n";
            echo "├─ Ранг: {$rank}\n";
            echo "└─ Статус: Активний\033[0m\n";

            $this->sendAdminNotification($user_id, "added", $rank);
            $this->commandAdmins([]);
        } else {
            echo "\033[1;31m❌ ПОМИЛКА! Не вдалося додати адміна\033[0m\n";
        }
    }

    private function commandRemoveadmin($params) {
        if (empty($params)) {
            echo "\033[1;31m❌ Використання: removeadmin [user_id/@username]\033[0m\n";
            echo "   Приклади:\n";
            echo "   removeadmin 123456789\n";
            echo "   removeadmin @username\n";
            return;
        }

        $identifier = $params[0];

        echo "\033[1;36m🗑️  ВИДАЛЕННЯ АДМІНА...\n";
        echo "├─ Ідентифікатор: {$identifier}\n";
        echo "└─ Перевіряю...\033[0m\n";

        $user_id = null;
        $admin = null;

        if (is_numeric($identifier)) {
            $user_id = $identifier;
            $admin = $this->db->getAdminByUserId($user_id);
        } elseif (strpos($identifier, '@') === 0) {
            $username = substr($identifier, 1);
            $admin = $this->db->getAdminByUsername($username);
            if ($admin) {
                $user_id = $admin['user_id'];
            }
        }

        if (!$admin) {
            echo "\033[1;31m❌ Адмін не знайдений!\033[0m\n";
            return;
        }

        // 🔒 ПЕРЕВІРКА: Не можна видалити дефолтного власника
        $default_owner_id = $this->getDefaultOwnerId();
        if ($user_id === $default_owner_id) {
            echo "\033[1;31m❌ Не можна видалити головного власника!\n";
            echo "💡 Це захищений акаунт з .env файлу\033[0m\n";
            return;
        }

        echo "🔍 Знайдено адміна:\n";
        echo "├─ User ID: {$admin['user_id']}\n";
        echo "├─ Ім'я: " . ($admin['first_name'] ?: 'Не вказано') . "\n";
        echo "├─ Username: " . ($admin['username'] ? "@{$admin['username']}" : 'Не вказано') . "\n";
        echo "└─ Ранг: {$admin['rank']}\n";

        echo "\n\033[1;33m❓ Ви впевнені, що хочете видалити цього адміна? (y/n): \033[0m";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) !== 'y') {
            echo "🚫 Операція скасована\n";
            return;
        }

        $result = $this->db->removeAdmin($user_id);

        if ($result) {
            echo "\033[1;32m✅ АДМІНА УСПІШНО ВИДАЛЕНО!\n";
            echo "├─ User ID: {$user_id}\n";
            echo "├─ Ім'я: " . ($admin['first_name'] ?: 'Не вказано') . "\n";
            echo "└─ Username: " . ($admin['username'] ? "@{$admin['username']}" : 'Не вказано') . "\033[0m\n";

            $this->sendAdminNotification($user_id, "removed");
            $this->commandAdmins([]);
        } else {
            echo "\033[1;31m❌ ПОМИЛКА! Не вдалося видалити адміна\033[0m\n";
        }
    }

    private function commandSetrank($params) {
        if (count($params) < 2) {
            echo "\033[1;31m❌ Використання: setrank [user_id/@username] [rank]\033[0m\n";
            echo "   Доступні ранги: owner, admin, moderator\n";
            echo "   Приклади:\n";
            echo "   setrank 123456789 admin\n";
            echo "   setrank @username moderator\n";
            return;
        }

        $identifier = $params[0];
        $rank = strtolower($params[1]);

        $allowed_ranks = ['owner', 'admin', 'moderator'];
        if (!in_array($rank, $allowed_ranks)) {
            echo "\033[1;31m❌ Невірний ранг! Доступні: " . implode(', ', $allowed_ranks) . "\033[0m\n";
            return;
        }

        echo "\033[1;36m🔧 ЗМІНА РАНГУ АДМІНА...\n";
        echo "├─ Ідентифікатор: {$identifier}\n";
        echo "├─ Новий ранг: {$rank}\n";
        echo "└─ Перевіряю...\033[0m\n";

        $user_id = null;
        $admin = null;

        if (is_numeric($identifier)) {
            $user_id = $identifier;
            $admin = $this->db->getAdminByUserId($user_id);
        } elseif (strpos($identifier, '@') === 0) {
            $username = substr($identifier, 1);
            $admin = $this->db->getAdminByUsername($username);
            if ($admin) {
                $user_id = $admin['user_id'];
            }
        }

        if (!$admin) {
            echo "\033[1;31m❌ Адмін не знайдений!\033[0m\n";
            return;
        }

        // 🔒 ПЕРЕВІРКА: Не можна змінити ранг дефолтного власника
        $default_owner_id = $this->getDefaultOwnerId();
        if ($user_id === $default_owner_id) {
            echo "\033[1;31m❌ Не можна змінити ранг головного власника!\n";
            echo "💡 Це захищений акаунт з .env файлу\033[0m\n";
            return;
        }

        echo "🔍 Знайдено адміна:\n";
        echo "├─ User ID: {$admin['user_id']}\n";
        echo "├─ Ім'я: " . ($admin['first_name'] ?: 'Не вказано') . "\n";
        echo "├─ Username: " . ($admin['username'] ? "@{$admin['username']}" : 'Не вказано') . "\n";
        echo "└─ Поточний ранг: {$admin['rank']}\n";

        if ($admin['rank'] === $rank) {
            echo "\033[1;33m⚠️  Адмін вже має ранг '{$rank}'!\n";
            echo "💡 Немає потреби змінювати на той самий ранг\033[0m\n";
            return;
        }

        echo "\n\033[1;33m❓ Змінити ранг з '{$admin['rank']}' на '{$rank}'? (y/n): \033[0m";
        $handle = fopen("php://stdin", "r");
        $line = fgets($handle);
        fclose($handle);

        if (trim(strtolower($line)) !== 'y') {
            echo "🚫 Операція скасована\n";
            return;
        }

        $result = $this->db->setAdminRank($user_id, $rank);

        if ($result) {
            echo "\033[1;32m✅ РАНГ УСПІШНО ЗМІНЕНО!\n";
            echo "├─ User ID: {$user_id}\n";
            echo "├─ Ім'я: " . ($admin['first_name'] ?: 'Не вказано') . "\n";
            echo "├─ Старий ранг: {$admin['rank']}\n";
            echo "└─ Новий ранг: {$rank}\033[0m\n";

            $this->sendAdminNotification($user_id, "rank_updated", $rank);
            $this->commandAdmins([]);
        } else {
            echo "\033[1;31m❌ ПОМИЛКА! Не вдалося змінити ранг\033[0m\n";
        }
    }

    private function commandFinduser($params) {
        if (empty($params)) {
            echo "\033[1;31m❌ Використання: finduser [критерій пошуку]\033[0m\n";
            echo "   Критерії пошуку:\n";
            echo "   @username - пошук по юзернейму\n";
            echo "   123456789 - пошук по ID\n";
            echo "   \"Ім'я\" - пошук по імені\n";
            echo "   * - всі користувачі\n";
            echo "\n   Приклади:\n";
            echo "   finduser @username\n";
            echo "   finduser 5720736515\n";
            echo "   finduser \"John\"\n";
            echo "   finduser *\n";
            return;
        }

        $search_term = implode(' ', $params);
        $terminal_width = $this->getTerminalWidth();

        echo "\033[1;36m🔍 ПОШУК КОРИСТУВАЧА: '{$search_term}'\n";
        echo str_repeat("─", $terminal_width) . "\033[0m\n";

        $users = [];

        try {
            if ($search_term === '*') {
                // Всі користувачі
                $users = $this->db->getAllUsers();
                echo "📋 Вивожу всіх користувачів...\n";
            } elseif (is_numeric($search_term)) {
                // Пошук по ID
                $user = $this->db->getUserById($search_term);
                if ($user) {
                    $users[] = $user;
                }
                echo "🔎 Пошук по ID: {$search_term}\n";
            } elseif (strpos($search_term, '@') === 0) {
                // Пошук по юзернейму
                $username = substr($search_term, 1);
                $user = $this->findUserByUsername($username);
                if ($user) {
                    $users[] = $user;
                }
                echo "🔎 Пошук по юзернейму: @{$username}\n";
            } else {
                // Пошук по імені
                $stmt = $this->db->query("SELECT * FROM users WHERE first_name LIKE ?", true, ["%{$search_term}%"]);
                $users = $stmt ?: [];
                echo "🔎 Пошук по імені: '{$search_term}'\n";
            }

            if (empty($users)) {
                echo "\033[1;31m❌ Користувачів не знайдено!\033[0m\n";
                return;
            }

            echo "✅ Знайдено користувачів: " . count($users) . "\n\n";

            // Отримуємо список адмінів для позначення
            $admins = $this->db->getAllAdmins();
            $admin_ids = array_column($admins, 'user_id');

            foreach ($users as $index => $user) {
                // ВИПРАВЛЕННЯ: використовуємо user_id замість chat_id
                $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
                $is_admin = in_array($user_id, $admin_ids);
                $admin_badge = $is_admin ? " \033[1;33m[АДМІН]\033[0m" : "";
                
                echo "\033[1;35m👤 КОРИСТУВАЧ #" . ($index + 1) . "{$admin_badge}\033[0m\n";
                echo "├─ \033[1;37mID:\033[0m {$user_id}\n";
                echo "├─ \033[1;37mІм'я:\033[0m " . ($user['first_name'] ?: 'Не вказано') . "\n";
                echo "├─ \033[1;37mЮзернейм:\033[0m " . ($user['username'] ? "@{$user['username']}" : 'Не вказано') . "\n";
                
                // Мова користувача
                $user_language = $this->db->getUserLanguage($user_id) ?? 'uk';
                echo "├─ \033[1;37mМова:\033[0m {$user_language}\n";
                
                // Статистика репортів
                $reports_count = $this->getUserReportsCount($user_id);
                echo "├─ \033[1;37mРепортів:\033[0m {$reports_count}\n";
                
                // Дата реєстрації
                $created = date("d.m.Y H:i", strtotime($user['created_at']));
                echo "└─ \033[1;37mЗареєстрований:\033[0m {$created}\n";

                // Додаткова інформація для адмінів
                if ($is_admin) {
                    $admin_info = $this->db->getAdminByUserId($user_id);
                    if ($admin_info) {
                        $rank_emoji = match($admin_info['rank']) {
                            'owner' => '👑',
                            'admin' => '⭐',
                            'moderator' => '🛡️',
                            default => '🔹'
                        };
                        echo "   └─ \033[1;33m{$rank_emoji} Ранг: {$admin_info['rank']}\033[0m\n";
                    }
                }

                // Останні репорти (якщо є)
                if ($reports_count > 0) {
                    $last_reports = $this->getUserLastReports($user_id, 3);
                    echo "   └─ \033[1;36m📨 Останні репорти:\033[0m\n";
                    foreach ($last_reports as $report) {
                        $status_emoji = match($report['status']) {
                            'pending' => '⏳',
                            'accepted' => '✅',
                            'rejected' => '❌',
                            default => '🔹'
                        };
                        $date = date("d.m.Y", strtotime($report['created_at']));
                        echo "      └─ {$status_emoji} #{$report['id']} на {$report['reported_nick']} ({$date})\n";
                    }
                }

                if ($index < count($users) - 1) {
                    echo str_repeat("─", $terminal_width) . "\n";
                }
            }

            echo "\n" . str_repeat("─", $terminal_width) . "\n";
            
            // Загальна статистика
            $admins_count = count(array_filter($users, function($user) use ($admin_ids) {
                $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
                return in_array($user_id, $admin_ids);
            }));
            $regular_count = count($users) - $admins_count;
            
            echo "📊 \033[1;32mПІДСУМОК:\033[0m\n";
            echo "├─ Знайдено: " . count($users) . " користувачів\n";
            echo "├─ Адмінів: {$admins_count}\n";
            echo "├─ Звичайних: {$regular_count}\n";
            
            $total_reports = array_sum(array_map(function($user) {
                $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
                return $this->getUserReportsCount($user_id);
            }, $users));
            
            echo "└─ Всього репортів: {$total_reports}\n";

        } catch (Exception $e) {
            echo "\033[1;31m❌ Помилка пошуку: " . $e->getMessage() . "\033[0m\n";
        }
    }

    // Допоміжні методи для пошуку
    private function getUserReportsCount($user_id) {
        try {
            $stmt = $this->db->query("SELECT COUNT(*) as count FROM reports WHERE user_id = ?", false, [$user_id]);
            return $stmt ? $stmt['count'] : 0;
        } catch (Exception $e) {
            return 0;
        }
    }

    private function getUserLastReports($user_id, $limit = 3) {
        try {
            $stmt = $this->db->query("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", true, [$user_id, $limit]);
            return $stmt ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    private function commandBroadcast($params) {
        if (empty($params)) {
            echo "\033[1;31m❌ Використання: broadcast [повідомлення]\033[0m\n";
            echo "   Додаткові опції:\n";
            echo "   --lang=uk - тільки користувачам з певною мовою\n";
            echo "   --active - тільки активним користувачам\n";
            echo "   --admins - тільки адмінам\n";
            echo "   --users - тільки звичайним користувачам\n";
            echo "   --test - тестовий режим (не відправляти)\n";
            echo "\n   Приклади:\n";
            echo "   broadcast Привіт усім! --lang=uk\n";
            echo "   broadcast Hello admins! --admins\n";
            echo "   broadcast Важливе повідомлення --active\n";
            echo "   broadcast Test --users --lang=en --test\n";
            return;
        }

        $message = implode(' ', $params);
        $options = [
            'lang' => null,
            'active_only' => false,
            'admins_only' => false,
            'users_only' => false,
            'test' => false
        ];

        // Обробка параметрів
        $filteredParams = [];
        foreach ($params as $param) {
            if (strpos($param, '--lang=') === 0) {
                $options['lang'] = substr($param, 7);
            } elseif ($param === '--active') {
                $options['active_only'] = true;
            } elseif ($param === '--admins') {
                $options['admins_only'] = true;
            } elseif ($param === '--users') {
                $options['users_only'] = true;
            } elseif ($param === '--test') {
                $options['test'] = true;
            } else {
                $filteredParams[] = $param;
            }
        }

        $message = implode(' ', $filteredParams);

        echo "\033[1;36m📢 РОЗСИЛКА: {$message}\n";

        // Виводимо опції
        if ($options['lang']) {
            echo "🌍 Мова: {$options['lang']}\n";
        }
        if ($options['active_only']) {
            echo "👥 Тільки активні користувачі\n";
        }
        if ($options['admins_only']) {
            echo "👨‍💼 Тільки адміни\n";
        }
        if ($options['users_only']) {
            echo "👤 Тільки звичайні користувачі\n";
        }
        if ($options['test']) {
            echo "🧪 ТЕСТОВИЙ РЕЖИМ\n";
        }

        echo "⏳ Отримую список...\033[0m\n";

        // Отримуємо всіх користувачів
        $allUsers = $this->db->getAllUsers();
        
        // Фільтруємо користувачів за опціями
        $filteredUsers = [];
        $adminsList = $this->db->getAllAdmins();
        $adminIds = array_column($adminsList, 'user_id');

        foreach ($allUsers as $user) {
            $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
            $isAdmin = in_array($user_id, $adminIds);
            
            // Фільтр по типу користувача
            if ($options['admins_only'] && !$isAdmin) continue;
            if ($options['users_only'] && $isAdmin) continue;
            
            // Фільтр по мові
            if ($options['lang']) {
                $userLang = $this->db->getUserLanguage($user_id) ?? 'uk';
                if ($userLang !== $options['lang']) continue;
            }
            
            // Фільтр по активності
            if ($options['active_only']) {
                $hasReports = $this->db->userHasReports($user_id);
                if (!$hasReports) continue;
            }
            
            $filteredUsers[] = $user;
        }

        if (empty($filteredUsers)) {
            echo "\033[1;31m❌ Не знайдено користувачів для розсилки з вказаними фільтрами\033[0m\n";
            return;
        }

        // Статистика
        $totalUsers = count($allUsers);
        $targetUsers = count($filteredUsers);
        $adminsCount = 0;
        $usersCount = 0;
        
        foreach ($filteredUsers as $user) {
            $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
            if (in_array($user_id, $adminIds)) {
                $adminsCount++;
            } else {
                $usersCount++;
            }
        }

        echo "\n🎯 ЦІЛЬОВА АУДИТОРІЯ:\n";
        echo "├─ Всього користувачів: {$totalUsers}\n";
        echo "├─ Отримають розсилку: {$targetUsers}\n";
        echo "├─ Адмінів: {$adminsCount}\n";
        echo "└─ Звичайних користувачів: {$usersCount}\n\n";

        if ($options['test']) {
            echo "🧪 ТЕСТОВИЙ РЕЖИМ - перші 10 користувачів:\n";
            foreach (array_slice($filteredUsers, 0, 10) as $user) {
                $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
                $userType = in_array($user_id, $adminIds) ? '👨‍💼 АДМІН' : '👤 КОРИСТУВАЧ';
                $userLang = $this->db->getUserLanguage($user_id) ?? 'uk';
                echo "   - {$userType}: " . ($user['first_name'] ?: 'Без імені') . 
                    " (" . ($user['username'] ? "@{$user['username']}" : 'без юзернейму') . 
                    ") [{$userLang}]\n";
            }
            if (count($filteredUsers) > 10) {
                echo "   ... і ще " . (count($filteredUsers) - 10) . " користувачів\n";
            }
            echo "✅ Тест завершено!\n";
            return;
        }

        echo "\033[1;33m❗ ПОЧИНАЮ РОЗСИЛКУ ДЛЯ {$targetUsers} КОРИСТУВАЧІВ...\n";
        echo "⏹️  Для скасування натисніть Ctrl+C\033[0m\n";
        sleep(2); // Даємо час на скасування

        $success = 0;
        $failed = 0;
        $adminsSent = 0;
        $usersSent = 0;

        echo "\n";

        foreach ($filteredUsers as $index => $user) {
            $user_id = $user['user_id'] ?? $user['chat_id'] ?? 'N/A';
            $isAdmin = in_array($user_id, $adminIds);
            $userType = $isAdmin ? '👨‍💼' : '👤';
            $userInfo = ($user['first_name'] ?: 'Без імені') . " (" . ($user['username'] ? "@{$user['username']}" : 'без юзернейму') . ")";

            echo "[" . ($index + 1) . "/{$targetUsers}] {$userType} {$userInfo}... ";

            $result = $this->sendTelegramMessage($user_id, $message);

            if ($result) {
                echo "✅\n";
                $success++;
                if ($isAdmin) {
                    $adminsSent++;
                } else {
                    $usersSent++;
                }
            } else {
                echo "❌\n";
                $failed++;
            }

            // Затримка між повідомленнями
            usleep(500000); // 0.5 секунди
        }

        // Результати
        echo "\033[1;32m\n✅ РОЗСИЛКА ЗАВЕРШЕНА!\n";
        echo "├─ Успішно відправлено: {$success}\n";
        echo "│   ├─ Адмінам: {$adminsSent}\n";
        echo "│   └─ Користувачам: {$usersSent}\n";
        echo "├─ Не вдалось відправити: {$failed}\n";
        echo "└─ Всього спроб: " . ($success + $failed) . "\033[0m\n";

        // Додаткова статистика
        if ($success > 0) {
            $successRate = round(($success / ($success + $failed)) * 100, 1);
            echo "📊 Успішність: {$successRate}%\n";
        }
    }

    private function commandVersion($params) {
        $default_owner_id = $this->getDefaultOwnerId();
        
        echo "\033[1;35m\n🤖 ІНФОРМАЦІЯ ПРО БОТА:\n";
        echo "├─ Версія: 2.0\n";
        echo "├─ Автор: Твоє ім'я\n";
        echo "├─ Telegram: @{$this->botUsername}\n";
        echo "├─ 👑 Дефолт власник: {$default_owner_id}\n";
        echo "└─ База даних: SQLite\033[0m\n";
    }

    private function commandRestart($params) {
        echo "\033[1;33m🔄 ГАРЯЧЕ ПЕРЕЗАВАНТАЖЕННЯ ВСІХ ПРОЦЕСІВ...\033[0m\n";

        echo "🔧 Перезавантажую конфігурацію...\n";
        $this->reloadConfiguration();

        echo "🗄️ Перепідключаю базу даних...\n";
        global $db, $db_path;
        $db = new Database($db_path);

        echo "🌍 Перезавантажую мовні файли...\n";
        global $languages;
        $languages = [];
        foreach ($this->languageFiles as $lang) {
            $filePath = __DIR__ . "/../languages/{$lang}.json";
            if (file_exists($filePath)) {
                $languages[$lang] = json_decode(file_get_contents($filePath), true);
            }
        }

        echo "🔄 Скидаю всі сесії...\n";
        global $report_sessions;
        $report_sessions = [];

        echo "📡 Скидаю last_update_id...\n";
        global $last_update_id;
        $last_update_id = 0;

        if (function_exists('opcache_reset')) {
            opcache_reset();
            echo "🧹 Очищую кеш PHP...\n";
        }

        echo "\033[1;32m✅ ВСІ ПРОЦЕСИ ПЕРЕЗАВАНТАЖЕНО!\n";
        echo "🤖 Бот оновлено без перезапуску\n";
        echo "🕒 Час: " . date('H:i:s') . "\033[0m\n";

        $this->commandStatus([]);
    }

    private function commandReload($params) {
        echo "\033[1;33m🔄 Перезавантаження конфігурації...\033[0m\n";
        $this->reloadConfiguration();
    }

    private function reloadConfiguration() {
        global $env_file, $env_vars, $token, $apiURL, $botUsername, $botName;

        if (file_exists($env_file)) {
            $env_vars = parse_ini_file($env_file);
            $new_token = $env_vars['BOT_TOKEN'] ?? $token;

            if ($new_token !== $token) {
                echo "🔑 Токен оновлено: " . substr($new_token, 0, 10) . "...\n";
                $token = $new_token;
                $apiURL = "https://api.telegram.org/bot$token/";
            }
        }

        $botInfo = $this->getBotInfo();
        if ($botInfo) {
            $botUsername = $botInfo['username'];
            $botName = $botInfo['first_name'];

            echo "\033[1;32m✅ Конфігурацію перезавантажено!\n";
            echo "🤖 Бот: @{$botUsername} ({$botName})\033[0m\n";
        } else {
            echo "\033[1;31m❌ Помилка: Токен не працює!\n";
            echo "📝 Перевірте .env файл\033[0m\n";
        }
    }

    private function getBotInfo() {
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

    private function commandClear($params) {
        $terminal_width = $this->getTerminalWidth();
        $line_char = "═";

        echo "\033[2J\033[1;1H";
        echo "\033[1;35m" . str_repeat($line_char, $terminal_width) . "\n";
        echo "🤖 TELEGRAM BOT CONSOLE\n";
        echo str_repeat($line_char, $terminal_width) . "\033[0m\n\n";
    }

    private function commandExit($params) {
        echo "\033[1;31m👋 Завершення роботи бота...\033[0m\n";
        exit(0);
    }

    private function commandQuit($params) {
        $this->commandExit($params);
    }

    private function commandAdmins($params) {
        $this->commandAdminlist($params);
    }

    private function commandUnknown($command) {
        echo "\033[1;31m❌ Невідома команда: {$command}\n";
        echo "💡 Введіть 'help' для списку команд\033[0m\n";
    }

    private function findUserByUsername($username) {
        try {
            $user = $this->db->query("SELECT user_id, username, first_name, created_at FROM users WHERE username = ?", false, [$username]);
            return $user;
        } catch (Exception $e) {
            echo "❌ Помилка пошуку користувача: " . $e->getMessage() . "\n";
            return null;
        }
    }

    private function sendAdminNotification($user_id, $action, $rank = null) {
        $user_language = $this->db->getUserLanguage($user_id) ?? 'uk';

        $messages = [
            'added' => t('you_appointed_admin', $user_language, [$rank ? $rank : 'moderator']),
            'rank_updated' => t('your_rank_updated', $user_language, [$rank ? $rank : 'moderator']),
            'removed' => t('you_removed_from_admins', $user_language)
        ];

        $message = $messages[$action] ?? t('admin_status_updated', $user_language);

        echo "📨 Відправляю сповіщення адміну (мова: {$user_language})... ";
        $result = $this->sendTelegramMessage($user_id, $message);

        if ($result) {
            echo "✅\n";
        } else {
            echo "❌ (користувач заблокував бота)\n";
        }
    }

    private function sendTelegramMessage($chatId, $message) {
        global $token;

        if (empty($token)) {
            echo " [NO TOKEN] ";
            return false;
        }

        $apiUrl = "https://api.telegram.org/bot{$token}/sendMessage";

        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'HTML'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode !== 200) {
            $error = curl_error($ch);
            echo " [HTTP: {$httpCode}] ";
            if ($error) echo " [CURL: {$error}] ";
        }

        curl_close($ch);

        return $httpCode === 200;
    }
}

if (!function_exists('readConsoleInput')) {
    function readConsoleInput() {
        $read = [STDIN];
        $write = [];
        $except = [];

        if (stream_select($read, $write, $except, 0, 100000) > 0) {                                                   
            return trim(fgets(STDIN));
        }
        return null;
    }
}
?>