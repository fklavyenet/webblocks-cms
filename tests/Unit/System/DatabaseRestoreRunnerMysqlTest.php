<?php

namespace Tests\Unit\System;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\DatabaseExecutionStrategyResolver;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use WebBlocks\Cms\Support\System\SqlDumpContentValidator;

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

  #[Test]
  public function auto_restore_strategy_uses_direct_for_native_local(): void
  {
    config()->set('app.env', 'local');
    config()->set('app.url', 'https://webblocks-cms.test');
    config()->set('cms.backup.execution', 'auto');

    $runner = app(DatabaseRestoreRunner::class);

    $this->assertSame('direct', $runner->resolveMysqlRestoreStrategy());
  }

  #[Test]
  public function direct_restore_command_uses_configured_database_port(): void
  {
    config()->set('cms.backup.execution', 'direct');
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql', [
      'driver' => 'mysql',
      'host' => '127.0.0.1',
      'port' => '3307',
      'database' => 'webblocks_cms_native',
      'username' => 'webblocks_cms_native',
      'password' => 'local-secret',
    ]);

    $sqlPath = storage_path('app/testing-database-restores/native-port.sql');
    File::ensureDirectoryExists(dirname($sqlPath));
    File::put($sqlPath, 'CREATE TABLE native_restore_test (id int);');

    $runner = new class(app(DatabaseExecutionStrategyResolver::class), app(SqlDumpContentValidator::class)) extends DatabaseRestoreRunner
    {
      public array $command = [];

      protected function findMysqlClientBinary(string $driver): string
      {
        return 'mysql';
      }

      protected function makeRestoreProcess(array $command): Process
      {
        $this->command = $command;

        return new Process(['php', '-r', 'stream_get_contents(STDIN);']);
      }
    };

    try {
      $runner->restoreFrom($sqlPath);

      $this->assertSame('mysql', $runner->command[0]);
      $this->assertContains('--host=127.0.0.1', $runner->command);
      $this->assertContains('--port=3307', $runner->command);
      $this->assertContains('webblocks_cms_native', $runner->command);
    } finally {
      File::delete($sqlPath);
    }
  }

  #[Test]
  public function restore_failure_message_masks_password_and_keeps_connection_details(): void
  {
    config()->set('cms.backup.execution', 'direct');
    config()->set('database.default', 'mysql');
    config()->set('database.connections.mysql', [
      'driver' => 'mysql',
      'host' => '127.0.0.1',
      'port' => '3307',
      'database' => 'webblocks_cms_native',
      'username' => 'webblocks_cms_native',
      'password' => 'local-secret',
    ]);

    $sqlPath = storage_path('app/testing-database-restores/native-error.sql');
    File::ensureDirectoryExists(dirname($sqlPath));
    File::put($sqlPath, 'CREATE TABLE native_restore_test (id int);');

    $runner = new class(app(DatabaseExecutionStrategyResolver::class), app(SqlDumpContentValidator::class)) extends DatabaseRestoreRunner
    {
      protected function findMysqlClientBinary(string $driver): string
      {
        return 'mysql';
      }

      protected function makeRestoreProcess(array $command): Process
      {
        return new Process(['php', '-r', 'fwrite(STDERR, "Access denied using password local-secret"); exit(1);']);
      }
    };

    try {
      $this->expectException(RuntimeException::class);
      $this->expectExceptionMessage('Access denied using password [masked]');
      $this->expectExceptionMessage('database=webblocks_cms_native, username=webblocks_cms_native, host=127.0.0.1, port=3307');

      $runner->restoreFrom($sqlPath);
    } finally {
      File::delete($sqlPath);
    }
  }
}
