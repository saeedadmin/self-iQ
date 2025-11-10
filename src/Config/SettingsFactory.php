<?php

declare(strict_types=1);

namespace App\Config;

use App\Session\PostgresSessionHandler;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\Settings\AppInfo;
use danog\MadelineProto\Settings\Ipc;

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

        $ipcSettings = new Ipc();
        $ipcSettings->setEnabled(false);
        $settings->setIpc($ipcSettings);

        $logLevel = getenv('LOG_LEVEL') ?: 'info';
        $settings->getLogger()->setLevel(match (strtolower($logLevel)) {
            'fatal' => Logger::LEVEL_FATAL,
            'error' => Logger::LEVEL_ERROR,
            'warning', 'warn' => Logger::LEVEL_WARNING,
            'notice' => Logger::LEVEL_NOTICE,
            'verbose', 'debug' => Logger::LEVEL_VERBOSE,
            'ultra_verbose', 'trace' => Logger::LEVEL_ULTRA_VERBOSE,
            default => Logger::LEVEL_NOTICE,
        });

        return $settings;
    }
}
