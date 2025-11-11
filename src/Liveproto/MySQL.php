<?php

declare(strict_types=1);

namespace Tak\Liveproto\Database;

use Amp\Mysql\MysqlConnectionPool;
use Amp\Sync\LocalMutex;
use Revolt\EventLoop;
use Tak\Liveproto\Utils\Logging;
use Tak\Liveproto\Utils\Tools;

final class MySQL implements AbstractDB, AbstractPeers
{
    private MysqlConnectionPool $connection;

    public function __construct(object $config)
    {
        $this->connection = new MysqlConnectionPool($config);
    }

    public function init(string $table): bool
    {
        $quotedTable = $this->quoteIdentifier($table);
        $tableExists = $this->connection
            ->query(sprintf('SHOW TABLES LIKE %s', $this->quoteValue($table)))
            ->fetchRow();

        if ($tableExists && $this->connection
            ->query(sprintf('SELECT * FROM %s LIMIT 1', $quotedTable))
            ->fetchRow()) {
            return false;
        }

        $this->connection->query(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (`id` BIGINT NOT NULL PRIMARY KEY) DEFAULT CHARSET = utf8mb4',
                $quotedTable
            )
        );

        $this->connection
            ->prepare(
                sprintf(
                    'INSERT INTO %s (`id`) VALUES (:id) ON DUPLICATE KEY UPDATE `id` = VALUES(`id`)',
                    $quotedTable
                )
            )
            ->execute(['id' => 0]);

        return true;
    }

    public function set(string $table, string $key, mixed $value, string $type): void
    {
        static $mutex = new LocalMutex();
        $lock = $mutex->acquire();

        $quotedTable = $this->quoteIdentifier($table);
        $quotedColumn = $this->quoteIdentifier($key);

        try {
            if (!$this->columnExists($table, $key)) {
                $this->connection->query(
                    sprintf('ALTER TABLE %s ADD COLUMN %s %s', $quotedTable, $quotedColumn, $type)
                );
            }

            $this->connection
                ->prepare(sprintf('UPDATE %s SET %s = :new', $quotedTable, $quotedColumn))
                ->execute(['new' => $value]);
        } catch (\Throwable $error) {
            Logging::log('MySQL', $error->getMessage(), E_WARNING);
        } finally {
            EventLoop::queue($lock->release(...));
        }
    }

    public function get(string $table): array|null
    {
        return $this->connection
            ->query(sprintf('SELECT * FROM %s', $this->quoteIdentifier($table)))
            ->fetchRow();
    }

    public function delete(string $table, string $key): void
    {
        $this->connection->query(
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
        $quotedTable = $this->quoteIdentifier($table);
        $tableExists = $this->connection
            ->query(sprintf('SHOW TABLES LIKE %s', $this->quoteValue($table)))
            ->fetchRow();

        if ($tableExists) {
            return false;
        }

        $this->connection->query(
            sprintf(
                'CREATE TABLE IF NOT EXISTS %s (`id` BIGINT PRIMARY KEY) DEFAULT CHARSET = utf8mb4',
                $quotedTable
            )
        );

        return true;
    }

    public function setPeer(string $table, mixed $value): void
    {
        static $mutex = new LocalMutex();
        $lock = $mutex->acquire();

        $quotedTable = $this->quoteIdentifier($table);

        try {
            foreach ($value as $column => $columnValue) {
                $type = Tools::inferType($columnValue);
                if (!$this->columnExists($table, $column)) {
                    $this->connection->query(
                        sprintf(
                            'ALTER TABLE %s ADD COLUMN %s %s',
                            $quotedTable,
                            $this->quoteIdentifier($column),
                            $type
                        )
                    );
                }
            }

            $columns = array_keys($value);
            $placeholders = array_map(static fn(string $col): string => ':' . $col, $columns);
            $assignments = array_map(
                fn(string $col): string => sprintf('%1$s = VALUES(%1$s)', $this->quoteIdentifier($col)),
                $columns
            );
            $quotedColumns = array_map(fn(string $col): string => $this->quoteIdentifier($col), $columns);

            $sql = sprintf(
                'INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE %s',
                $quotedTable,
                implode(', ', $quotedColumns),
                implode(', ', $placeholders),
                implode(', ', $assignments)
            );

            $this->connection->prepare($sql)->execute($value);
        } catch (\Throwable $error) {
            Logging::log('MySQL', $error->getMessage(), E_WARNING);
        } finally {
            EventLoop::queue($lock->release(...));
        }
    }

    public function getPeer(string $table, string $key, mixed $value): array|null
    {
        return $this->connection
            ->prepare(
                sprintf(
                    'SELECT * FROM %s WHERE %s = :value',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($key)
                )
            )
            ->execute(['value' => $value])
            ->fetchRow();
    }

    public function deletePeer(string $table, string $key, mixed $value): void
    {
        $this->connection
            ->prepare(
                sprintf(
                    'DELETE FROM %s WHERE %s = :value',
                    $this->quoteIdentifier($table),
                    $this->quoteIdentifier($key)
                )
            )
            ->execute(['value' => $value]);
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = $this->connection
            ->query(
                sprintf(
                    'SHOW COLUMNS FROM %s LIKE %s',
                    $this->quoteIdentifier($table),
                    $this->quoteValue($column)
                )
            );

        return $result->fetchRow() !== null;
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    private function quoteValue(string $value): string
    {
        return "'" . str_replace("'", "''", $value) . "'";
    }
}
