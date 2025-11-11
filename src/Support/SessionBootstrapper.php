<?php

declare(strict_types=1);

namespace App\Support;

use PDO;
use PDOException;
use RuntimeException;

final class SessionBootstrapper
{
    public static function bootstrap(string $sessionName): void
    {
        if ($sessionName === '') {
            throw new RuntimeException('Session name cannot be empty when bootstrapping tables.');
        }

        $host = self::envString('MYSQL_HOST');
        $port = self::envInt('MYSQL_PORT', 3306);
        $database = self::envString('MYSQL_DB');
        $username = self::envString('MYSQL_USER');
        $password = self::envString('MYSQL_PASSWORD');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException('Unable to connect to MySQL for session bootstrap: ' . $e->getMessage(), 0, $e);
        }

        $table = self::quoteIdentifier($sessionName);
        $createSql = sprintf(
            'CREATE TABLE IF NOT EXISTS %s (`id` BIGINT NOT NULL PRIMARY KEY) DEFAULT CHARSET = utf8mb4',
            $table
        );
        $pdo->exec($createSql);

        $pdo->exec(sprintf('INSERT IGNORE INTO %s (`id`) VALUES (0)', $table));
    }

    private static function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private static function envString(string $key, ?string $default = null): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            if ($default !== null) {
                return $default;
            }

            throw new RuntimeException(sprintf('Environment variable %s is required for session bootstrap.', $key));
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

            throw new RuntimeException(sprintf('Environment variable %s is required for session bootstrap.', $key));
        }

        return (int) $value;
    }
}
