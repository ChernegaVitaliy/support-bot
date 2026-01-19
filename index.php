<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Bot;
use App\Commands;

$bot = new Bot();

$logger = $bot->getLogger();
$telegram = $bot->getTelegram();
$db = $bot->getDb();
$translator = $bot->getTranslator();
$sessionManager = $bot->getSessionManager();
$config = $bot->getConfig();

$commands = [
    new Commands\HelpCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\StartCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\AboutCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\ProfileCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\MyIdCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\MyRankCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\StatsCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\ReportCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\CancelCommand($logger, $telegram, $db, $translator, $sessionManager),
];

foreach ($commands as $command) {
    $bot->registerCommand($command);
}

$adminCommands = [
    new Commands\Admin\AdminListCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\AddAdminCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\RemoveAdminCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\SetRankCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\ViewReportCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\AcceptReportCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\RejectReportCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\BroadcastCommand($logger, $telegram, $db, $translator, $sessionManager),
    new Commands\Admin\DebugCommand($logger, $telegram, $db, $translator, $sessionManager),
];

foreach ($adminCommands as $command) {
    $bot->registerCommand($command);
}

$bot->run();
