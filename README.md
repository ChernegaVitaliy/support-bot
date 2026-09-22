# Telegram Support Bot

[![Docker Image CI](https://github.com/ChernegaVitaliy/support-bot/actions/workflows/docker-image.yml/badge.svg)](https://github.com/ChernegaVitaliy/support-bot/actions/workflows/docker-image.yml)

Telegram Support Bot is rewritten in clean OOP code using modern libraries.

## Features

- **Clean OOP code**: Use of classes, interfaces and dependencies.
- **Telegram Bot API**: Professional integration with `telegram-bot/api` library.
- **Configuration management**: Environment-based config with `vlucas/phpdotenv`.
- **Logging**: Powerful logging with colored output via `Monolog`.
- **Modular architecture**: Clear separation into Services, Models, Commands, Config, and Handlers.
- **Scalability**: Easy addition of new commands and features.

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

## Running the Project

### Local (console)

```bash
php console app:run
```

### Docker

```bash
docker compose build
docker compose up -d
```

The bot will be available on port 8081, Mini App on the same port.

To view logs:
```bash
docker compose logs -f
```

To stop:
```bash
docker compose down
```

### Mini App

The Mini App is a Symfony web application (controllers under `src/Controller/`).
Start the local web server with the built-in PHP server:

```bash
php -S 127.0.0.1:8080 -t public public/index.php
```

Then open `https://127.0.0.1:8080`.

## Project Structure

```
support-bot/
+-- src/
|   +-- Bot.php                       # Main bot class
|   +-- Config/                       # Configuration
|   |   \-- Config.php
|   +-- Commands/                     # Base command class
|   |   +-- BaseCommand.php
|   |   +-- AboutCommand.php
|   |   +-- CancelCommand.php
|   |   +-- HelpCommand.php
|   |   +-- MyIdCommand.php
|   |   +-- MyRankCommand.php
|   |   +-- ProfileCommand.php
|   |   +-- ReportCommand.php
|   |   +-- StartCommand.php
|   |   +-- StatsCommand.php
|   |   \-- Admin/
|   |       +-- AcceptReportCommand.php
|   |       +-- AddAdminCommand.php
|   |       +-- AdminListCommand.php
|   |       +-- BroadcastCommand.php
|   |       +-- DebugCommand.php
|   |       +-- RejectReportCommand.php
|   |       +-- RemoveAdminCommand.php
|   |       +-- ReportsCommand.php
|   |       \-- SetRankCommand.php
|   +-- Console/
|   |   +-- ServiceContainer.php
|   |   \-- Commands/
|   |       +-- AddAdminCommand.php
|   |       +-- AdminListCommand.php
|   |       +-- BroadcastCommand.php
|   |       +-- FindUserCommand.php
|   |       +-- RemoveAdminCommand.php
|   |       +-- RunCommand.php
|   |       +-- SetRankCommand.php
|   |       +-- SetupBotCommand.php
|   |       +-- StatsCommand.php
|   |       \-- VersionCommand.php
|   +-- Handlers/
|   |   +-- CallbackHandler.php
|   |   \-- SessionHandler.php
|   +-- Interfaces/
|   |   \-- CommandInterface.php
|   +-- Models/
|   |   +-- Admin.php
|   |   +-- Report.php
|   |   \-- User.php
|   \-- Services/
|       +-- BroadcastService.php
|       +-- DatabaseService.php
|       +-- Logger.php
|       +-- ReportService.php
|       +-- SessionManager.php
|       +-- TelegramService.php
|       \-- Translator.php
```

## Usage

### User Commands

- `/start` — start working with the bot
- `/help` — help
- `/about` — about the bot
- `/profile` — user profile
- `/myid` — show your Telegram ID
- `/myrank` — show your admin rank
- `/report` — submit a report
- `/cancel` — cancel current action

### Admin Commands

- `/stats` — bot statistics
- `/adminlist` — list of admins
- `/addadmin <username/id> [rank]` — add admin (admin+)
- `/removeadmin <username/id>` — remove admin (admin+)
- `/setrank <username/id> <rank>` — change rank (owner only)
- `/reports [status]` — list of reports (moderator+)
- `/accept <report_id> [comment]` — accept report (moderator+)
- `/reject <report_id> [reason]` — reject report (moderator+)
- `/broadcast` — broadcast message (owner only)
- `/debug <level>` — debug settings (owner only)

### Console Commands

While the bot is running, you can use console commands:
- `help` — show help for console commands
- `stats` — statistics
- `app:version` — show version
- `admin:list` — list of admins
- `admin:add <username/id> [rank]` — add admin
- `admin:remove <username/id>` — remove admin
- `admin:rank <username/id> <rank>` — change rank
- `broadcast` — broadcast message
- `user:find <query>` — find user by ID, username or name
- `bot:setup` — setup bot
- `app:run` — run the bot

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

2. Register the command in `src/Bot.php` in the `registerDefaultCommands()` method:
```php
$this->commands[] = new \App\Commands\MyCommand($this->container);
```

## Contributing

Contributions are welcome and appreciated! Here's how you can contribute:

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

Please make sure to update tests as appropriate and adhere to the existing coding style.

## License

This project is licensed under the CSSM Unlimited License v2.0 (CSSM-ULv2). See the [LICENSE](LICENSE) file for details.
