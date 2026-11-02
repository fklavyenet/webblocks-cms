<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Support\Facades\File;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\BufferedOutput;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\Updates\CmsComposerBridge;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Tests\TestCase;

class CmsComposerBridgeGuardTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public function test_other_commands_preserve_another_products_configuration(): void
  {
    config()->set('publisher-client.product', 'another-product');
    app(CmsComposerBridge::class)->beforeMigration(new CommandStarting('cache:clear', new ArrayInput([]), new BufferedOutput));
    $this->assertSame('another-product', config('publisher-client.product'));
  }

  public function test_a_package_migration_without_an_active_update_never_starts_composer(): void
  {
    $this->withMigration(function (CommandStarting $event): void {
      config()->set('publisher-client.product', 'another-product');
      app(CmsComposerBridge::class)->beforeMigration($event);
      $this->assertSame('another-product', config('publisher-client.product'));
    });
  }

  public function test_a_running_sql_record_without_a_live_parent_is_not_sufficient_authority(): void
  {
    $this->withMigration(function (CommandStarting $event): void {
      SystemUpdateRun::create(['from_version' => '1.112.1', 'to_version' => '1.112.2', 'status' => 'running', 'started_at' => now()]);
      $this->expectException(UpdateException::class);
      app(CmsComposerBridge::class)->beforeMigration($event);
    });
  }

  private function withMigration(callable $check): void
  {
    $base = base_path();
    $root = sys_get_temp_dir().'/cms-bridge-guard-'.bin2hex(random_bytes(6));
    $path = $root.'/vendor/fklavyenet/webblocks-cms/database/migrations/updates';
    File::ensureDirectoryExists($path);
    $input = new ArrayInput(['--path' => [$path], '--realpath' => true, '--force' => true], new InputDefinition([
      new InputOption('path', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY),
      new InputOption('realpath', null, InputOption::VALUE_NONE),
      new InputOption('force', null, InputOption::VALUE_NONE),
    ]));
    app()->setBasePath($root);
    try {
      $check(new CommandStarting('migrate', $input, new BufferedOutput));
    } finally {
      app()->setBasePath($base);
      File::deleteDirectory($root);
    }
  }
}
