<?php

namespace Tests\Unit;

use RuntimeException;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\DatabaseDumpWriter;

class DatabaseDumpWriterTest extends TestCase
{
  public function test_auto_strategy_uses_direct_mysql_cli_execution(): void
  {
    config()->set('app.env', 'local');
    config()->set('app.url', 'https://webblocks-cms.test');
    config()->set('cms.backup.execution', 'auto');

    $writer = app(DatabaseDumpWriter::class);

    $this->assertSame('direct', $writer->resolveMysqlDumpStrategy());
  }

  public function test_forced_execution_strategy_is_respected(): void
  {
    config()->set('cms.backup.execution', 'direct');

    $writer = app(DatabaseDumpWriter::class);

    $this->assertSame('direct', $writer->resolveMysqlDumpStrategy());
  }

  public function test_invalid_execution_strategy_throws_clear_exception(): void
  {
    config()->set('cms.backup.execution', 'invalid');

    $writer = app(DatabaseDumpWriter::class);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Invalid cms.backup.execution value [invalid]. Supported values: auto, direct.');

    $writer->resolveMysqlDumpStrategy();
  }
}
