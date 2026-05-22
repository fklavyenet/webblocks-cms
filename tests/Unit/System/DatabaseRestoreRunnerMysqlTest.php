<?php

namespace Tests\Unit\System;

use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DatabaseRestoreRunnerMysqlTest extends TestCase
{
    #[Test]
    public function mysql_restore_input_wraps_dumps_with_foreign_key_and_unique_guards(): void
    {
        $sqlPath = storage_path('app/testing-database-restores/mysql-out-of-order.sql');
        File::ensureDirectoryExists(dirname($sqlPath));
        File::put($sqlPath, implode(PHP_EOL, [
            'CREATE TABLE `page_translations` (',
            '  `page_id` bigint unsigned not null,',
            '  `site_id` bigint unsigned not null,',
            '  CONSTRAINT `page_translations_page_id_site_id_foreign` FOREIGN KEY (`page_id`, `site_id`) REFERENCES `pages` (`id`, `site_id`) ON DELETE CASCADE',
            ');',
            'CREATE TABLE `pages` (',
            '  `id` bigint unsigned not null,',
            '  `site_id` bigint unsigned not null,',
            '  UNIQUE KEY `pages_id_site_id_unique` (`id`,`site_id`)',
            ');',
        ]));

        $runner = new class extends DatabaseRestoreRunner
        {
            public function __construct() {}

            public function createGuardedInput(string $sqlPath): string
            {
                return $this->createMysqlRestoreInputFile($sqlPath);
            }
        };

        $guardedPath = $runner->createGuardedInput($sqlPath);

        try {
            $guardedSql = File::get($guardedPath);

            $this->assertStringStartsWith("SET FOREIGN_KEY_CHECKS=0;\nSET UNIQUE_CHECKS=0;\n", $guardedSql);
            $this->assertStringContainsString('CREATE TABLE `page_translations`', $guardedSql);
            $this->assertStringContainsString('CONSTRAINT `page_translations_page_id_site_id_foreign` FOREIGN KEY (`page_id`, `site_id`) REFERENCES `pages` (`id`, `site_id`) ON DELETE CASCADE', $guardedSql);
            $this->assertStringContainsString('CREATE TABLE `pages`', $guardedSql);
            $this->assertStringContainsString('UNIQUE KEY `pages_id_site_id_unique` (`id`,`site_id`)', $guardedSql);
            $this->assertStringEndsWith("\nSET UNIQUE_CHECKS=1;\nSET FOREIGN_KEY_CHECKS=1;\n", $guardedSql);
            $this->assertStringNotContainsString('SET FOREIGN_KEY_CHECKS', File::get($sqlPath));
            $this->assertStringNotContainsString('SET UNIQUE_CHECKS', File::get($sqlPath));
        } finally {
            File::delete($guardedPath);
            File::delete($sqlPath);
        }
    }
}
