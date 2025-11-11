<?php

declare(strict_types=1);

namespace App\Config;

use Tak\Liveproto\Utils\Settings;

final class SettingsFactory
{
    public static function make(): Settings
    {
        $settings = new Settings();

        $settings->setApiId(self::envInt('TELEGRAM_API_ID'));
        $settings->setApiHash(self::envString('TELEGRAM_API_HASH'));

        $settings->setDeviceModel(self::envString('DEVICE_MODEL', 'Koyeb LiveProto'));
        $settings->setSystemVersion(self::envString('SYSTEM_VERSION', '1.0'));
        $settings->setAppVersion(self::envString('APP_VERSION', '1.0.0'));
        $settings->setSystemLangCode(self::envString('SYSTEM_LANG_CODE', 'en-US'));
        $settings->setLangCode(self::envString('LANG_CODE', 'en'));

        $settings->setReceiveUpdates(true);
        $settings->setHotReload(false);

        $host = self::envString('MYSQL_HOST');
        $port = self::envInt('MYSQL_PORT', 3306);
        $settings->setServer(sprintf('%s:%d', $host, $port));
        $settings->setUsername(self::envString('MYSQL_USER'));
        $settings->setPassword(self::envString('MYSQL_PASSWORD'));
        $settings->setDatabase(self::envString('MYSQL_DB'));

        return $settings;
    }

    private static function envString(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException(sprintf('Environment variable %s is required.', $key));
        }

        return (string) $value;
    }

    private static function envInt(string $key, ?int $default = null): int
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default !== null) {
                return $default;
            }
            throw new \RuntimeException(sprintf('Environment variable %s is required.', $key));
        }

        return (int) $value;
    }
}
