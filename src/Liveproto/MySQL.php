<?php

declare(strict_types=1);

namespace Tak\Liveproto\Database;

use PDO;
use PDOException;
use RuntimeException;
use Tak\Liveproto\Utils\Logging;
use Tak\Liveproto\Utils\Tools;

final class MySQL implements AbstractDB, AbstractPeers
{
    private PDO $pdo;

    public function __construct(object $config)
    {
        error_log('[LiveProto Override] Initializing PDO-based MySQL adapter');
        $this->pdo = $this->createConnection();
    }

    public function init(string $table): bool
    {
        $quotedTable = $this->quoteIdentifier($table);

        if ($this->tableExists($table) && $this->hasRows($table)) {
            return false;
        }

        $this->pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (`id` BIGINT NOT NULL PRIMARY KEY) DEFAULT CHARSET = utf8mb4',
                $quotedTable
            )
        );

        $statement = $this->pdo->prepare(
            sprintf(
                'INSERT INTO %s (`id`) VALUES (0) ON DUPLICATE KEY UPDATE `id` = VALUES(`id`)',
                $quotedTable
            )
        );
        $statement->execute();

        return true;
    }

    public function set(string $table, string $key, mixed $value, string $type): void
    {
        try {
            $this->addColumnIfMissing($table, $key, $type);

            $stmt = $this->pdo->prepare(
                sprintf(
                    'UPDATE %s SET %s = :value LIMIT 1',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($key)
                )
            );
            $stmt->execute(['value' => $value]);
        } catch (PDOException $error) {
            Logging::log('MySQL', $error->getMessage(), E_WARNING);
        }
    }

    public function get(string $table): array|null
    {
        $stmt = $this->pdo->query(
            sprintf('SELECT * FROM %s LIMIT 1', $this->quoteIdentifier($table))
        );

        if ($stmt === false) {
            return null;
        }

        $result = $stmt->fetch();

        return $result === false ? null : $result;
    }

    public function delete(string $table, string $key): void
    {
        $this->pdo->exec(
            sprintf(
                'ALTER TABLE %s DROP COLUMN %s',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($key)
            )
        );
    }

    public function exists(string $table, string $key): bool
    {
        return $this->columnExists($table, $key);
    }

    public function initPeer(string $table): bool
    {
        if ($this->tableExists($table)) {
            return false;
        }

        $this->pdo->exec(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (`id` BIGINT PRIMARY KEY) DEFAULT CHARSET = utf8mb4',
                $this->quoteIdentifier($table)
            )
        );

        return true;
    }

    public function setPeer(string $table, mixed $value): void
    {
        try {
            $value = Tools::marshal($value);

            foreach ($value as $column => $columnValue) {
                $this->addColumnIfMissing($table, $column, Tools::inferType($columnValue));
            }

            $columns = array_keys($value);
            $quotedColumns = array_map(fn(string $col): string => $this->quoteIdentifier($col), $columns);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);
            $assignments = array_map(
                fn(string $col): string => sprintf('%1$s = VALUES(%1$s)', $this->quoteIdentifier($col)),
                $columns
            );

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                $this->quoteIdentifier($table),
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $assignments)
            );

            $this->pdo->prepare($sql)->execute($value);
        } catch (PDOException $error) {
            Logging::log('MySQL', $error->getMessage(), E_WARNING);
        }
    }

    public function getPeer(string $table, string $key, mixed $value): array|null
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SELECT * FROM %s WHERE %s = :value LIMIT 1',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($key)
            )
        );
        $stmt->execute(['value' => $value]);

        return $stmt->fetch() ?: null;
    }

    public function deletePeer(string $table, string $key, mixed $value): void
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'DELETE FROM %s WHERE %s = :value',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($key)
            )
        );
        $stmt->execute(['value' => $value]);
    }

    private function createConnection(): PDO
    {
        $host = $this->envString('MYSQL_HOST');
        $port = $this->envInt('MYSQL_PORT', 3306);
        $database = $this->envString('MYSQL_DB');
        $username = $this->envString('MYSQL_USER');
        $password = $this->envString('MYSQL_PASSWORD');

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $port, $database);

        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $exception) {
            throw new RuntimeException('Unable to connect to MySQL: ' . $exception->getMessage(), 0, $exception);
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute(['table' => $table]);

        return $stmt->fetchColumn() !== false;
    }

    private function hasRows(string $table): bool
    {
        $stmt = $this->pdo->query(
            sprintf('SELECT 1 FROM %s LIMIT 1', $this->quoteIdentifier($table))
        );

        return $stmt !== false && $stmt->fetchColumn() !== false;
    }

    private function addColumnIfMissing(string $table, string $column, string $type): void
    {
        if ($this->columnExists($table, $column)) {
            return;
        }

        $this->pdo->exec(
            sprintf(
                'ALTER TABLE %s ADD COLUMN %s %s',
                $this->quoteIdentifier($table),
                $this->quoteIdentifier($column),
                $type
            )
        );
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->pdo->prepare(
            sprintf(
                'SHOW COLUMNS FROM %s LIKE :column',
                $this->quoteIdentifier($table)
            )
        );
        $stmt->execute(['column' => $column]);

        return $stmt->fetchColumn() !== false;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function envString(string $key): string
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            throw new RuntimeException(sprintf('Environment variable %s is required for LiveProto MySQL adapter.', $key));
        }

        return (string) $value;
    }

    private function envInt(string $key, int $default): int
    {
        $value = getenv($key);
        if ($value === false || $value === '') {
            return $default;
        }

        return (int) $value;
    }
}
