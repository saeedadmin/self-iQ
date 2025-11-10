<?php

namespace App\Session;

use danog\MadelineProto\Settings\Database\Postgres as PostgresSettings;
use danog\MadelineProto\Settings\Database\SerializerType;
use RuntimeException;

final class PostgresSessionHandler
{
    public static function createSettings(): PostgresSettings
    {
        $settings = new PostgresSettings();
        [$uri, $database, $user, $password, $prefix] = self::databaseParameters();

        $settings->setUri($uri);
        $settings->setDatabase($database);
        $settings->setUsername($user);
        $settings->setPassword($password);
        $settings->setEphemeralFilesystemPrefix($prefix);
        $settings->setSerializer(SerializerType::SERIALIZER_DEFAULT);
        $settings->setEnableFileReferenceDb(true);
        $settings->setEnableFullPeerDb(true);
        $settings->setEnablePeerInfoDb(true);
        $settings->setEnableMinDb(true);
        $settings->setEnableUsernameDb(true);

        return $settings;
    }

    private static function databaseParameters(): array
    {
        $host = getenv('DB_HOST');
        $port = getenv('DB_PORT');
        $dbName = getenv('DB_NAME');
        $user = getenv('DB_USER');
        $password = getenv('DB_PASSWORD');
        $prefix = getenv('SESSION_PREFIX');

        if ($host === false || $port === false || $dbName === false || $user === false || $password === false || $prefix === false) {
            throw new RuntimeException('Database environment variables are missing for session storage.');
        }

        $uri = sprintf('tcp://%s:%d', $host, (int) $port);

        return [$uri, $dbName, $user, $password, $prefix];
    }
}
