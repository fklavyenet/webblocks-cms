<?php

namespace App\Support\System;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
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
        $config = $connection->getConfig();
        $strategy = $this->resolveMysqlDumpStrategy();
        match ($strategy) {
            'ddev' => $this->runDdevMysqlDump($connection->getDriverName(), $destinationPath, $config),
            default => $this->runDirectMysqlDump($destinationPath, $config),
        };

        $output[] = 'Using '.$this->describeMysqlDumpCommand($strategy, $connection->getDriverName()).' for database dump.';
        $output[] = 'MySQL-compatible SQL dump written to '.basename($destinationPath).'.';

        return [
            'driver' => $connection->getDriverName(),
            'strategy' => $strategy,
            'connection' => $connection->getName(),
        ];
    }

    public function resolveMysqlDumpStrategy(): string
    {
        $configuredStrategy = Str::lower((string) config('cms.backup.execution', 'auto'));

        return match ($configuredStrategy) {
            'direct' => 'direct',
            'ddev' => 'ddev',
            'auto', '' => $this->shouldUseDdevStrategy() ? 'ddev' : 'direct',
            default => throw new RuntimeException('Invalid cms.backup.execution value ['.$configuredStrategy.']. Supported values: auto, direct, ddev.'),
        };
    }

    private function runDirectMysqlDump(string $destinationPath, array $config): void
    {
        $defaultsFile = $this->createMysqlDefaultsFile($destinationPath, $config);

        try {
            $command = $this->buildDirectMysqlDumpCommand($destinationPath, $defaultsFile, $config);
            $process = new Process($command);
            $process->setTimeout((int) config('cms.backup.dump_timeout_seconds', 120));
            $process->run();

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database dump command failed.');
            }
        } finally {
            File::delete($defaultsFile);
        }
    }

    private function buildDirectMysqlDumpCommand(string $destinationPath, string $defaultsFile, array $config): array
    {
        $command = [
            $this->findMysqlDumpBinary(),
            '--defaults-extra-file='.$defaultsFile,
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--skip-dump-date',
            '--no-tablespaces',
            '--result-file='.$destinationPath,
            (string) $config['database'],
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        } else {
            $command[] = '--host='.$config['host'];
            $command[] = '--port='.(string) $config['port'];
        }

        return $command;
    }

    private function runDdevMysqlDump(string $driver, string $destinationPath, array $config): void
    {
        $command = $this->buildDdevMysqlDumpCommand($driver, $config);
        $process = new Process($command);
        $process->setTimeout((int) config('cms.backup.dump_timeout_seconds', 120));

        $directory = dirname($destinationPath);
        File::ensureDirectoryExists($directory);

        $handle = fopen($destinationPath, 'wb');

        if ($handle === false) {
            throw new RuntimeException('Database dump destination could not be opened for writing.');
        }

        try {
            $process->run(function (string $type, string $buffer) use ($handle): void {
                if ($type !== Process::OUT) {
                    return;
                }

                fwrite($handle, $buffer);
            });
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            File::delete($destinationPath);

            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database dump command failed.');
        }

        if (! is_file($destinationPath) || filesize($destinationPath) === 0) {
            File::delete($destinationPath);

            throw new RuntimeException('Database dump command completed without writing an SQL file.');
        }
    }

    private function buildDdevMysqlDumpCommand(string $driver, array $config): array
    {
        $command = [
            $this->findDdevBinary(),
            'exec',
            '--raw',
            '--',
            $driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump',
            '--single-transaction',
            '--quick',
            '--skip-comments',
            '--skip-dump-date',
            '--no-tablespaces',
            (string) $config['database'],
        ];

        if (! empty($config['unix_socket'])) {
            $command[] = '--socket='.$config['unix_socket'];
        } else {
            $command[] = '--host='.(string) $config['host'];
            $command[] = '--port='.(string) $config['port'];
        }

        return $command;
    }

    private function createMysqlDefaultsFile(string $destinationPath, array $config): string
    {
        $defaultsFile = $destinationPath.'.cnf';
        $lines = [
            '[client]',
            'user='.(string) ($config['username'] ?? ''),
        ];

        if (($config['password'] ?? null) !== null && $config['password'] !== '') {
            $lines[] = 'password='.(string) $config['password'];
        }

        File::put($defaultsFile, implode(PHP_EOL, $lines).PHP_EOL, true);
        @chmod($defaultsFile, 0600);

        return $defaultsFile;
    }

    private function findMysqlDumpBinary(): string
    {
        $finder = new ExecutableFinder;
        $binary = $finder->find('mysqldump') ?: $finder->find('mariadb-dump');

        if ($binary === null) {
            throw new RuntimeException('Database backup requires mysqldump or mariadb-dump for the current MySQL/MariaDB environment, but neither command is available.');
        }

        return $binary;
    }

    private function findDdevBinary(): string
    {
        $binary = (new ExecutableFinder)->find('ddev');

        if ($binary === null) {
            throw new RuntimeException('Database backup requires the ddev command for ddev execution mode, but it is not available.');
        }

        return $binary;
    }

    private function shouldUseDdevStrategy(): bool
    {
        if (Str::lower((string) config('app.env')) !== 'local') {
            return false;
        }

        if (! $this->isDdevProject()) {
            return false;
        }

        if ($this->isRunningInsideDdev()) {
            return false;
        }

        return $this->isDdevUrl((string) config('app.url')) || $this->isDdevProject();
    }

    private function isDdevProject(): bool
    {
        return File::isDirectory(base_path('.ddev'));
    }

    private function isRunningInsideDdev(): bool
    {
        return ! empty($_SERVER['IS_DDEV_PROJECT'] ?? $_ENV['IS_DDEV_PROJECT'] ?? getenv('IS_DDEV_PROJECT'));
    }

    private function isDdevUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && str_ends_with(Str::lower($host), '.ddev.site');
    }

    private function describeMysqlDumpCommand(string $strategy, string $driver): string
    {
        if ($strategy === 'ddev') {
            return 'ddev exec '.($driver === 'mariadb' ? 'mariadb-dump' : 'mysqldump');
        }

        return basename($this->findMysqlDumpBinary());
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
