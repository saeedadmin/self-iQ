<?php

declare(strict_types=1);

use App\Bot\BaseHandler;
use App\Config\SettingsFactory;

require_once __DIR__ . '/../config/bootstrap.php';

$sessionFile = getenv('SESSION_FILE');
if ($sessionFile === false || $sessionFile === '') {
    throw new RuntimeException('SESSION_FILE env variable is not set.');
}

$settings = SettingsFactory::make();

BaseHandler::startAndLoop($sessionFile, $settings);
