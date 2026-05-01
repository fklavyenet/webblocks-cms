<?php

namespace Tests\Unit;

use App\Support\System\DatabaseDumpWriter;
use Illuminate\Support\Facades\File;
use RuntimeException;
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
}
