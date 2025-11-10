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
            $sslMode = getenv('DB_SSLMODE');

            if ($host === false || $port === false || $dbName === false || $user === false || $password === false) {
                throw new PDOException('Database environment variables are missing.');
            }

            $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $host, (int) $port, $dbName);
            if ($sslMode !== false && $sslMode !== '') {
                $dsn .= ';sslmode=' . $sslMode;
            }

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT => false,
            ];

            if ($sslMode !== false && $sslMode !== '' && defined('PDO::PGSQL_ATTR_SSL_MODE')) {
                $modeMap = [
                    'disable' => defined('PDO::PGSQL_SSLMODE_DISABLE') ? PDO::PGSQL_SSLMODE_DISABLE : null,
                    'allow' => defined('PDO::PGSQL_SSLMODE_ALLOW') ? PDO::PGSQL_SSLMODE_ALLOW : null,
                    'prefer' => defined('PDO::PGSQL_SSLMODE_PREFER') ? PDO::PGSQL_SSLMODE_PREFER : null,
                    'require' => defined('PDO::PGSQL_SSLMODE_REQUIRE') ? PDO::PGSQL_SSLMODE_REQUIRE : null,
                    'verify-ca' => defined('PDO::PGSQL_SSLMODE_VERIFY_CA') ? PDO::PGSQL_SSLMODE_VERIFY_CA : null,
                    'verify-full' => defined('PDO::PGSQL_SSLMODE_VERIFY_FULL') ? PDO::PGSQL_SSLMODE_VERIFY_FULL : null,
                ];

                $normalized = strtolower((string) $sslMode);
                if (isset($modeMap[$normalized]) && $modeMap[$normalized] !== null) {
                    $options[PDO::PGSQL_ATTR_SSL_MODE] = $modeMap[$normalized];
                }
            }

            self::$instance = new PDO($dsn, $user, $password, $options);
        }

        return self::$instance;
    }
}
