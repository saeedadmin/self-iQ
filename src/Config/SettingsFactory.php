<?php

declare(strict_types=1);

namespace App\Config;

use App\Session\PostgresSessionHandler;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;

final class SettingsFactory
{
    public static function make(): Settings
    {
        $settings = new Settings();

        $appInfo = new AppInfo();
        $appInfo->setApiId((int) getenv('TELEGRAM_API_ID'));
        $appInfo->setApiHash((string) getenv('TELEGRAM_API_HASH'));

        $settings->setAppInfo($appInfo);
        $settings->setDb(PostgresSessionHandler::createSettings());

        $logLevel = getenv('LOG_LEVEL') ?: 'info';
        $settings->getLogger()->setLevel(match (strtolower($logLevel)) {
            'error' => Logger::LEVEL_ERROR,
            'warning', 'warn' => Logger::LEVEL_WARNING,
            'notice' => Logger::LEVEL_NOTICE,
            'verbose' => Logger::LEVEL_VERBOSE,
            'ultra_verbose' => Logger::LEVEL_ULTRA_VERBOSE,
            default => Logger::LEVEL_INFO,
        });

        return $settings;
    }
}
