<?php
class Database {
    private $pdo;
    private $dbPath;

    public function __construct($dbPath) {
        $this->dbPath = $dbPath;
        $this->connect();
        $this->updateDatabaseStructure();
        $this->createTables();
        $this->createReportsTable();
        $this->updateReportsTableStructure();
        $this->addDefaultAdmin();
        $this->addLanguageSupport();
        $this->createIndexes();
    }

    private function connect() {
        try {
            // Створюємо директорію якщо не існує
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("PRAGMA foreign_keys = ON");
        } catch (PDOException $e) {
            logError("Помилка підключення до бази даних: " . $e->getMessage());
            die("❌ Помилка підключення до бази даних: " . $e->getMessage());
        }
    }

    // Оновлення структури таблиці reports
    private function updateReportsTableStructure() {
        try {
            $stmt = $this->pdo->prepare("PRAGMA table_info(reports)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $has_processed_by = false;
            $has_processed_at = false;
            $has_proof_type = false;
            $has_file_id = false;

            foreach ($columns as $column) {
                if ($column['name'] === 'processed_by') $has_processed_by = true;
                if ($column['name'] === 'processed_at') $has_processed_at = true;
                if ($column['name'] === 'proof_type') $has_proof_type = true;
                if ($column['name'] === 'file_id') $has_file_id = true;
            }

            // Додаємо відсутні колонки
            if (!$has_processed_by) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN processed_by TEXT");
                logInfo("Додано колонку processed_by");
            }

            if (!$has_processed_at) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN processed_at DATETIME");
                logInfo("Додано колонку processed_at");
            }

            if (!$has_proof_type) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN proof_type TEXT DEFAULT 'text'");
                logInfo("Додано колонку proof_type");
            }

            if (!$has_file_id) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN file_id TEXT");
                logInfo("Додано колонку file_id");
            }

            if (!$has_processed_by || !$has_processed_at || !$has_proof_type || !$has_file_id) {
                logInfo("Структура таблиці reports оновлена");
            }
        } catch (Exception $e) {
            logError("Помилка оновлення структури таблиці reports: " . $e->getMessage());
        }
    }

    private function updateDatabaseStructure() {
        logInfo("Перевірка структури бази даних...");
        try {
            // Перевіряємо чи існує таблиця admins і чи має вона колонку user_id
            $stmt = $this->pdo->prepare("PRAGMA table_info(admins)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $has_user_id = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'user_id') {
                    $has_user_id = true;
                    break;
                }
            }

            if (!$has_user_id) {
                logInfo("Оновлення структури таблиці admins...");

                // Створюємо тимчасову таблицю з правильною структурою
                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS admins_new (
                        user_id TEXT PRIMARY KEY,
                        username TEXT,
                        first_name TEXT,
                        rank TEXT DEFAULT 'moderator',
                        added_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                // Спробуємо скопіювати дані зі старої таблиці
                try {
                    $this->pdo->exec("INSERT INTO admins_new (user_id, username, first_name, rank)
                                     SELECT chat_id, username, first_name, rank FROM admins");
                    logInfo("Дані адмінів успішно перенесені");
                } catch (Exception $e) {
                    logWarning("Не вдалося скопіювати дані адмінів: " . $e->getMessage());
                }

                // Видаляємо стару таблицю
                $this->pdo->exec("DROP TABLE IF EXISTS admins");

                // Перейменовуємо нову таблицю
                $this->pdo->exec("ALTER TABLE admins_new RENAME TO admins");

                logInfo("Структура таблиці admins оновлена");
            }

            // Аналогічно для таблиці users
            $stmt = $this->pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $has_user_id = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'user_id') {
                    $has_user_id = true;
                    break;
                }
            }

            if (!$has_user_id) {
                logInfo("Оновлення структури таблиці users...");

                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS users_new (
                        user_id TEXT PRIMARY KEY,
                        username TEXT,
                        first_name TEXT,
                        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                try {
                    $this->pdo->exec("INSERT INTO users_new (user_id, username, first_name)
                                     SELECT chat_id, username, first_name FROM users");
                    logInfo("Дані користувачів успішно перенесені");
                } catch (Exception $e) {
                    logWarning("Не вдалося скопіювати дані користувачів: " . $e->getMessage());
                }

                $this->pdo->exec("DROP TABLE IF EXISTS users");
                $this->pdo->exec("ALTER TABLE users_new RENAME TO users");

                logInfo("Структура таблиці users оновлена");
            }

        } catch (Exception $e) {
            logError("Помилка перевірки структури: " . $e->getMessage());
        }
    }

    private function createTables() {
        // Створюємо таблицю users (якщо ще не існує)
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                first_name TEXT,
                language TEXT DEFAULT 'uk',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        // Створюємо таблицю admins
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                first_name TEXT,
                rank TEXT DEFAULT 'moderator',
                added_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        logInfo("Таблиці бази даних створені/перевірені");
    }

    // Створення таблиці для репортів
    public function createReportsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS reports (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id TEXT,
                reporter_nick TEXT,
                reported_nick TEXT,
                reason TEXT,
                proof TEXT,
                proof_type TEXT DEFAULT 'text',
                file_id TEXT,
                status TEXT DEFAULT 'pending',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                admin_notes TEXT,
                processed_by TEXT,
                processed_at DATETIME
            )
        ");
        logInfo("Таблиця reports створена/перевірена");
    }

    // 🔧 МЕТОД ДЛЯ ОТРИМАННЯ ДЕФОЛТНОГО ВЛАСНИКА
    public function getDefaultOwnerId() {
        $env_path = __DIR__ . '/.env';
        $default_owner_id = "5720736515"; // значення за замовчуванням
        
        if (file_exists($env_path)) {
            $env_vars = parse_ini_file($env_path);
            $default_owner_id = $env_vars['DEFAULT_OWNER_ID'] ?? $default_owner_id;
        }
        
        return $default_owner_id;
    }

    private function addDefaultAdmin() {
        // Отримуємо з .env замість хардкоду
        $default_owner_id = $this->getDefaultOwnerId();

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE user_id = ?");
        $stmt->execute([$default_owner_id]);
        $exists = $stmt->fetchColumn() > 0;

        if (!$exists) {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO admins (user_id, username, first_name, rank)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$default_owner_id, 'default_admin', 'Default Admin', 'owner']);
            logInfo("Default admin ($default_owner_id) додано як owner");
        }
    }

    // Додаємо мовну підтримку
    public function addLanguageSupport() {
        try {
            // Додаємо колонку language до таблиці users
            $stmt = $this->pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!in_array('language', $columns)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN language TEXT DEFAULT 'uk'");
                logInfo("Додано колонку language до таблиці users");
            }

            // Оновлюємо всіх існуючих користувачів на українську мову
            $this->pdo->exec("UPDATE users SET language = 'uk' WHERE language IS NULL");

        } catch (Exception $e) {
            logError("Помилка додавання мовної підтримки: " . $e->getMessage());
        }
    }

    // Створення індексів для швидкості
    private function createIndexes() {
        try {
            // Індекси для швидкого пошуку
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_user_id ON reports(user_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_language ON users(language)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_admins_rank ON admins(rank)");
            
            logInfo("Індекси створені/перевірені");
        } catch (Exception $e) {
            logError("Помилка створення індексів: " . $e->getMessage());
        }
    }

    // НОВІ МЕТОДИ ДЛЯ МОВИ
    /**
     * Отримати мову користувача
     */
    public function getUserLanguage($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT language FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['language'] ?? null;
        } catch (Exception $e) {
            logError("Помилка отримання мови для $user_id: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Оновити мову користувача
     */
    public function updateUserLanguage($user_id, $language) {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET language = ? WHERE user_id = ?");
            $result = $stmt->execute([$language, $user_id]);
            logDebug("Оновлено мову для $user_id: $language");
            return $result;
        } catch (Exception $e) {
            logError("Помилка оновлення мови для $user_id: " . $e->getMessage());
            return false;
        }
    }

    // Оновлений метод addUser з підтримкою мови
    public function addUser($user_id, $username = null, $first_name = null, $language = 'uk') {
        try {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO users (user_id, username, first_name, language)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $username, $first_name, $language]);
            logDebug("Користувач доданий/оновлений: $user_id @$username, мова: $language");
            return $result;
        } catch (Exception $e) {
            logError("Помилка додавання користувача $user_id: " . $e->getMessage());
            return false;
        }
    }

    // Оновлений метод для збереження репорту
    public function addReport($user_id, $reporter_nick, $reported_nick, $reason, $proof = null, $proof_type = 'text', $file_id = null) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reports (user_id, reporter_nick, reported_nick, reason, proof, proof_type, file_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $reporter_nick, $reported_nick, $reason, $proof, $proof_type, $file_id]);

            if ($result) {
                $report_id = $this->pdo->lastInsertId();
                logInfo("Репорт доданий: ID $report_id від $user_id ($reporter_nick) на $reported_nick, тип: $proof_type");
                return $report_id;
            }
            return false;
        } catch (Exception $e) {
            logError("Помилка додавання репорту: " . $e->getMessage());
            return false;
        }
    }

    // Метод для оновлення статусу репорту з транзакцією
    public function updateReportStatus($report_id, $status, $admin_notes = null, $admin_id = null) {
        try {
            $this->pdo->beginTransaction();
            
            $stmt = $this->pdo->prepare(
                "UPDATE reports SET status = ?, admin_notes = ?, processed_by = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $result = $stmt->execute([$status, $admin_notes, $admin_id, $report_id]);
            
            if ($result && $stmt->rowCount() > 0) {
                $this->pdo->commit();
                logInfo("Статус репорту оновлено: ID $report_id -> $status (адмін: $admin_id)");
                return true;
            } else {
                $this->pdo->rollBack();
                logWarning("Репорт не знайдено для оновлення: ID $report_id");
                return false;
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            logError("Помилка оновлення статусу репорту: " . $e->getMessage());
            return false;
        }
    }

    // Отримати репорт по ID
    public function getReportById($report_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE id = ?");
            $stmt->execute([$report_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            logDebug("Пошук репорту по ID $report_id: " . ($result ? 'знайдено' : 'не знайдено'));
            return $result;
        } catch (Exception $e) {
            logError("Помилка пошуку репорту по ID $report_id: " . $e->getMessage());
            return null;
        }
    }

    // Отримати репорти за статусом
    public function getAllReports($status = null) {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE status = ? ORDER BY created_at DESC");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM reports ORDER BY created_at DESC");
                $stmt->execute();
            }
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            logDebug("Отримано репортів: " . count($result) . " записів");
            return $result;
        } catch (Exception $e) {
            logError("Помилка отримання списку репортів: " . $e->getMessage());
            return [];
        }
    }

    // Отримати всі репорти (для статистики)
    public function getAllReportsForStats() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM reports ORDER BY created_at DESC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Помилка отримання всіх репортів: " . $e->getMessage());
            return [];
        }
    }

    public function isAdmin($user_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn() > 0;
        logDebug("isAdmin для $user_id: " . ($result ? 'Y' : 'N'));
        return $result;
    }

    public function getAdminRank($user_id) {
        logDebug("getAdminRank для: $user_id");

        $stmt = $this->pdo->prepare("SELECT rank FROM admins WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();

        logDebug("getAdminRank результат: " . ($result ?: 'NULL'));
        return $result;
    }

    public function addAdmin($user_id, $username, $first_name, $rank) {
        try {
            // Перевіряємо чи не існує вже адмін з таким user_id
            if ($this->isAdmin($user_id)) {
                logWarning("Адмін з user_id $user_id вже існує");
                return false;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO admins (user_id, username, first_name, rank)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $username, $first_name, $rank]);
            logInfo("Адмін доданий: $user_id @$username ранг: $rank");
            return $result;
        } catch (Exception $e) {
            logError("Помилка додавання адміна: " . $e->getMessage());
            return false;
        }
    }

    public function removeAdmin($user_id, $current_admin_id = null) {
        try {
            $default_owner_id = $this->getDefaultOwnerId();
            
            // Дефолтний власник не може бути видалений НІКОМУ
            if ($user_id === $default_owner_id) {
                logWarning("Спроба видалити дефолтного власника: $user_id");
                return false;
            }

            // Якщо це дефолтний власник видаляє - дозволяємо все
            if ($current_admin_id === $default_owner_id) {
                $stmt = $this->pdo->prepare("DELETE FROM admins WHERE user_id = ?");
                $result = $stmt->execute([$user_id]);

                if ($result) {
                    logInfo("🗑️ Дефолтний власник видалив адміна: $user_id");
                    return true;
                } else {
                    logWarning("Адмін не знайдений для видалення: $user_id");
                    return false;
                }
            }

            // Для інших адмінів - старі обмеження
            $stmt = $this->pdo->prepare("DELETE FROM admins WHERE user_id = ?");
            $result = $stmt->execute([$user_id]);

            if ($result) {
                logInfo("Адмін видалений: $user_id");
                return true;
            } else {
                logWarning("Адмін не знайдений для видалення: $user_id");
                return false;
            }
        } catch (Exception $e) {
            logError("Помилка видалення адміна $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function setAdminRank($user_id, $rank, $current_admin_id = null) {
        try {
            $default_owner_id = $this->getDefaultOwnerId();
            
            // Дефолтний власник не може бути змінений НІКОМУ
            if ($user_id === $default_owner_id) {
                logWarning("Спроба змінити ранг дефолтного власника: $user_id");
                return false;
            }

            // Якщо це дефолтний власник змінює - дозволяємо все
            if ($current_admin_id === $default_owner_id) {
                $stmt = $this->pdo->prepare("UPDATE admins SET rank = ? WHERE user_id = ?");
                $result = $stmt->execute([$rank, $user_id]);

                if ($result) {
                    logInfo("👑 Дефолтний власник змінив ранг: $user_id -> $rank");
                    return true;
                } else {
                    logWarning("Адмін не знайдений для оновлення рангу: $user_id");
                    return false;
                }
            }

            // Для інших адмінів - старі обмеження
            $stmt = $this->pdo->prepare("UPDATE admins SET rank = ? WHERE user_id = ?");
            $result = $stmt->execute([$rank, $user_id]);

            if ($result) {
                logInfo("Ранг оновлено: $user_id -> $rank");
                return true;
            } else {
                logWarning("Адмін не знайдений для оновлення рангу: $user_id");
                return false;
            }
        } catch (Exception $e) {
            logError("Помилка оновлення рангу $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByUsername($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, first_name, language FROM users WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Помилка пошуку користувача за username @$username: " . $e->getMessage());
            return null;
        }
    }

    public function getAllAdmins() {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM admins ORDER BY
                CASE rank
                    WHEN 'owner' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'moderator' THEN 3
                    ELSE 4
                END");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            logDebug("Отримано список адмінів: " . count($result) . " записів");
            return $result;
        } catch (Exception $e) {
            logError("Помилка отримання списку адмінів: " . $e->getMessage());
            return [];
        }
    }

    public function getStats() {
        try {
            $stats = [];

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $stats['total_users'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins");
            $stmt->execute();
            $stats['total_admins'] = $stmt->fetchColumn();

            // Статистика репортів
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports");
            $stmt->execute();
            $stats['total_reports'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
            $stmt->execute();
            $stats['pending_reports'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'accepted'");
            $stmt->execute();
            $stats['accepted_reports'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = 'rejected'");
            $stmt->execute();
            $stats['rejected_reports'] = $stmt->fetchColumn();

            logDebug("Статистика: users=" . $stats['total_users'] . " admins=" . $stats['total_admins'] . " reports=" . $stats['total_reports']);
            return $stats;
        } catch (Exception $e) {
            logError("Помилка отримання статистики: " . $e->getMessage());
            return [
                'total_users' => 0,
                'total_admins' => 0,
                'total_reports' => 0,
                'pending_reports' => 0,
                'accepted_reports' => 0,
                'rejected_reports' => 0
            ];
        }
    }

    public function getAdminByUserId($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            logDebug("Пошук адміна по user_id $user_id: " . ($result ? 'знайдено' : 'не знайдено'));
            return $result;
        } catch (Exception $e) {
            logError("Помилка пошуку адміна по user_id $user_id: " . $e->getMessage());
            return null;
        }
    }

    // Оновлення інформації адміна при зміні username (ТІЛЬКИ ЯКЩО ЗМІНИВСЯ)
    public function updateAdminInfo($user_id, $username, $first_name) {
        try {
            $stmt = $this->pdo->prepare("UPDATE admins SET username = ?, first_name = ? WHERE user_id = ?");
            $result = $stmt->execute([$username, $first_name, $user_id]);
            return $result;
        } catch (Exception $e) {
            logError("Помилка оновлення інформації адміна для $user_id: " . $e->getMessage());
            return false;
        }
    }

    // Старі методи для сумісності
    public function getAdminByChatId($chat_id) {
        return $this->getAdminByUserId($chat_id);
    }

    public function getAdminByIdentifier($identifier) {
        if (empty($identifier)) {
            return null;
        }

        // Якщо починається з @ - шукаємо по username (без @)
        if (strpos($identifier, '@') === 0) {
            $username = substr($identifier, 1);
            return $this->getAdminByUsername($username);
        }
        // Якщо це числовий ID
        elseif (is_numeric($identifier)) {
            return $this->getAdminByUserId((string)$identifier);
        }
        // Інакше шукаємо як username (без @)
        else {
            return $this->getAdminByUsername($identifier);
        }
    }

    public function getAdminByUsername($username) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Помилка пошуку адміна по username @$username: " . $e->getMessage());
            return null;
        }
    }

    public function updateAdminChatId($username, $chat_id, $first_name) {
        try {
            $stmt = $this->pdo->prepare("UPDATE admins SET user_id = ?, first_name = ? WHERE username = ? AND user_id IS NULL");
            $result = $stmt->execute([$chat_id, $first_name, $username]);
            if ($result) {
                logInfo("Оновлено user_id для @$username: $chat_id");
            }
            return $result;
        } catch (Exception $e) {
            logError("Помилка оновлення user_id для @$username: " . $e->getMessage());
            return false;
        }
    }

    public function updateAdminUsername($chat_id, $username, $first_name) {
        return $this->updateAdminInfo($chat_id, $username, $first_name);
    }

    // Додайте ці методи в ваш клас Database
    public function addReportMedia($report_id, $file_id, $media_type) {
        $stmt = $this->pdo->prepare("INSERT INTO report_media (report_id, file_id, media_type) VALUES (?, ?, ?)");
        return $stmt->execute([$report_id, $file_id, $media_type]);
    }

    public function getReportMedia($report_id) {
        $stmt = $this->pdo->prepare("SELECT * FROM report_media WHERE report_id = ? ORDER BY id");
        $stmt->execute([$report_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ================== МЕТОДИ ДЛЯ КОНСОЛЬНИХ КОМАНД ==================

    public function getUsersCount() {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['count'] : 0;
        } catch (Exception $e) {
            logError("Помилка отримання кількості користувачів: " . $e->getMessage());
            return 0;
        }
    }

    public function getActiveUsersToday() {
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM reports WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['count'] : 0;
        } catch (Exception $e) {
            logError("Помилка отримання активних користувачів: " . $e->getMessage());
            return 0;
        }
    }

    public function getAdminsList() {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, rank FROM admins ORDER BY
                CASE rank
                    WHEN 'owner' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'moderator' THEN 3
                    ELSE 4
                END");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Помилка отримання списку адмінів: " . $e->getMessage());
            return [];
        }
    }

    public function getAllUsers() {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, first_name, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Додамо дебаг інформацію
            if (empty($users)) {
                echo "📭 База даних порожня - немає користувачів\n";
            } else {
                echo "📋 Знайдено користувачів: " . count($users) . "\n";
                echo "👤 Перші 3 користувачі:\n";
                foreach (array_slice($users, 0, 3) as $user) {
                    echo "   - ID: {$user['user_id']}, Ім'я: {$user['first_name']}, @{$user['username']}\n";
                }
            }

            return $users;
        } catch (Exception $e) {
            echo "❌ Помилка отримання користувачів: " . $e->getMessage() . "\n";
            return [];
        }
    }

    // НОВИЙ МЕТОД ДЛЯ ПЕРЕВІРКИ АКТИВНИХ КОРИСТУВАЧІВ
    public function userHasReports($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // НОВИЙ МЕТОД ДЛЯ ПОШУКУ КОРИСТУВАЧА ПО USERNAME
    public function getUserById($user_id) {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            logError("Помилка пошуку користувача по ID $user_id: " . $e->getMessage());
            return null;
        }
    }

    // НОВИЙ МЕТОД ДЛЯ ЗАПИТІВ З ПАРАМЕТРАМИ
    public function query($sql, $fetchAll = false, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($fetchAll) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                return $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (Exception $e) {
            logError("Помилка виконання запиту: " . $e->getMessage());
            return $fetchAll ? [] : null;
        }
    }

    public function cleanupInactiveUsers($days = 30) {
        try {
            $cutoff_date = date('Y-m-d', strtotime("-$days days"));
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE created_at < ? AND user_id NOT IN (SELECT DISTINCT user_id FROM reports)");
            $result = $stmt->execute([$cutoff_date]);
            $deleted = $stmt->rowCount();
            logInfo("Видалено $deleted неактивних користувачів старіших за $days днів");
            return $deleted;
        } catch (Exception $e) {
            logError("Помилка очищення неактивних користувачів: " . $e->getMessage());
            return 0;
        }
    }

    // Додаткові методи для розсилки
    public function getUsersForBroadcast($options = []) {
        try {
            $query = "SELECT user_id, username, first_name, language FROM users WHERE 1=1";
            $params = [];

            if (!empty($options['lang'])) {
                $query .= " AND language = ?";
                $params[] = $options['lang'];
            }

            if (!empty($options['active_only'])) {
                $query .= " AND user_id IN (SELECT DISTINCT user_id FROM reports WHERE created_at > datetime('now', '-7 days'))";
            }

            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "🔍 Знайдено користувачів: " . count($users) . " з параметрами: ";
            if ($options['lang']) echo "lang={$options['lang']} ";
            if ($options['active_only']) echo "active_only ";
            echo "\n";

            return $users;
        } catch (Exception $e) {
            logError("Помилка отримання користувачів для розсилки: " . $e->getMessage());
            return [];
        }
    }

    // Метод для отримання детальної статистики
    public function getDetailedStats($period = 'today') {
        try {
            $stats = [];

            // Визначаємо дату початку періоду
            $startDate = match($period) {
                'today' => date('Y-m-d'),
                'week' => date('Y-m-d', strtotime('-7 days')),
                'month' => date('Y-m-d', strtotime('-30 days')),
                default => date('Y-m-d')
            };

            // Нові користувачі
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['new_users'] = $stmt->fetchColumn();

            // Активні користувачі
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['active_users'] = $stmt->fetchColumn();

            // Повідомлень відправлено (репортів)
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['messages_sent'] = $stmt->fetchColumn();

            // Унікальні сесії (користувачі з репортами)
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['unique_sessions'] = $stmt->fetchColumn();

            // Найпопулярніша мова (заглушка)
            $stats['top_language'] = 'uk';

            return $stats;
        } catch (Exception $e) {
            logError("Помилка отримання детальної статистики: " . $e->getMessage());
            return [
                'new_users' => 0,
                'active_users' => 0,
                'messages_sent' => 0,
                'unique_sessions' => 0,
                'top_language' => 'uk'
            ];
        }
    }

    // 🔧 НОВИЙ МЕТОД ДЛЯ ОЧИЩЕННЯ СТАРИХ РЕПОРТІВ
    public function cleanupOldReports($days = 90) {
        try {
            $cutoff_date = date('Y-m-d', strtotime("-$days days"));
            $stmt = $this->pdo->prepare(
                "DELETE FROM reports WHERE created_at < ? AND status != 'pending'"
            );
            $result = $stmt->execute([$cutoff_date]);
            $deleted = $stmt->rowCount();
            
            logInfo("Видалено $deleted старих репортів старіших за $days днів");
            return $deleted;
        } catch (Exception $e) {
            logError("Помилка очищення старих репортів: " . $e->getMessage());
            return 0;
        }
    }

    // 🔧 НОВИЙ МЕТОД ДЛЯ РЕЗЕРВНОГО КОПІЮВАННЯ
    public function backupDatabase($backup_path = null) {
        try {
            if (!$backup_path) {
                $backup_dir = dirname($this->dbPath) . '/backups';
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                $backup_path = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.db';
            }
            
            if (copy($this->dbPath, $backup_path)) {
                logInfo("Резервна копія створена: $backup_path");
                return $backup_path;
            } else {
                logError("Не вдалося створити резервну копію");
                return false;
            }
        } catch (Exception $e) {
            logError("Помилка резервного копіювання: " . $e->getMessage());
            return false;
        }
    }
}
?>