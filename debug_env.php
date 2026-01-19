<?php
require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

var_dump($_ENV['BOT_TOKEN'] ?? 'ENV_BOT_TOKEN_NOT_SET');
var_dump($_SERVER['BOT_TOKEN'] ?? 'SERVER_BOT_TOKEN_NOT_SET');
var_dump(getenv('BOT_TOKEN'));
