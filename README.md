# Telegram Support Bot v3.0

Telegram Support Bot is rewritten in clean OOP code using modern libraries.

## Features

- ✅ **Clean OOP code** - use of classes, interfaces and dependencies
- ✅ **telegram-bot/api library** - professional Telegram Bot API integration
- ✅ **vlucas/phpdotenv** - configuration management via .env
- ✅ **Monolog** - powerful logging with colored output
- ✅ **Architecture** - separation into Services, Models, Commands, Config
- ✅ **Scalability** - easy addition of new commands and features

## Installation

```bash
composer install
```

## Configuration

`.env` file:
```
BOT_TOKEN=your_bot_token
DB_PATH=bot.db
LOG_FILE=bot.log
DEFAULT_OWNER_ID=your_telegram_id
```

## Run

```bash
php console app:run
```

## Project Structure

```
src/
├── Bot.php              - Main bot class
├── Config/
│   └── Config.php       - Configuration
├── Commands/
│   ├── BaseCommand.php  - Base command class
│   ├── Admin/           - Admin commands
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
│   └── CommandInterface.php - Command interface
├── Models/
│   ├── User.php         - User model
│   ├── Admin.php        - Admin model
│   └── Report.php       - Report model
├── Services/
│   ├── Logger.php       - Logging (Monolog)
│   ├── DatabaseService.php - Database
│   ├── TelegramService.php  - Telegram API
│   ├── Translator.php   - Multi-language support
│   └── SessionManager.php    - Session management
```

## Commands

### User commands:
- `/start` - Start working with the bot
- `/help` - Help
- `/about` - About the bot
- `/profile` - User profile
- `/cancel` - Cancel action

### Admin commands:
- `/stats` - Bot statistics
- `/adminlist` - List of admins
- `/addadmin <username/id> [rank]` - Add admin
- `/removeadmin <username/id>` - Remove admin
- `/setrank <username/id> <rank>` - Change rank
- `/reports [status]` - List of reports
- `/broadcast` - Broadcast

## Console Commands

While the bot is running, you can use console commands:
- `help` - Help
- `stats` - Statistics
- `users` - Number of users
- `admins` - List of admins
- `sessions` - Active sessions
- `exit` - Exit

## Adding New Commands

1. Create a command class in `src/Commands/`:
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
        // Your code
    }
}
```

2. Register the command in `index.php`:
```php
$bot->registerCommand(new \App\Commands\MyCommand(...));
```

## License

MIT
