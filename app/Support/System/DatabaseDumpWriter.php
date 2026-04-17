<?php

namespace App\Support\System;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseDumpWriter
{
    public function dumpTo(string $destinationPath, array &$output = []): array
    {
        File::ensureDirectoryExists(dirname($destinationPath));

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $output[] = 'Preparing database dump for driver '.$driver.'.';

        return match ($driver) {
            'sqlite' => $this->dumpSqlite($connection, $destinationPath, $output),
            'mysql', 'mariadb' => $this->dumpMysqlFamily($connection, $destinationPath, $output),
            default => throw new RuntimeException('Database backups currently support sqlite, mysql, and mariadb drivers. Active driver: '.$driver.'.'),
        };
    }

    private function dumpSqlite(Connection $connection, string $destinationPath, array &$output): array
    {
        $pdo = $connection->getPdo();
        $tables = $connection->select("select name, sql from sqlite_master where type = 'table' and name not like 'sqlite_%' and sql is not null order by name");
        $secondaryObjects = $connection->select("select type, name, tbl_name, sql from sqlite_master where type in ('index', 'trigger', 'view') and name not like 'sqlite_%' and sql is not null order by case type when 'view' then 0 when 'index' then 1 else 2 end, name");

        $lines = [
            '-- WebBlocks CMS SQLite backup',
            '-- Generated at '.now()->toIso8601String(),
            'PRAGMA foreign_keys=OFF;',
            'BEGIN TRANSACTION;',
            '',
        ];

        foreach ($tables as $table) {
            $tableName = $table->name;
            $quotedTable = $this->quoteIdentifier($tableName);
            $lines[] = 'DROP TABLE IF EXISTS '.$quotedTable.';';
            $lines[] = rtrim((string) $table->sql, ';').';';

            $rows = $connection->table($tableName)->get();
            foreach ($rows as $row) {
                $values = [];
                $columns = [];

                foreach ((array) $row as $column => $value) {
                    $columns[] = $this->quoteIdentifier((string) $column);
                    $values[] = $this->quoteSqliteValue($pdo, $value);
                }

                $lines[] = 'INSERT INTO '.$quotedTable.' ('.implode(', ', $columns).') VALUES ('.implode(', ', $values).');';
            }

            $lines[] = '';
        }

        foreach ($secondaryObjects as $object) {
            $dropStatement = match ($object->type) {
                'view' => 'DROP VIEW IF EXISTS '.$this->quoteIdentifier($object->name).';',
                'index' => 'DROP INDEX IF EXISTS '.$this->quoteIdentifier($object->name).';',
                'trigger' => 'DROP TRIGGER IF EXISTS '.$this->quoteIdentifier($object->name).';',
                default => null,
            };

            if ($dropStatement !== null) {
                $lines[] = $dropStatement;
            }

            $lines[] = rtrim((string) $object->sql, ';').';';
            $lines[] = '';
        }

        $lines[] = 'COMMIT;';

        File::put($destinationPath, implode(PHP_EOL, $lines));

        $output[] = 'SQLite SQL dump written to '.basename($destinationPath).'.';

        return [
            'driver' => 'sqlite',
            'strategy' => 'php_sql_export',
            'connection' => $connection->getName(),
        ];
    }

    private function dumpMysqlFamily(Connection $connection, string $destinationPath, array &$output): array
    {
        $finder = new ExecutableFinder;
        $binary = $finder->find('mysqldump') ?: $finder->find('mariadb-dump');

        if ($binary === null) {
            throw new RuntimeException('Database backup requires mysqldump or mariadb-dump for the current MySQL/MariaDB environment, but neither command is available.');
        }

        $config = $connection->getConfig();
        $command = [
            $binary,
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--skip-dump-date',
            '--no-tablespaces',
            '--result-file='.$destinationPath,
            '--user='.$config['username'],
            (string) $config['database'],
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        } else {
            $command[] = '--host='.$config['host'];
            $command[] = '--port='.(string) $config['port'];
        }

        $process = new Process($command, null, array_filter([
            'MYSQL_PWD' => $config['password'] ?? null,
        ], fn ($value) => $value !== null));

        $process->setTimeout(120);
        $process->run();

        $output[] = 'Using '.basename($binary).' for database dump.';

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database dump command failed.');
        }

        $output[] = 'MySQL-compatible SQL dump written to '.basename($destinationPath).'.';

        return [
            'driver' => $connection->getDriverName(),
            'strategy' => basename($binary),
            'connection' => $connection->getName(),
        ];
    }

    private function quoteIdentifier(string $value): string
    {
        return '"'.str_replace('"', '""', $value).'"';
    }

    private function quoteSqliteValue(\PDO $pdo, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return $pdo->quote((string) $value);
    }
}
