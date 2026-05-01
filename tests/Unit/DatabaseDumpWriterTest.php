<?php

namespace Tests\Unit;

use App\Support\System\DatabaseDumpWriter;
use App\Support\System\DatabaseExecutionStrategyResolver;
use App\Support\System\SqlDumpContentValidator;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class DatabaseDumpWriterTest extends TestCase
{
    public function test_auto_strategy_uses_ddev_for_local_ddev_projects(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.url', 'https://example.ddev.site');
        config()->set('cms.backup.execution', 'auto');

        putenv('IS_DDEV_PROJECT');
        unset($_ENV['IS_DDEV_PROJECT'], $_SERVER['IS_DDEV_PROJECT']);
        File::ensureDirectoryExists(base_path('.ddev'));

        $writer = app(DatabaseDumpWriter::class);

        $this->assertSame('ddev', $writer->resolveMysqlDumpStrategy());
    }

    public function test_auto_strategy_falls_back_to_direct_outside_ddev(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.url', 'http://localhost');
        config()->set('cms.backup.execution', 'auto');

        putenv('IS_DDEV_PROJECT');
        unset($_ENV['IS_DDEV_PROJECT'], $_SERVER['IS_DDEV_PROJECT']);

        $writer = app(DatabaseDumpWriter::class);

        $expected = file_exists(base_path('.ddev')) ? 'ddev' : 'direct';

        $this->assertSame($expected, $writer->resolveMysqlDumpStrategy());
    }

    public function test_auto_strategy_uses_direct_when_already_running_inside_ddev(): void
    {
        config()->set('app.env', 'local');
        config()->set('app.url', 'https://example.ddev.site');
        config()->set('cms.backup.execution', 'auto');

        File::ensureDirectoryExists(base_path('.ddev'));
        putenv('IS_DDEV_PROJECT=true');
        $_ENV['IS_DDEV_PROJECT'] = 'true';
        $_SERVER['IS_DDEV_PROJECT'] = 'true';

        $writer = app(DatabaseDumpWriter::class);

        $this->assertSame('direct', $writer->resolveMysqlDumpStrategy());

        putenv('IS_DDEV_PROJECT');
        unset($_ENV['IS_DDEV_PROJECT'], $_SERVER['IS_DDEV_PROJECT']);
    }

    public function test_forced_execution_strategy_is_respected(): void
    {
        config()->set('cms.backup.execution', 'ddev');

        $writer = app(DatabaseDumpWriter::class);

        $this->assertSame('ddev', $writer->resolveMysqlDumpStrategy());
    }

    public function test_invalid_execution_strategy_throws_clear_exception(): void
    {
        config()->set('cms.backup.execution', 'invalid');

        $writer = app(DatabaseDumpWriter::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid cms.backup.execution value [invalid]. Supported values: auto, direct, ddev.');

        $writer->resolveMysqlDumpStrategy();
    }

    public function test_ddev_dump_writer_captures_only_stdout_as_sql(): void
    {
        $writer = new class(app(DatabaseExecutionStrategyResolver::class), app(SqlDumpContentValidator::class)) extends DatabaseDumpWriter
        {
            public function __construct(DatabaseExecutionStrategyResolver $resolver, SqlDumpContentValidator $validator)
            {
                parent::__construct($resolver, $validator);
            }

            public function captureDump(string $destinationPath): void
            {
                $this->runDdevDump($destinationPath);
            }

            protected function makeDumpProcess(array $command): Process
            {
                return new Process(['php', '-r', 'fwrite(STDOUT, "-- MySQL dump\nCREATE TABLE test (id int);\n"); fwrite(STDERR, "You executed ddev exec --raw -- mysqldump\n");']);
            }

            private function runDdevDump(string $destinationPath): void
            {
                $method = new \ReflectionMethod(DatabaseDumpWriter::class, 'runDdevMysqlDump');
                $method->setAccessible(true);
                $method->invoke($this, 'mysql', $destinationPath, ['database' => 'demo', 'host' => 'db', 'port' => 3306]);
            }
        };

        $directory = storage_path('app/testing-system-backups/dump-writer');
        File::ensureDirectoryExists($directory);
        $destinationPath = $directory.'/database.sql';

        $writer->captureDump($destinationPath);

        $this->assertSame("-- MySQL dump\nCREATE TABLE test (id int);\n", File::get($destinationPath));
        $this->assertStringNotContainsString('You executed', File::get($destinationPath));

        File::deleteDirectory($directory);
    }

    public function test_ddev_dump_writer_rejects_command_text_stdout(): void
    {
        $writer = new class(app(DatabaseExecutionStrategyResolver::class), app(SqlDumpContentValidator::class)) extends DatabaseDumpWriter
        {
            public function __construct(DatabaseExecutionStrategyResolver $resolver, SqlDumpContentValidator $validator)
            {
                parent::__construct($resolver, $validator);
            }

            public function captureDump(string $destinationPath): void
            {
                $this->runDdevDump($destinationPath);
            }

            protected function makeDumpProcess(array $command): Process
            {
                return new Process(['php', '-r', 'fwrite(STDOUT, "You executed `ddev exec --raw -- mysqldump`\n");']);
            }

            private function runDdevDump(string $destinationPath): void
            {
                $method = new \ReflectionMethod(DatabaseDumpWriter::class, 'runDdevMysqlDump');
                $method->setAccessible(true);
                $method->invoke($this, 'mysql', $destinationPath, ['database' => 'demo', 'host' => 'db', 'port' => 3306]);
            }
        };

        $directory = storage_path('app/testing-system-backups/dump-writer-invalid');
        File::ensureDirectoryExists($directory);
        $destinationPath = $directory.'/database.sql';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Generated backup SQL dump contains command output instead of SQL.');

        try {
            $writer->captureDump($destinationPath);
        } finally {
            $this->assertFalse(File::exists($destinationPath));
            File::deleteDirectory($directory);
        }
    }
}
