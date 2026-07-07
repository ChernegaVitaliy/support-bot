# Telegram Support Bot v3.0

Telegram Support Bot переписаний на чистий ООП код з використанням сучасних бібліотек.

## Особливості

- ✅ **Чистий ООП код** - використання класів, інтерфейсів та залежностей
- ✅ **Бібліотека telegram-bot/api** - професійна робота з Telegram Bot API
- ✅ **vlucas/phpdotenv** - керування конфігурацією через .env
- ✅ **Monolog** - потужне логування з кольоровим виводом
- ✅ **Архітектура** - поділ на Services, Models, Commands, Config
- ✅ **Масштабованість** - легке додавання нових команд та функцій

## Встановлення

```bash
composer install
```

## Конфігурація

Файл `.env`:
```
BOT_TOKEN=your_bot_token
DB_PATH=bot.db
LOG_FILE=bot.log
DEFAULT_OWNER_ID=your_telegram_id
```

## Запуск

```bash
php console app:run
```

## Структура проекту

```
src/
├── Bot.php              - Основний клас бота
├── Config/
│   └── Config.php       - Конфігурація
├── Commands/
│   ├── BaseCommand.php  - Базовий клас команди
│   ├── Admin/           - Адмін-команди
│   │   ├── StatsCommand.php
│   │   ├── AddAdminCommand.php
│   │   ├── RemoveAdminCommand.php
│   │   ├── SetRankCommand.php
│   │   ├── ReportsCommand.php
│   │   └── BroadcastCommand.php
│   ├── HelpCommand.php
│   ├── StartCommand.php
│   ├── AboutCommand.php
│   ├── ProfileCommand.php
│   └── CancelCommand.php
├── Interfaces/
│   └── CommandInterface.php - Інтерфейс команди
├── Models/
│   ├── User.php         - Модель користувача
│   ├── Admin.php        - Модель адміна
│   └── Report.php       - Модель репорту
├── Services/
│   ├── Logger.php       - Логування (Monolog)
│   ├── DatabaseService.php - База даних
│   ├── TelegramService.php  - Telegram API
│   ├── Translator.php   - Мульти-мовність
│   └── SessionManager.php    - Управління сесіями
```

## Команди

### Користувацькі команди:
- `/start` - Почати роботу з ботом
- `/help` - Довідка
- `/about` - Про бота
- `/profile` - Профіль користувача
- `/cancel` - Скасувати дію

### Адмін-команди:
- `/stats` - Статистика бота
- `/adminlist` - Список адмінів
- `/addadmin <username/id> [rank]` - Додати адміна
- `/removeadmin <username/id>` - Видалити адміна
- `/setrank <username/id> <rank>` - Змінити ранг
- `/reports [status]` - Список репортів
- `/broadcast` - Розсилка

## Консольні команди

Під час роботи бота можна використовувати консольні команди:
- `help` - Довідка
- `stats` - Статистика
- `users` - Кількість користувачів
- `admins` - Список адмінів
- `sessions` - Активні сесії
- `exit` - Вихід

## Додавання нових команд

1. Створіть клас команди в `src/Commands/`:
```php
<?php
namespace App\Commands;

use TelegramBot\Api\Types\Message;

class MyCommand extends BaseCommand
{
    public function getName(): string
    {
        return '/mycommand';
    }

    public function execute(Message $message, string $language = 'uk'): void
    {
        // Ваш код
    }
}
```

2. Зареєструйте команду в `index.php`:
```php
$bot->registerCommand(new \App\Commands\MyCommand(...));
```

## Ліцензія

MIT
