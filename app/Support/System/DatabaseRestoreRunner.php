<?php

namespace App\Support\System;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class DatabaseRestoreRunner
{
    public function __construct(
        private readonly DatabaseExecutionStrategyResolver $strategyResolver,
    ) {}

    public function restoreFrom(string $sqlPath, array &$output = []): array
    {
        if (! File::isFile($sqlPath) || filesize($sqlPath) === 0) {
            throw new RuntimeException('Backup database restore requires a non-empty SQL file.');
        }

        $connection = DB::connection();
        $driver = $connection->getDriverName();

        $output[] = 'Preparing database restore for driver '.$driver.'.';

        return match ($driver) {
            'sqlite' => $this->restoreSqlite($connection, $sqlPath, $output),
            'mysql', 'mariadb' => $this->restoreMysqlFamily($connection, $sqlPath, $output),
            default => throw new RuntimeException('Database restore currently supports sqlite, mysql, and mariadb drivers. Active driver: '.$driver.'.'),
        };
    }

    private function restoreSqlite(Connection $connection, string $sqlPath, array &$output): array
    {
        $sql = File::get($sqlPath);

        if (trim($sql) === '') {
            throw new RuntimeException('Backup SQL file is empty.');
        }

        $connection->unprepared($sql);
        DB::purge($connection->getName());
        DB::reconnect($connection->getName());

        $output[] = 'SQLite database restored from '.basename($sqlPath).'.';

        return [
            'driver' => 'sqlite',
            'strategy' => 'pdo_unprepared',
            'connection' => $connection->getName(),
        ];
    }

    private function restoreMysqlFamily(Connection $connection, string $sqlPath, array &$output): array
    {
        $strategy = $this->strategyResolver->resolveMysqlStrategy();
        $config = $connection->getConfig();

        match ($strategy) {
            'ddev' => $this->runDdevMysqlRestore($connection->getDriverName(), $sqlPath, $config),
            default => $this->runDirectMysqlRestore($connection->getDriverName(), $sqlPath, $config),
        };

        DB::purge($connection->getName());
        DB::reconnect($connection->getName());

        $output[] = 'Using '.$this->describeMysqlRestoreCommand($strategy, $connection->getDriverName()).' for database restore.';
        $output[] = 'MySQL-compatible database restored from '.basename($sqlPath).'.';

        return [
            'driver' => $connection->getDriverName(),
            'strategy' => $strategy,
            'connection' => $connection->getName(),
        ];
    }

    private function runDirectMysqlRestore(string $driver, string $sqlPath, array $config): void
    {
        $defaultsFile = $this->createMysqlDefaultsFile($sqlPath, $config);

        try {
            $command = $this->buildDirectMysqlRestoreCommand($driver, $defaultsFile, $config);
            $process = new Process($command);
            $process->setTimeout((int) config('cms.backup.restore_timeout_seconds', 300));

            $handle = fopen($sqlPath, 'rb');

            if ($handle === false) {
                throw new RuntimeException('Backup SQL file could not be opened for restore.');
            }

            try {
                $process->setInput($handle);
                $process->run();
            } finally {
                fclose($handle);
            }

            if (! $process->isSuccessful()) {
                throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database restore command failed.');
            }
        } finally {
            File::delete($defaultsFile);
        }
    }

    private function runDdevMysqlRestore(string $driver, string $sqlPath, array $config): void
    {
        $command = $this->buildDdevMysqlRestoreCommand($driver, $config);
        $process = new Process($command);
        $process->setTimeout((int) config('cms.backup.restore_timeout_seconds', 300));

        $handle = fopen($sqlPath, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Backup SQL file could not be opened for restore.');
        }

        try {
            $process->setInput($handle);
            $process->run();
        } finally {
            fclose($handle);
        }

        if (! $process->isSuccessful()) {
            throw new RuntimeException(trim($process->getErrorOutput()) ?: 'Database restore command failed.');
        }
    }

    private function buildDirectMysqlRestoreCommand(string $driver, string $defaultsFile, array $config): array
    {
        $command = [
            $this->findMysqlClientBinary($driver),
            '--defaults-extra-file='.$defaultsFile,
            '--binary-mode',
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

    private function buildDdevMysqlRestoreCommand(string $driver, array $config): array
    {
        $command = [
            $this->findDdevBinary(),
            'exec',
            '--raw',
            '--',
            $driver === 'mariadb' ? 'mariadb' : 'mysql',
            '--binary-mode',
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

    private function createMysqlDefaultsFile(string $sqlPath, array $config): string
    {
        $defaultsFile = $sqlPath.'.restore.cnf';
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

    private function findMysqlClientBinary(string $driver): string
    {
        $finder = new ExecutableFinder;
        $preferredBinary = $driver === 'mariadb' ? 'mariadb' : 'mysql';
        $fallbackBinary = $driver === 'mariadb' ? 'mysql' : 'mariadb';
        $binary = $finder->find($preferredBinary) ?: $finder->find($fallbackBinary);

        if ($binary === null) {
            throw new RuntimeException('Database restore requires mysql or mariadb for the current MySQL/MariaDB environment, but neither command is available.');
        }

        return $binary;
    }

    private function findDdevBinary(): string
    {
        $binary = (new ExecutableFinder)->find('ddev');

        if ($binary === null) {
            throw new RuntimeException('Database restore requires the ddev command for ddev execution mode, but it is not available.');
        }

        return $binary;
    }

    private function describeMysqlRestoreCommand(string $strategy, string $driver): string
    {
        if ($strategy === 'ddev') {
            return 'ddev exec '.($driver === 'mariadb' ? 'mariadb' : 'mysql');
        }

        return basename($this->findMysqlClientBinary($driver));
    }
}
