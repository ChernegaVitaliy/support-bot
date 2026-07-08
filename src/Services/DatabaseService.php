<?php

namespace App\Services;

use PDO;
use PDOException;
use Exception;

class DatabaseService
{
    private PDO $pdo;
    private string $dbPath;
    private Logger $logger;
    private string $defaultOwnerId;

    public function __construct(string $dbPath, Logger $logger, string $defaultOwnerId = '5720736515')
    {
        $this->dbPath = $dbPath;
        $this->logger = $logger;
        $this->defaultOwnerId = $defaultOwnerId;

        $this->connect();
        $this->updateDatabaseStructure();
        $this->createTables();
        $this->createReportsTable();
        $this->updateReportsTableStructure();
        $this->createNewsTable();
        $this->addDefaultAdmin();
        $this->addLanguageSupport();
        $this->createIndexes();
    }

    private function connect(): void
    {
        try {
            $dir = dirname($this->dbPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $this->pdo = new PDO("sqlite:" . $this->dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->exec("PRAGMA foreign_keys = ON");
        } catch (PDOException $e) {
            $this->logger->error("Помилка підключення до бази даних: " . $e->getMessage());
            die("❌ Помилка підключення до бази даних: " . $e->getMessage());
        }
    }

    private function updateReportsTableStructure(): void
    {
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

            if (!$has_processed_by) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN processed_by TEXT");
                $this->logger->info("Додано колонку processed_by");
            }

            if (!$has_processed_at) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN processed_at DATETIME");
                $this->logger->info("Додано колонку processed_at");
            }

            if (!$has_proof_type) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN proof_type TEXT DEFAULT 'text'");
                $this->logger->info("Додано колонку proof_type");
            }

            if (!$has_file_id) {
                $this->pdo->exec("ALTER TABLE reports ADD COLUMN file_id TEXT");
                $this->logger->info("Додано колонку file_id");
            }

            if (!$has_processed_by || !$has_processed_at || !$has_proof_type || !$has_file_id) {
                $this->logger->info("Структура таблиці reports оновлена");
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка оновлення структури таблиці reports: " . $e->getMessage());
        }
    }

    private function updateDatabaseStructure(): void
    {
        $this->logger->info("Перевірка структури бази даних...");
        try {
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
                $this->logger->info("Оновлення структури таблиці admins...");

                $this->pdo->exec("
                    CREATE TABLE IF NOT EXISTS admins_new (
                        user_id TEXT PRIMARY KEY,
                        username TEXT,
                        first_name TEXT,
                        rank TEXT DEFAULT 'moderator',
                        added_at DATETIME DEFAULT CURRENT_TIMESTAMP
                    )
                ");

                try {
                    $this->pdo->exec("INSERT INTO admins_new (user_id, username, first_name, rank)
                                     SELECT chat_id, username, first_name, rank FROM admins");
                    $this->logger->info("Дані адмінів успішно перенесені");
                } catch (Exception $e) {
                    $this->logger->warning("Не вдалося скопіювати дані адмінів: " . $e->getMessage());
                }

                $this->pdo->exec("DROP TABLE IF EXISTS admins");
                $this->pdo->exec("ALTER TABLE admins_new RENAME TO admins");

                $this->logger->info("Структура таблиці admins оновлена");
            }

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
                $this->logger->info("Оновлення структури таблиці users...");

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
                    $this->logger->info("Дані користувачів успішно перенесені");
                } catch (Exception $e) {
                    $this->logger->warning("Не вдалося скопіювати дані користувачів: " . $e->getMessage());
                }

                $this->pdo->exec("DROP TABLE IF EXISTS users");
                $this->pdo->exec("ALTER TABLE users_new RENAME TO users");

                $this->logger->info("Структура таблиці users оновлена");
            }

        } catch (Exception $e) {
            $this->logger->error("Помилка перевірки структури: " . $e->getMessage());
        }
    }

    private function createTables(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                first_name TEXT,
                language TEXT DEFAULT 'uk',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admins (
                user_id TEXT PRIMARY KEY,
                username TEXT,
                first_name TEXT,
                rank TEXT DEFAULT 'moderator',
                added_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");

        $this->logger->info("Таблиці бази даних створені/перевірені");
    }

    public function createReportsTable(): void
    {
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
        $this->logger->info("Таблиця reports створена/перевірена");
    }

    public function createNewsTable(): void
    {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS news (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                body TEXT NOT NULL,
                author_id TEXT NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $this->logger->info("Таблиця news створена/перевірена");
    }

    private function addDefaultAdmin(): void
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE user_id = ?");
        $stmt->execute([$this->defaultOwnerId]);
        $exists = $stmt->fetchColumn() > 0;

        if (!$exists) {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO admins (user_id, username, first_name, rank)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$this->defaultOwnerId, 'default_admin', 'Default Admin', 'owner']);
            $this->logger->info("Default admin ({$this->defaultOwnerId}) додано як owner");
        }
    }

    public function addLanguageSupport(): void
    {
        try {
            $stmt = $this->pdo->prepare("PRAGMA table_info(users)");
            $stmt->execute();
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 1);

            if (!in_array('language', $columns)) {
                $this->pdo->exec("ALTER TABLE users ADD COLUMN language TEXT DEFAULT 'uk'");
                $this->logger->info("Додано колонку language до таблиці users");
            }

            $this->pdo->exec("UPDATE users SET language = 'uk' WHERE language IS NULL");

        } catch (Exception $e) {
            $this->logger->error("Помилка додавання мовної підтримки: " . $e->getMessage());
        }
    }

    private function createIndexes(): void
    {
        try {
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_user_id ON reports(user_id)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_reports_created_at ON reports(created_at)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_users_language ON users(language)");
            $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_admins_rank ON admins(rank)");

            $this->logger->info("Індекси створені/перевірені");
        } catch (Exception $e) {
            $this->logger->error("Помилка створення індексів: " . $e->getMessage());
        }
    }

    public function getUserLanguage(string $user_id): ?string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT language FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['language'] ?? null;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання мови для $user_id: " . $e->getMessage());
            return null;
        }
    }

    public function updateUserLanguage(string $user_id, string $language): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE users SET language = ? WHERE user_id = ?");
            $result = $stmt->execute([$language, $user_id]);
            $this->logger->debug("Оновлено мову для $user_id: $language");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка оновлення мови для $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function addUser(string $user_id, ?string $username = null, ?string $first_name = null, string $language = 'uk'): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT OR REPLACE INTO users (user_id, username, first_name, language)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $username, $first_name, $language]);
            $this->logger->debug("Користувач доданий/оновлений: $user_id @$username, мова: $language");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка додавання користувача $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function addReport(string $user_id, string $reporter_nick, string $reported_nick, string $reason, ?string $proof = null, string $proof_type = 'text', ?string $file_id = null): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO reports (user_id, reporter_nick, reported_nick, reason, proof, proof_type, file_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $reporter_nick, $reported_nick, $reason, $proof, $proof_type, $file_id]);

            if ($result) {
                $report_id = $this->pdo->lastInsertId();
                $this->logger->info("Репорт доданий: ID $report_id від $user_id ($reporter_nick) на $reported_nick, тип: $proof_type");
                return $report_id;
            }
            return null;
        } catch (Exception $e) {
            $this->logger->error("Помилка додавання репорту: " . $e->getMessage());
            return null;
        }
    }

    public function updateReportStatus(int $report_id, string $status, ?string $admin_notes = null, ?string $admin_id = null): bool
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "UPDATE reports SET status = ?, admin_notes = ?, processed_by = ?, processed_at = CURRENT_TIMESTAMP WHERE id = ?"
            );
            $result = $stmt->execute([$status, $admin_notes, $admin_id, $report_id]);

            if ($result && $stmt->rowCount() > 0) {
                $this->pdo->commit();
                $this->logger->info("Статус репорту оновлено: ID $report_id -> $status (адмін: $admin_id)");
                return true;
            } else {
                $this->pdo->rollBack();
                $this->logger->warning("Репорт не знайдено для оновлення: ID $report_id");
                return false;
            }
        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error("Помилка оновлення статусу репорту: " . $e->getMessage());
            return false;
        }
    }

    public function getReportById(int $report_id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE id = ?");
            $stmt->execute([$report_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->logger->debug("Пошук репорту по ID $report_id: " . ($result ? 'знайдено' : 'не знайдено'));
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку репорту по ID $report_id: " . $e->getMessage());
            return null;
        }
    }

    public function getAllReports(?string $status = null): array
    {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE status = ? ORDER BY created_at ASC");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM reports ORDER BY created_at ASC");
                $stmt->execute();
            }
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Отримано репортів: " . count($result) . " записів");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання списку репортів: " . $e->getMessage());
            return [];
        }
    }

    public function getReportsCount(?string $status = null): int
    {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE status = ?");
                $stmt->execute([$status]);
            } else {
                $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports");
                $stmt->execute();
            }
            return (int)$stmt->fetchColumn();
        } catch (Exception $e) {
            $this->logger->error("Помилка підрахунку репортів: " . $e->getMessage());
            return 0;
        }
    }

    public function getReportsPaginated(?string $status = null, int $limit = 10, int $offset = 0): array
    {
        try {
            if ($status) {
                $stmt = $this->pdo->prepare("SELECT * FROM reports WHERE status = ? ORDER BY created_at ASC LIMIT ? OFFSET ?");
                $stmt->execute([$status, $limit, $offset]);
            } else {
                $stmt = $this->pdo->prepare("SELECT * FROM reports ORDER BY created_at ASC LIMIT ? OFFSET ?");
                $stmt->execute([$limit, $offset]);
            }
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання репортів (пагінація): " . $e->getMessage());
            return [];
        }
    }

    public function getAllReportsForStats(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM reports ORDER BY created_at ASC");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання всіх репортів: " . $e->getMessage());
            return [];
        }
    }

    public function isAdmin(string $user_id): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn() > 0;
        $this->logger->debug("isAdmin для $user_id: " . ($result ? 'Y' : 'N'));
        return $result;
    }

    public function getAdminRank(string $user_id): ?string
    {
        $this->logger->debug("getAdminRank для: $user_id");

        $stmt = $this->pdo->prepare("SELECT rank FROM admins WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $result = $stmt->fetchColumn();

        $this->logger->debug("getAdminRank результат: " . ($result ?: 'NULL'));
        return $result ?: null;
    }

    public function addAdmin(string $user_id, string $username, string $first_name, string $rank): bool
    {
        try {
            if ($this->isAdmin($user_id)) {
                $this->logger->warning("Адмін з user_id $user_id вже існує");
                return false;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO admins (user_id, username, first_name, rank)
                VALUES (?, ?, ?, ?)
            ");
            $result = $stmt->execute([$user_id, $username, $first_name, $rank]);
            $this->logger->info("Адмін доданий: $user_id @$username ранг: $rank");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка додавання адміна: " . $e->getMessage());
            return false;
        }
    }

    public function removeAdmin(string $user_id, ?string $current_admin_id = null): bool
    {
        try {
            if ($user_id === $this->defaultOwnerId) {
                $this->logger->warning("Спроба видалити дефолтного власника: $user_id");
                return false;
            }

            if ($current_admin_id === $this->defaultOwnerId) {
                $stmt = $this->pdo->prepare("DELETE FROM admins WHERE user_id = ?");
                $result = $stmt->execute([$user_id]);

                if ($result) {
                    $this->logger->info("🗑️ Дефолтний власник видалив адміна: $user_id");
                    return true;
                } else {
                    $this->logger->warning("Адмін не знайдений для видалення: $user_id");
                    return false;
                }
            }

            $stmt = $this->pdo->prepare("DELETE FROM admins WHERE user_id = ?");
            $result = $stmt->execute([$user_id]);

            if ($result) {
                $this->logger->info("Адмін видалений: $user_id");
                return true;
            } else {
                $this->logger->warning("Адмін не знайдений для видалення: $user_id");
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка видалення адміна $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function setAdminRank(string $user_id, string $rank, ?string $current_admin_id = null): bool
    {
        try {
            if ($user_id === $this->defaultOwnerId) {
                $this->logger->warning("Спроба змінити ранг дефолтного власника: $user_id");
                return false;
            }

            if ($current_admin_id === $this->defaultOwnerId) {
                $stmt = $this->pdo->prepare("UPDATE admins SET rank = ? WHERE user_id = ?");
                $result = $stmt->execute([$rank, $user_id]);

                if ($result) {
                    $this->logger->info("👑 Дефолтний власник змінив ранг: $user_id -> $rank");
                    return true;
                } else {
                    $this->logger->warning("Адмін не знайдений для оновлення рангу: $user_id");
                    return false;
                }
            }

            $stmt = $this->pdo->prepare("UPDATE admins SET rank = ? WHERE user_id = ?");
            $result = $stmt->execute([$rank, $user_id]);

            if ($result) {
                $this->logger->info("Ранг оновлено: $user_id -> $rank");
                return true;
            } else {
                $this->logger->warning("Адмін не знайдений для оновлення рангу: $user_id");
                return false;
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка оновлення рангу $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function findUserByUsername(string $username): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, first_name, language FROM users WHERE username = ?");
            $stmt->execute([$username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку користувача за username @$username: " . $e->getMessage());
            return null;
        }
    }

    public function getAllAdmins(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.user_id,
                    COALESCE(u.username, a.username) AS username,
                    COALESCE(u.first_name, a.first_name) AS first_name,
                    a.rank,
                    a.added_at
                FROM admins a
                LEFT JOIN users u ON a.user_id = u.user_id
                ORDER BY
                    CASE a.rank
                        WHEN 'owner' THEN 1
                        WHEN 'admin' THEN 2
                        WHEN 'moderator' THEN 3
                        ELSE 4
                    END
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Отримано список адмінів: " . count($result) . " записів");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання списку адмінів: " . $e->getMessage());
            return [];
        }
    }

    public function getStats(): array
    {
        try {
            $stats = [];

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $stats['total_users'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM admins");
            $stmt->execute();
            $stats['total_admins'] = $stmt->fetchColumn();

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

            $this->logger->debug("Статистика: users=" . $stats['total_users'] . " admins=" . $stats['total_admins'] . " reports=" . $stats['total_reports']);
            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання статистики: " . $e->getMessage());
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

    public function getAdminByUserId(string $user_id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.user_id,
                    COALESCE(u.username, a.username) AS username,
                    COALESCE(u.first_name, a.first_name) AS first_name,
                    a.rank,
                    a.added_at
                FROM admins a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.user_id = ?
            ");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->logger->debug("Пошук адміна по user_id $user_id: " . ($result ? 'знайдено' : 'не знайдено'));
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку адміна по user_id $user_id: " . $e->getMessage());
            return null;
        }
    }

    public function updateAdminInfo(string $user_id, string $username, string $first_name): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE admins SET username = ?, first_name = ? WHERE user_id = ?");
            $result = $stmt->execute([$username, $first_name, $user_id]);
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка оновлення інформації адміна для $user_id: " . $e->getMessage());
            return false;
        }
    }

    public function getAdminByChatId(string $chat_id): ?array
    {
        return $this->getAdminByUserId($chat_id);
    }

    public function getAdminByIdentifier(string $identifier): ?array
    {
        if (empty($identifier)) {
            return null;
        }

        if (strpos($identifier, '@') === 0) {
            $username = substr($identifier, 1);
            return $this->getAdminByUsername($username);
        } elseif (is_numeric($identifier)) {
            return $this->getAdminByUserId((string)$identifier);
        } else {
            return $this->getAdminByUsername($identifier);
        }
    }

    public function getAdminByUsername(string $username): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.user_id,
                    COALESCE(u.username, a.username) AS username,
                    COALESCE(u.first_name, a.first_name) AS first_name,
                    a.rank,
                    a.added_at
                FROM admins a
                LEFT JOIN users u ON a.user_id = u.user_id
                WHERE a.username = ? OR u.username = ?
            ");
            $stmt->execute([$username, $username]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку адміна по username @$username: " . $e->getMessage());
            return null;
        }
    }

    public function updateAdminChatId(string $username, string $chat_id, string $first_name): bool
    {
        try {
            $stmt = $this->pdo->prepare("UPDATE admins SET user_id = ?, first_name = ? WHERE username = ? AND user_id IS NULL");
            $result = $stmt->execute([$chat_id, $first_name, $username]);
            if ($result) {
                $this->logger->info("Оновлено user_id для @$username: $chat_id");
            }
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка оновлення user_id для @$username: " . $e->getMessage());
            return false;
        }
    }

    public function updateAdminUsername(string $chat_id, string $username, string $first_name): bool
    {
        return $this->updateAdminInfo($chat_id, $username, $first_name);
    }

    public function addReportMedia(int $report_id, string $file_id, string $media_type): bool
    {
        $stmt = $this->pdo->prepare("INSERT INTO report_media (report_id, file_id, media_type) VALUES (?, ?, ?)");
        return $stmt->execute([$report_id, $file_id, $media_type]);
    }

    public function getReportMedia(int $report_id): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM report_media WHERE report_id = ? ORDER BY id");
        $stmt->execute([$report_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUsersCount(): int
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) as count FROM users");
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання кількості користувачів: " . $e->getMessage());
            return 0;
        }
    }

    public function getActiveUsersToday(): int
    {
        try {
            $today = date('Y-m-d');
            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) as count FROM reports WHERE DATE(created_at) = ?");
            $stmt->execute([$today]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? (int)$result['count'] : 0;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання активних користувачів: " . $e->getMessage());
            return 0;
        }
    }

    public function getAdminsList(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    a.user_id,
                    COALESCE(u.username, a.username) AS username,
                    a.rank
                FROM admins a
                LEFT JOIN users u ON a.user_id = u.user_id
                ORDER BY
                    CASE a.rank
                        WHEN 'owner' THEN 1
                        WHEN 'admin' THEN 2
                        WHEN 'moderator' THEN 3
                        ELSE 4
                    END
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання списку адмінів: " . $e->getMessage());
            return [];
        }
    }

    public function getAllUsers(): array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT user_id, username, first_name, created_at FROM users ORDER BY created_at DESC");
            $stmt->execute();
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    public function userHasReports(string $user_id): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE user_id = ?");
            $stmt->execute([$user_id]);
            return $stmt->fetchColumn() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getUserById(string $user_id): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку користувача по ID $user_id: " . $e->getMessage());
            return null;
        }
    }

    public function query(string $sql, bool $fetchAll = false, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            if ($fetchAll) {
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                return $result ?: null;
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка виконання запиту: " . $e->getMessage());
            return $fetchAll ? [] : null;
        }
    }

    public function cleanupInactiveUsers(int $days = 30): int
    {
        try {
            $cutoff_date = date('Y-m-d', strtotime("-$days days"));
            $stmt = $this->pdo->prepare("DELETE FROM users WHERE created_at < ? AND user_id NOT IN (SELECT DISTINCT user_id FROM reports)");
            $result = $stmt->execute([$cutoff_date]);
            $deleted = $stmt->rowCount();
            $this->logger->info("Видалено $deleted неактивних користувачів старіших за $days днів");
            return $deleted;
        } catch (Exception $e) {
            $this->logger->error("Помилка очищення неактивних користувачів: " . $e->getMessage());
            return 0;
        }
    }

    public function getUsersForBroadcast(array $options = []): array
    {
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
            if (!empty($options['lang'])) echo "lang={$options['lang']} ";
            if (!empty($options['active_only'])) echo "active_only ";
            echo "\n";

            return $users;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання користувачів для розсилки: " . $e->getMessage());
            return [];
        }
    }

    public function getDetailedStats(string $period = 'today'): array
    {
        try {
            $stats = [];

            $startDate = match($period) {
                'today' => date('Y-m-d'),
                'week' => date('Y-m-d', strtotime('-7 days')),
                'month' => date('Y-m-d', strtotime('-30 days')),
                default => date('Y-m-d')
            };

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['new_users'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['active_users'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['messages_sent'] = $stmt->fetchColumn();

            $stmt = $this->pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM reports WHERE DATE(created_at) >= ?");
            $stmt->execute([$startDate]);
            $stats['unique_sessions'] = $stmt->fetchColumn();

            $stats['top_language'] = 'uk';

            return $stats;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання детальної статистики: " . $e->getMessage());
            return [
                'new_users' => 0,
                'active_users' => 0,
                'messages_sent' => 0,
                'unique_sessions' => 0,
                'top_language' => 'uk'
            ];
        }
    }

    public function cleanupOldReports(int $days = 90): int
    {
        try {
            $cutoff_date = date('Y-m-d', strtotime("-$days days"));
            $stmt = $this->pdo->prepare(
                "DELETE FROM reports WHERE created_at < ? AND status != 'pending'"
            );
            $result = $stmt->execute([$cutoff_date]);
            $deleted = $stmt->rowCount();

            $this->logger->info("Видалено $deleted старих репортів старіших за $days днів");
            return $deleted;
        } catch (Exception $e) {
            $this->logger->error("Помилка очищення старих репортів: " . $e->getMessage());
            return 0;
        }
    }

    public function backupDatabase(?string $backup_path = null): ?string
    {
        try {
            if (!$backup_path) {
                $backup_dir = dirname($this->dbPath) . '/backups';
                if (!is_dir($backup_dir)) {
                    mkdir($backup_dir, 0755, true);
                }
                $backup_path = $backup_dir . '/backup_' . date('Y-m-d_H-i-s') . '.db';
            }

            if (copy($this->dbPath, $backup_path)) {
                $this->logger->info("Резервна копія створена: $backup_path");
                return $backup_path;
            } else {
                $this->logger->error("Не вдалося створити резервну копію");
                return null;
            }
        } catch (Exception $e) {
            $this->logger->error("Помилка резервного копіювання: " . $e->getMessage());
            return null;
        }
    }

    public function getDefaultOwnerId(): string
    {
        return $this->defaultOwnerId;
    }

    public function createNews(string $title, string $body, string $authorId): ?int
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO news (title, body, author_id)
                VALUES (?, ?, ?)
            ");
            $result = $stmt->execute([$title, $body, $authorId]);

            if ($result) {
                $news_id = $this->pdo->lastInsertId();
                $this->logger->info("Новина створена: ID $news_id автором $authorId");
                return (int)$news_id;
            }
            return null;
        } catch (Exception $e) {
            $this->logger->error("Помилка створення новини: " . $e->getMessage());
            return null;
        }
    }

    public function getAllNews(): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT n.*, 
                       COALESCE(u.username, 'unknown') AS author_username,
                       COALESCE(u.first_name, 'Unknown') AS author_first_name
                FROM news n
                LEFT JOIN users u ON n.author_id = u.user_id
                ORDER BY n.created_at DESC
            ");
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->logger->debug("Отримано новин: " . count($result) . " записів");
            return $result;
        } catch (Exception $e) {
            $this->logger->error("Помилка отримання новин: " . $e->getMessage());
            return [];
        }
    }

    public function getNewsById(int $newsId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM news WHERE id = ?");
            $stmt->execute([$newsId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $this->logger->debug("Пошук новини по ID $newsId: " . ($result ? 'знайдено' : 'не знайдено'));
            return $result ?: null;
        } catch (Exception $e) {
            $this->logger->error("Помилка пошуку новини по ID $newsId: " . $e->getMessage());
            return null;
        }
    }

    public function deleteNews(int $newsId): bool
    {
        try {
            $stmt = $this->pdo->prepare("DELETE FROM news WHERE id = ?");
            $result = $stmt->execute([$newsId]);

            if ($result && $stmt->rowCount() > 0) {
                $this->logger->info("Новина видалена: ID $newsId");
                return true;
            }
            $this->logger->warning("Новина не знайдена для видалення: ID $newsId");
            return false;
        } catch (Exception $e) {
            $this->logger->error("Помилка видалення новини $newsId: " . $e->getMessage());
            return false;
        }
    }
}
