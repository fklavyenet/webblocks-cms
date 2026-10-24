<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\PluginLifecycle;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Tests\TestCase;

class PluginStateMemoTest extends TestCase
{
  private string $root;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->root = sys_get_temp_dir().'/wb-plugin-memo-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->root.'/example/1.0.0');
    config(['webblocks-plugins.install.root' => $this->root]);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->root);
    parent::tearDown();
  }

  #[Test]
  public function scalar_checks_are_bounded_by_coordinates_and_never_hydrate_documents(): void
  {
    $this->state();
    $this->state('1.0.0');
    $repository = app(InstalledPluginRepository::class);
    $queries = $this->queries(function () use ($repository): void {
      for ($index = 0; $index < 50; $index++) {
        $this->assertFalse($repository->isDisabled('example'));
        $this->assertSame('1.0.0', $repository->enabledVersion('example'));
        $this->assertFalse($repository->runtimeFailed('example', '1.0.0'));
        $this->assertFalse($repository->isDisabled('absent'));
      }
    });
    $selects = array_filter($queries, fn ($query) => str_contains($query['query'], 'from "wbcms_plugin_lifecycles"'));
    $this->assertCount(3, $selects); // Two present coordinates and one absent key.
    $this->assertSame([], array_values(array_filter($queries, fn ($q) => str_contains($q['query'], 'wbcms_persistent_'))));
    app()->instance('request', Request::create('/next'));
    $this->assertNotEmpty($this->queries(fn () => $repository->enabledVersion('example')));
    app()->forgetScopedInstances();
    $this->assertNotEmpty($this->queries(fn () => $repository->enabledVersion('example')));
  }

  #[Test]
  public function payloads_remain_complete_and_database_writes_and_rollback_invalidate_reads(): void
  {
    $state = $this->state('1.0.0');
    $repository = app(InstalledPluginRepository::class);
    $expected = ['status' => 'success', 'details' => ['nested' => ['kept', 'intact']]];
    $this->assertSame($expected, $repository->setupResult('example', '1.0.0'));
    $this->assertSame([], $this->queries(fn () => $repository->setupResult('example', '1.0.0')));
    DB::beginTransaction();
    $state->forceFill(['setup_result' => ['status' => 'failed']])->save();
    $this->assertSame('failed', $repository->setupResult('example', '1.0.0')['status']);
    DB::rollBack();
    $this->assertSame($expected, $repository->setupResult('example', '1.0.0'));
    $this->assertFalse($repository->isDisabled('new-example'));
    PluginLifecycle::query()->create(['root_hash' => hash('sha256', realpath($this->root)), 'handle' => 'new-example', 'version' => '', 'disabled_at' => now()]);
    $this->assertTrue($repository->isDisabled('new-example'));
  }

  #[Test]
  public function roots_versions_and_mutating_repository_operations_are_isolated(): void
  {
    $this->state();
    $this->state('1.0.0');
    $repository = app(InstalledPluginRepository::class);
    $this->assertSame('1.0.0', $repository->enabledVersion('example'));
    File::ensureDirectoryExists($this->root.'/other');
    config(['webblocks-plugins.install.root' => $this->root.'/other']);
    $this->assertNull($repository->enabledVersion('example'));
    config(['webblocks-plugins.install.root' => $this->root]);
    $repository->disable('example', force: true);
    $this->assertNull($repository->enabledVersion('example'));
    $repository->enable('example', '2.0.0');
    $this->assertSame('2.0.0', $repository->enabledVersion('example'));
    $repository->recordSetupResult('example', '2.0.0', ['status' => 'failed']);
    $this->assertSame('failed', $repository->setupResult('example', '2.0.0')['status']);
    $this->assertSame('success', $repository->setupResult('example', '1.0.0')['status']);
  }

  #[Test]
  public function emergency_files_take_effect_even_after_successful_cached_reads(): void
  {
    $this->state();
    $this->state('1.0.0');
    $repository = app(InstalledPluginRepository::class);
    $this->assertFalse($repository->isDisabled('example'));
    $this->assertFalse($repository->runtimeFailed('example', '1.0.0'));
    File::put($this->root.'/example/.disabled', 'disabled');
    File::put($this->root.'/example/1.0.0/.runtime-failure', 'failed');
    $this->assertTrue($repository->isDisabled('example'));
    $this->assertNull($repository->enabledVersion('example'));
    $this->assertTrue($repository->runtimeFailed('example', '1.0.0'));
  }

  #[Test]
  public function database_errors_fail_closed_and_are_not_memoized_as_success_or_absence(): void
  {
    $this->state();
    $fail = true;
    app('events')->listen(QueryExecuted::class, function (QueryExecuted $query) use (&$fail): void {
      if ($fail && str_contains($query->sql, 'from "wbcms_plugin_lifecycles"')) {
        throw new RuntimeException('Synthetic database failure');
      }
    });
    $repository = app(InstalledPluginRepository::class);
    $this->assertTrue($repository->isDisabled('example'));
    $this->assertNull($repository->enabledVersion('example'));
    $this->assertTrue($repository->runtimeFailed('example', '1.0.0'));
    $fail = false;
    $this->assertFalse($repository->isDisabled('example'));
    $this->assertSame('1.0.0', $repository->enabledVersion('example'));
  }

  private function state(string $version = ''): PluginLifecycle
  {
    return PluginLifecycle::query()->create(['root_hash' => hash('sha256', realpath($this->root)), 'handle' => 'example', 'version' => $version, 'enabled_version' => '1.0.0', 'setup_result' => ['status' => 'success', 'details' => ['nested' => ['kept', 'intact']]], 'previous_state' => ['version' => '0.9.0']]);
  }

  private function queries(callable $work): array
  {
    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
      $work();

      return DB::getQueryLog();
    } finally {
      DB::disableQueryLog();
    }
  }
}
