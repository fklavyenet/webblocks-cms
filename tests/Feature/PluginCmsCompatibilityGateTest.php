<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginDatabaseSetup;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRefresher;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class PluginCmsCompatibilityGateTest extends TestCase
{
  private const API = '/webadmin/api/plugins/compatibility-example';

  private string $fixtureRoot;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->fixtureRoot = sys_get_temp_dir().'/wb-plugin-compatibility-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->pluginPath());
    config(['webblocks-plugins.install.root' => $this->fixtureRoot.'/plugins']);
    // Route permission/authentication contracts have separate coverage. Exercise
    // the real controller response, repository and lifecycle services here.
    $this->withoutMiddleware();
    Process::fake();
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->fixtureRoot);
    parent::tearDown();
  }

  #[Test]
  public function api_enable_rejects_incompatible_plugins_without_running_setup_or_enabling(): void
  {
    $this->register('>=99.0.0');
    $this->mock(PluginDatabaseSetup::class)->shouldNotReceive('run');
    $this->mock(PluginRuntimeRefresher::class)->shouldNotReceive('refresh');

    $this->postJson(self::API.'/enable')->assertStatus(409)
      ->assertJsonPath('code', 'plugin_incompatible')
      ->assertJsonPath('message', 'Requires WebBlocks CMS >=99.0.0; installed CMS is '.WebBlocks::version().'.');

    $this->assertNull(app(InstalledPluginRepository::class)->enabledVersion('compatibility-example'));
    $this->assertFileDoesNotExist($this->pluginPath().'/setup.json');
    Process::assertNothingRan();
  }

  #[Test]
  public function api_setup_rejects_enabled_incompatible_plugins_and_preserves_state(): void
  {
    $this->register('>=99.0.0', true);
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('compatibility-example', '1.0.0');
    $repository->recordSetupResult('compatibility-example', '1.0.0', ['status' => 'completed', 'ran' => false, 'paths_count' => 0]);
    $before = File::get($this->pluginPath().'/setup.json');
    $this->mock(PluginDatabaseSetup::class)->shouldNotReceive('run');

    $this->postJson(self::API.'/setup')->assertStatus(409)->assertJsonPath('code', 'plugin_incompatible');

    $this->assertSame('1.0.0', $repository->enabledVersion('compatibility-example'));
    $this->assertSame($before, File::get($this->pluginPath().'/setup.json'));
    Process::assertNothingRan();
  }

  #[Test]
  public function api_reports_unsupported_constraints_without_server_errors_or_writes(): void
  {
    $this->register('unsupported-constraint');
    $this->mock(PluginDatabaseSetup::class)->shouldNotReceive('run');

    $this->postJson(self::API.'/enable')->assertStatus(409)->assertJsonPath('code', 'plugin_incompatible');
    $this->assertFileDoesNotExist($this->pluginPath().'/setup.json');
  }

  #[Test]
  public function compatible_plugins_can_still_be_enabled_through_the_api(): void
  {
    $this->register('^'.WebBlocks::version());
    $this->mock(PluginRuntimeRefresher::class)->shouldReceive('refresh')->once()->with(registerRoutes: true);

    $this->postJson(self::API.'/enable')->assertOk()->assertJsonPath('ok', true);

    $repository = app(InstalledPluginRepository::class);
    $this->assertSame('1.0.0', $repository->enabledVersion('compatibility-example'));
    $this->assertSame('completed', $repository->setupResult('compatibility-example', '1.0.0')['status']);
    Process::assertNothingRan();
  }

  #[Test]
  public function database_setup_rejects_before_overwriting_completed_state_or_disabling(): void
  {
    $plugin = $this->register('>=99.0.0', true);
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('compatibility-example', '1.0.0');
    $repository->recordSetupResult('compatibility-example', '1.0.0', ['status' => 'completed']);
    $before = File::get($this->pluginPath().'/setup.json');

    try {
      app(PluginDatabaseSetup::class)->run($plugin);
      $this->fail('An incompatible plugin must not reach database setup.');
    } catch (RuntimeException $exception) {
      $this->assertStringContainsString('Requires WebBlocks CMS >=99.0.0', $exception->getMessage());
    }

    $this->assertSame($before, File::get($this->pluginPath().'/setup.json'));
    $this->assertSame('1.0.0', $repository->enabledVersion('compatibility-example'));
    Process::assertNothingRan();
  }

  #[Test]
  public function direct_migration_repair_cannot_delete_records_for_an_incompatible_plugin(): void
  {
    if (! Schema::hasTable('migrations')) {
      Schema::create('migrations', function (Blueprint $table): void {
        $table->increments('id');
        $table->string('migration');
        $table->integer('batch');
      });
    }
    $plugin = $this->register('>=99.0.0')->migrations(['database/migrations']);
    File::ensureDirectoryExists($this->pluginPath().'/database/migrations');
    File::put($this->pluginPath().'/database/migrations/2099_01_01_000000_future_schema.php', '<?php return null;');
    DB::table('migrations')->insert(['migration' => '2099_01_01_000000_future_schema', 'batch' => 1]);

    try {
      app(PluginMigrationRunner::class)->run($plugin, repairRecordedMigrations: true);
      $this->fail('An incompatible plugin must not reach migration repair.');
    } catch (RuntimeException $exception) {
      $this->assertStringContainsString('Requires WebBlocks CMS >=99.0.0', $exception->getMessage());
    }

    $this->assertSame(1, DB::table('migrations')->where('migration', '2099_01_01_000000_future_schema')->count());
  }

  #[Test]
  public function cli_rejects_incompatible_plugins_before_loading_provider_code(): void
  {
    $this->register('>=99.0.0');
    File::ensureDirectoryExists($this->pluginPath().'/src');
    $marker = $this->fixtureRoot.'/provider-loaded';
    File::put($this->pluginPath().'/src/Provider.php', '<?php namespace CompatibilityFixture; file_put_contents('.var_export($marker, true).', "loaded"); class Provider {}');

    $this->assertSame(1, Artisan::call('cms:plugin-migrate', ['handle' => 'compatibility-example', 'version' => '1.0.0']));
    $this->assertStringContainsString('Requires WebBlocks CMS >=99.0.0', Artisan::output());
    $this->assertFileDoesNotExist($marker);
    $this->assertFileDoesNotExist($this->pluginPath().'/setup.json');
    Process::assertNothingRan();
  }

  #[Test]
  public function panel_enable_and_setup_reject_the_same_constraint_before_database_setup(): void
  {
    $this->register('>=99.0.0', true);
    $this->mock(PluginDatabaseSetup::class)->shouldNotReceive('run');
    $this->actingAs(new class extends User
    {
      public function isSuperAdmin(): bool
      {
        return true;
      }
    });

    foreach (['enable', 'setup'] as $operation) {
      $this->from('/webadmin/system/plugins/compatibility-example')
        ->post('/webadmin/system/plugins/compatibility-example/'.$operation)
        ->assertRedirect('/webadmin/system/plugins/compatibility-example')
        ->assertSessionHasErrors(['plugin' => 'Requires WebBlocks CMS >=99.0.0; installed CMS is '.WebBlocks::version().'.']);
    }

    $this->assertFileDoesNotExist($this->pluginPath().'/setup.json');
    Process::assertNothingRan();
  }

  private function register(string $constraint, bool $enabled = false): PluginDefinition
  {
    $manifest = ['handle' => 'compatibility-example', 'label' => 'Compatibility Fixture', 'version' => '1.0.0', 'provider' => 'CompatibilityFixture\\Provider', 'required_cms_version' => $constraint, 'migrations' => []];
    File::put($this->pluginPath().'/webblocks-plugin.json', json_encode($manifest, JSON_THROW_ON_ERROR));
    $plugin = PluginDefinition::make('compatibility-example')->label('Compatibility Fixture')->version('1.0.0')
      ->provider('CompatibilityFixture\\Provider')->requiresCms($constraint)->source('manual upload')->installPath($this->pluginPath());
    $registry = new PluginRegistry(['compatibility-example' => $enabled]);
    $registry->register($plugin);
    $this->app->instance(PluginRegistry::class, $registry);

    return $plugin;
  }

  private function pluginPath(): string
  {
    return $this->fixtureRoot.'/plugins/compatibility-example/1.0.0';
  }
}
