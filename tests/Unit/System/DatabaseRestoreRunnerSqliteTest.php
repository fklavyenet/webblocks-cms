<?php

namespace Tests\Unit\System;

use WebBlocks\Cms\Support\System\DatabaseExecutionStrategyResolver;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use WebBlocks\Cms\Support\System\SqlDumpContentValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseRestoreRunnerSqliteTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function sqlite_restore_can_run_inside_an_existing_transaction(): void
    {
        $path = storage_path('app/testing-database-restores/sqlite-restore.sql');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, implode(PHP_EOL, [
            'BEGIN TRANSACTION;',
            "INSERT INTO system_settings (key, value, created_at, updated_at) VALUES ('restore.probe', 'ok', '2026-05-20 00:00:00', '2026-05-20 00:00:00');",
            'COMMIT;',
        ]));

        try {
            DB::transaction(function () use ($path): void {
                $result = app(DatabaseRestoreRunner::class)->restoreFrom($path);

                $this->assertSame('sqlite', $result['driver']);
                $this->assertSame('pdo_unprepared', $result['strategy']);
            });
        } finally {
            File::delete($path);
        }
    }
}
