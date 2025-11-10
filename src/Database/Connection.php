<?php

namespace App\Database;

use PDO;
use PDOException;

final class Connection
{
    private static ?PDO $instance = null;

    public static function get(): PDO
    {
        if (self::$instance === null) {
            $host = getenv('DB_HOST');
            $port = getenv('DB_PORT');
            $dbName = getenv('DB_NAME');
            $user = getenv('DB_USER');
            $password = getenv('DB_PASSWORD');

            if ($host === false || $port === false || $dbName === false || $user === false || $password === false) {
                throw new PDOException('Database environment variables are missing.');
            }

            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) $port, $dbName);

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
            ];

            self::$instance = new PDO($dsn, $user, $password, $options);
        }

        return self::$instance;
    }
}
