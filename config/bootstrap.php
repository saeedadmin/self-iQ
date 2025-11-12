<?php

declare(strict_types=1);

use Dotenv\Dotenv;

$rootPath = dirname(__DIR__);

require_once $rootPath . '/vendor/autoload.php';

$overrides = $rootPath . '/src/Liveproto/overrides.php';
if (is_file($overrides)) {
    require_once $overrides;
}

if (is_file($rootPath . '/.env')) {
    Dotenv::createImmutable($rootPath)->safeLoad();
}

$requiredEnv = [
    'TELEGRAM_API_ID',
    'TELEGRAM_API_HASH',
    'SESSION_NAME',
    'OWNER_USER_ID',
    'MYSQL_HOST',
    'MYSQL_PORT',
    'MYSQL_USER',
    'MYSQL_PASSWORD',
    'MYSQL_DB',
];

foreach ($requiredEnv as $key) {
    if (getenv($key) === false) {
        throw new RuntimeException(sprintf('Missing required environment variable "%s".', $key));
    }
}
