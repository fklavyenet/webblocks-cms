<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Route;
use Monolog\Handler\NullHandler;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use WebBlocks\Cms\Actions\Plugins\RecoverPlugin;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginBootProbe;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRecoveryMode;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginRuntimeRegistrar;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class PluginSafetyTest extends TestCase
{
  private string $fixtureRoot;

  protected function setUp(): void
  {
    parent::setUp();
    $this->fixtureRoot = sys_get_temp_dir().'/wb-plugin-safety-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->fixtureRoot.'/plugins');
    config(['webblocks-plugins.install.root' => $this->fixtureRoot.'/plugins']);
    Route::get('/webadmin', fn () => 'Core admin')->name('admin.dashboard');
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->fixtureRoot);
    parent::tearDown();
  }

  public static function brokenPackages(): array
  {
    return [
      'missing parent' => ['<?php namespace SafetyFixture; class Provider extends MissingParent {}'],
      'parse error' => ['<?php namespace SafetyFixture; class Provider { broken php syntax'],
      'provider boot error' => ['<?php namespace SafetyFixture; class Provider extends \Illuminate\Support\ServiceProvider { public function boot(): void { throw new \RuntimeException("synthetic-secret"); } }'],
      'conditional provider boot error' => ['<?php namespace SafetyFixture; class Provider extends \Illuminate\Support\ServiceProvider { public function boot(): void { if (app(\WebBlocks\Cms\Support\Plugins\PluginRegistry::class)->isActive("safety-example")) { throw new \RuntimeException("synthetic-secret"); } } }'],
      'conditional source error' => ['<?php namespace SafetyFixture; if (app(\WebBlocks\Cms\Support\Plugins\PluginRegistry::class)->isActive("safety-example")) { throw new \RuntimeException("synthetic-secret"); } class Provider {}'],
      'early successful exit' => ['<?php exit(0);'],
      'timeout' => ['<?php sleep(5);'],
      'route registration error' => ['<?php namespace SafetyFixture; class Provider { public static function definition() { return \WebBlocks\Cms\Support\Plugins\PluginDefinition::make("safety-example")->version("2.0.0")->provider(self::class)->adminRoutes(function () { throw new \RuntimeException("synthetic-secret"); }); } }'],
    ];
  }

  #[DataProvider('brokenPackages')]
  public function test_failed_update_without_migrations_preserves_the_working_package(string $source): void
  {
    $this->isolatedHost();
    config(['webblocks-plugins.install.boot_timeout_seconds' => 1]);
    $installer = app(PluginZipInstaller::class);
    $repository = app(InstalledPluginRepository::class);
    $installer->install($this->zip('1.0.0'));
    $repository->enable('safety-example', '1.0.0');
    try {
      $installer->update($this->zip('2.0.0', $source), 'safety-example', '1.0.0');
      $this->fail('A broken package must never activate.');
    } catch (RuntimeException $exception) {
      $this->assertStringContainsString('startup check', $exception->getMessage());
      $this->assertStringNotContainsString('synthetic-secret', $exception->getMessage());
    }
    $this->assertSame('1.0.0', $repository->enabledVersion('safety-example'));
    $this->assertDirectoryExists($this->packagePath('1.0.0'));
    $this->assertDirectoryDoesNotExist($this->packagePath('2.0.0'));
    $this->assertFalse($repository->isDisabled('safety-example'));
  }

  public function test_successful_update_retains_and_can_restore_the_previous_package(): void
  {
    $this->isolatedHost();
    $installer = app(PluginZipInstaller::class);
    $repository = app(InstalledPluginRepository::class);
    $installer->install($this->zip('1.0.0'));
    $repository->enable('safety-example', '1.0.0');
    $installer->update($this->zip('2.0.0'), 'safety-example', '1.0.0');
    $this->assertSame('2.0.0', $repository->enabledVersion('safety-example'));
    $this->assertDirectoryExists($this->packagePath('1.0.0'));
    $this->assertCount(1, $repository->installed());
    $this->assertTrue($repository->previous('safety-example')['rollback_safe']);
    app(RecoverPlugin::class)->handle('safety-example', 'restore');
    $this->assertSame('1.0.0', $repository->enabledVersion('safety-example'));
    $this->assertSame('1.0.0', $repository->installed()[0]['manifest']['version']);
    $repository->uninstall('safety-example', '1.0.0');
    $this->assertDirectoryDoesNotExist($this->packagePath('1.0.0'));
    $this->assertDirectoryDoesNotExist($this->packagePath('2.0.0'));
    $this->assertSame([], $repository->installed());
  }

  public function test_probe_requires_its_completion_marker_and_redacts_subprocess_output(): void
  {
    Process::fake(['*' => Process::result(output: 'synthetic-secret')]);
    $plugin = PluginDefinition::make('safety-example')->version('1.0.0');
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('startup check');
    app(PluginBootProbe::class)->check($plugin);
  }

  public function test_runtime_missing_class_quarantines_only_that_plugin_and_blocks_config_override(): void
  {
    $path = $this->extract('1.0.0', '<?php namespace RuntimeBrokenSafety; class Provider extends AbsentParent {}');
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('safety-example', '1.0.0');
    config(['webblocks-plugins.enabled.safety-example' => true]);
    $this->app->forgetInstance(PluginRegistry::class);
    $registry = app(PluginRegistry::class);
    $this->assertFalse($registry->isEnabled('safety-example'));
    $this->assertTrue($repository->runtimeFailed('safety-example', '1.0.0'));
    $this->assertTrue($repository->isDisabled('safety-example'));
    $this->assertNull($repository->enabledVersion('safety-example'));
    $this->assertNotNull($registry->get('safety-example'));
    // A second request's registry must not try loading the broken source again.
    File::put($path.'/src/Provider.php', '<?php throw new \RuntimeException("must not load");');
    $this->app->forgetInstance(PluginRegistry::class);
    $this->assertFalse(app(PluginRegistry::class)->isEnabled('safety-example'));
    $this->get('/webadmin')->assertOk()->assertSee('Core admin');
  }

  public function test_catalog_loader_resolves_inheritance_without_filename_ordering(): void
  {
    $path = $this->extract('1.0.0', '<?php namespace InheritanceSafety; class Provider {}');
    File::put($path.'/src/AChild.php', '<?php namespace InheritanceSafety; class AChild extends ZParent {}');
    File::put($path.'/src/ZParent.php', '<?php namespace InheritanceSafety; class ZParent {}');
    $installed = app(InstalledPluginRepository::class)->findVersion('safety-example', '1.0.0');
    app(InstalledPluginDefinitionFactory::class)->make($installed['manifest'], $path, true);
    $this->assertTrue(is_subclass_of('InheritanceSafety\AChild', 'InheritanceSafety\ZParent'));
  }

  public function test_route_failure_removes_partial_plugin_routes_and_keeps_core_and_other_plugins(): void
  {
    $path = $this->extract('1.0.0');
    app(InstalledPluginRepository::class)->enable('safety-example', '1.0.0');
    $plugin = PluginDefinition::make('safety-example')->version('1.0.0')->installPath($path)
      ->adminRoutes(function (): void {
      Route::get('/partial', fn () => 'bad')->name('partial');
      })
      ->publicRoutes(function (): void {
      Route::get('/partial', fn () => 'bad');
      throw new RuntimeException('synthetic-secret');
      });
    $healthy = PluginDefinition::make('healthy-example')->adminRoutes(function (): void {
    Route::get('/healthy', fn () => 'healthy')->name('healthy');
    });
    $registry = new PluginRegistry(['safety-example' => true, 'healthy-example' => true]);
    $registry->register($plugin)->register($healthy);
    $this->app->instance(PluginRegistry::class, $registry);
    app(PluginRuntimeRegistrar::class)->register();
    $this->assertFalse(Route::has('webblocks.plugins.safety_example.partial'));
    $this->assertTrue(Route::has('webblocks.plugins.healthy_example.healthy'));
    $this->assertFalse($registry->isEnabled('safety-example'));
    $this->assertTrue($registry->isEnabled('healthy-example'));
    $this->get('/webadmin')->assertOk();
  }

  public function test_recovery_screen_and_login_do_not_load_plugin_code_and_enforce_authorization(): void
  {
    $path = $this->extract('1.0.0', '<?php throw new \RuntimeException("must never load in recovery");');
    app(InstalledPluginRepository::class)->enable('safety-example', '1.0.0');
    $this->get('/webadmin/plugin-recovery')->assertRedirect('/webadmin/plugin-recovery/login');
    $this->get('/webadmin/plugin-recovery/login')->assertOk()->assertSee('/webadmin/plugin-recovery/login');
    $this->actingAs($this->user(false));
    $this->get('/webadmin/plugin-recovery')->assertForbidden();
    $this->post('/webadmin/plugin-recovery/safety-example', ['operation' => 'disable'])->assertForbidden();
    $this->actingAs($this->user(true));
    $this->get('/webadmin/plugin-recovery')->assertOk()->assertSee('Plugin recovery');
    $this->assertTrue(app(PluginRecoveryMode::class)->active());
    $this->app->forgetInstance(PluginRegistry::class);
    $this->assertSame([], app(PluginRegistry::class)->all());
    $this->post('/webadmin/plugin-recovery/safety-example', ['operation' => 'disable'])->assertRedirect('/webadmin/plugin-recovery');
    $this->assertNull(app(InstalledPluginRepository::class)->enabledVersion('safety-example'));
    $this->assertFileExists($path.'/src/Provider.php');
  }

  public function test_recovery_rejects_invalid_operations_csrf_and_unsafe_database_rollback(): void
  {
    $this->extract('1.0.0');
    $repository = app(InstalledPluginRepository::class);
    $repository->recordPrevious('safety-example', '1.0.0', '2.0.0', false);
    $this->actingAs($this->user(true));
    $this->postJson('/webadmin/plugin-recovery/safety-example', ['operation' => 'erase'])->assertUnprocessable();
    $this->from('/webadmin/plugin-recovery')->post('/webadmin/plugin-recovery/safety-example', ['operation' => 'restore'])->assertSessionHasErrors('plugin');
    config(['app.env' => 'production']);
    $this->app->instance('env', 'production');
    $this->post('/webadmin/plugin-recovery/safety-example', ['operation' => 'disable'])->assertStatus(419);
  }

  public function test_fresh_http_recovery_login_bypasses_even_a_plugin_that_exits_the_process(): void
  {
    $this->isolatedHost();
    $path = $this->extract('1.0.0', '<?php exit(23);');
    app(InstalledPluginRepository::class)->enable('safety-example', '1.0.0');
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    $host = $this->fixtureRoot.'/host';
    $script = '<?php require '.var_export($autoload, true).'; $app = Illuminate\\Foundation\\Application::configure(basePath: __DIR__)->withProviders([WebBlocks\\Cms\\WebBlocksCmsServiceProvider::class])->withMiddleware()->withExceptions(function ($exceptions) { $exceptions->render(function (\\Throwable $exception) { return new Illuminate\\Http\\JsonResponse(["error" => $exception->getMessage()], 500); }); })->create(); $request = Illuminate\\Http\\Request::create("http://localhost/webadmin/plugin-recovery/login"); $kernel = $app->make(Illuminate\\Contracts\\Http\\Kernel::class); $response = $kernel->handle($request); echo $response->getStatusCode(); if ($response->getStatusCode() !== 200) { echo $response->getContent(); } $kernel->terminate($request, $response);';
    File::put($host.'/recovery.php', $script);
    $result = Process::path($host)->env(['WEBBLOCKS_PLUGIN_SAFE_MODE' => false])->run([PHP_BINARY, 'recovery.php']);
    $this->assertTrue($result->successful(), $result->errorOutput());
    $this->assertSame('200', trim($result->output()));
    $this->assertFileExists($path.'/src/Provider.php');
  }

  private function user(bool $super): User
  {
    return new class($super) extends User
    {
      public function __construct(private bool $super = false)
      {
      parent::__construct();
      }

      public function isSuperAdmin(): bool
      {
      return $this->super;
      }
    };
  }

  private function packagePath(string $version): string
  {
    return $this->fixtureRoot.'/plugins/safety-example/'.$version;
  }

  private function zip(string $version, ?string $source = null): string
  {
    $file = $this->fixtureRoot.'/'.$version.'.zip';
    $zip = new ZipArchive;
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $provider = 'SafetyFixture\\Provider';
    if ($source !== null && preg_match('/namespace\s+([^;]+);/', $source, $match)) {
      $provider = trim($match[1]).'\\Provider';
    }
    $zip->addFromString('webblocks-plugin.json', json_encode(['handle' => 'safety-example', 'label' => 'Safety Example', 'version' => $version, 'provider' => $provider, 'required_cms_version' => '^1.88.0', 'migrations' => []]));
    $zip->addFromString('src/Provider.php', $source ?? '<?php namespace SafetyFixture; class Provider {}');
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $zip->addFromString('resources/lang/'.$locale.'/admin.php', '<?php return [];');
    }
    $zip->close();

    return $file;
  }

  private function extract(string $version, ?string $source = null): string
  {
    $zip = new ZipArchive;
    $zip->open($this->zip($version, $source));
    $zip->extractTo($this->packagePath($version));
    $zip->close();

    return $this->packagePath($version);
  }

  private function isolatedHost(): void
  {
    $host = $this->fixtureRoot.'/host';
    foreach (['bootstrap/cache', 'config', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'resources/views', 'public'] as $directory) {
      File::ensureDirectoryExists($host.'/'.$directory);
    }
    File::put($host.'/composer.json', json_encode(['autoload' => ['psr-4' => ['App\\' => 'app/']]]));
    $database = $this->fixtureRoot.'/database.sqlite';
    touch($database);
    config(['database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');
    $config = [
      'app' => ['name' => 'Synthetic Safety Host', 'env' => 'testing', 'key' => config('app.key'), 'locale' => 'en', 'fallback_locale' => 'en', 'timezone' => 'UTC'],
      'database' => config('database'), 'webblocks-cms' => config('webblocks-cms'), 'webblocks-plugins' => config('webblocks-plugins'),
      'cache' => ['default' => 'array', 'stores' => ['array' => ['driver' => 'array']]],
      'session' => ['driver' => 'array'], 'queue' => ['default' => 'sync'],
      'logging' => ['default' => 'null', 'channels' => ['null' => ['driver' => 'monolog', 'handler' => NullHandler::class]]],
    ];
    foreach ($config as $name => $values) {
      File::put($host.'/config/'.$name.'.php', '<?php return '.var_export($values, true).';');
    }
    $autoload = dirname(__DIR__, 2).'/vendor/autoload.php';
    File::put($host.'/artisan', '<?php require '.var_export($autoload, true).'; $app = Illuminate\\Foundation\\Application::configure(basePath: __DIR__)->withProviders([WebBlocks\\Cms\\WebBlocksCmsServiceProvider::class])->create(); exit($app->handleCommand(new Symfony\\Component\\Console\\Input\\ArgvInput));');
    $this->app->setBasePath($host);
  }
}
