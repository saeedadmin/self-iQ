<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/vendor/autoload.php';

if (is_file($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$requiredEnv = [
    'TELEGRAM_API_ID',
    'TELEGRAM_API_HASH',
    'SESSION_PREFIX',
    'SESSION_FILE',
    'OWNER_USER_ID',
    'DB_HOST',
    'DB_PORT',
    'DB_NAME',
    'DB_USER',
    'DB_PASSWORD',
];

foreach ($requiredEnv as $key) {
    if (getenv($key) === false) {
        throw new RuntimeException(sprintf('Missing required environment variable "%s".', $key));
    }
}
