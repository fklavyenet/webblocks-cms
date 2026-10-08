<?php

namespace WebBlocks\Cms\Tests\Feature;

use ExampleAutoFixture\Provider;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\NullHandler;
use RuntimeException;
use WebBlocks\Cms\Support\Plugins\InstalledPluginDefinitionFactory;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\Plugins\PluginBootProbe;
use WebBlocks\Cms\Support\Plugins\PluginDatabaseSetup;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginMigrationRunner;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Plugins\PluginZipInstaller;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class AutomaticPluginDatabaseSetupTest extends TestCase
{
  private string $fixtureRoot;

  protected function setUp(): void
  {
    parent::setUp();
    $this->fixtureRoot = sys_get_temp_dir().'/wb-plugin-auto-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->fixtureRoot);
    config(['webblocks-plugins.install.root' => $this->fixtureRoot.'/plugins']);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->fixtureRoot);
    parent::tearDown();
  }

  public function test_install_and_update_apply_migrations_in_a_fresh_host_process(): void
  {
    $this->isolatedHost();
    $installer = app(PluginZipInstaller::class);
    $repository = app(InstalledPluginRepository::class);
    $installed = $installer->install($this->zip('1.0.0'));

    $this->assertTrue(Schema::hasTable('example_plugin_messages'));
    $this->assertNull($repository->enabledVersion('example-auto'));
    $this->assertSame('completed', $installed['database_setup']['status']);
    $this->assertSame('completed', $repository->setupResult('example-auto', '1.0.0')['status']);
    DB::table('example_plugin_messages')->insert(['body' => 'Preserve this conversation']);
    $repository->enable('example-auto', '1.0.0');
    app(InstalledPluginDefinitionFactory::class)->make($this->manifest('1.0.0'), $installed['path'], true);
    $this->assertSame('1.0.0', Provider::VERSION);

    $updated = $installer->update($this->zip('2.0.0'), 'example-auto', '1.0.0');

    $this->assertSame('2.0.0', $repository->enabledVersion('example-auto'));
    $this->assertSame('completed', $updated['database_setup']['status']);
    $this->assertSame('Preserve this conversation', DB::table('example_plugin_messages')->value('body'));
    // The old provider remains loaded in this HTTP-like parent process. The
    // migration must nevertheless execute against the new provider in the child.
    $this->assertSame('1.0.0', Provider::VERSION);
    $this->assertSame('2.0.0', DB::table('example_plugin_messages')->value('source_version'));
    $this->assertCount(1, $repository->installed());
    $this->assertFalse(app(PluginMigrationRunner::class)->hasPendingMigrations(
      app(InstalledPluginDefinitionFactory::class)->make($this->manifest('2.0.0'), $updated['path'], false),
    ));
  }

  public function test_failed_update_is_disabled_and_retry_preserves_data_and_does_not_repeat_completed_migrations(): void
  {
    $installer = app(PluginZipInstaller::class);
    // Exercise real migrations via the registered command; the separate test
    // above covers actual PHP process isolation with a shared SQLite file.
    $this->mock(PluginBootProbe::class)->shouldReceive('check');
    Process::fake(fn () => $this->runMigrationCommand('1.0.0'));
    $installer->install($this->zip('1.0.0'));
    $repository = app(InstalledPluginRepository::class);
    $repository->enable('example-auto', '1.0.0');
    DB::table('example_plugin_messages')->insert(['body' => 'Existing message']);
    Process::fake(['*' => Process::result(errorOutput: 'SQL contains secret-password', exitCode: 1)]);

    try {
      $installer->update($this->zip('2.0.0'), 'example-auto', '1.0.0');
      $this->fail('Failed migration must not report a successful update.');
    } catch (RuntimeException $exception) {
      $this->assertStringNotContainsString('secret-password', $exception->getMessage());
      $this->assertStringContainsString('database update failed', $exception->getMessage());
    }
    $this->assertNull($repository->enabledVersion('example-auto'));
    $this->assertSame('failed', $repository->setupResult('example-auto', '2.0.0')['status']);
    config(['webblocks-plugins.enabled.example-auto' => true]);
    $this->app->forgetInstance(PluginRegistry::class);
    $this->assertFalse(app(PluginRegistry::class)->isConfiguredEnabled('example-auto'));
    $this->assertSame('Existing message', DB::table('example_plugin_messages')->value('body'));

    Process::fake(fn () => $this->runMigrationCommand('2.0.0'));
    $installed = $repository->installed()[0];
    $definition = app(InstalledPluginDefinitionFactory::class)->make($installed['manifest'], $installed['path'], false);
    app(PluginDatabaseSetup::class)->run($definition);
    $repository->enable('example-auto', '2.0.0');
    $this->assertSame('completed', $repository->setupResult('example-auto', '2.0.0')['status']);
    $this->assertSame('Existing message', DB::table('example_plugin_messages')->value('body'));
    Process::swap(new Factory);
    Process::fake();
    $this->assertFalse(app(PluginDatabaseSetup::class)->run($definition)['ran']);
    Process::assertNothingRan();
  }

  public function test_successful_process_with_pending_migrations_is_still_a_failure(): void
  {
    $this->mock(PluginBootProbe::class)->shouldReceive('check');
    Process::fake();
    try {
      app(PluginZipInstaller::class)->install($this->zip('1.0.0'));
      $this->fail('A zero process exit without applied migrations is not success.');
    } catch (RuntimeException) {
      $repository = app(InstalledPluginRepository::class);
      $this->assertNull($repository->enabledVersion('example-auto'));
      $this->assertSame('failed', $repository->setupResult('example-auto', '1.0.0')['status']);
    }
  }

  public function test_plugin_without_migrations_still_requires_a_startup_probe_and_stays_disabled(): void
  {
    Process::fake();
    Process::fake(['*' => Process::result(output: PluginBootProbe::SUCCESS)]);
    $result = app(PluginZipInstaller::class)->install($this->zip('1.0.0', migrations: false));
    Process::assertRan(fn ($process) => in_array('cms:plugin-probe', $process->command, true));
    $this->assertFalse($result['database_setup']['ran']);
    $this->assertNull(app(InstalledPluginRepository::class)->enabledVersion('example-auto'));
  }

  public function test_nonzero_artisan_exit_is_not_recorded_as_a_completed_migration(): void
  {
    $package = $this->fixtureRoot.'/migration-error';
    File::ensureDirectoryExists($package.'/database/migrations');
    File::put($package.'/database/migrations/2026_10_01_000000_failure.php', '<?php return null;');
    $plugin = PluginDefinition::make('failed-example')
      ->version('1.0.0')->installPath($package)->migrations(['database/migrations']);
    Artisan::swap(new class
    {
      public function call(string $command, array $arguments): int
      {
        return 1;
      }
    });
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Migration command did not complete');
    app(PluginMigrationRunner::class)->run($plugin);
  }

  private function runMigrationCommand(string $version): mixed
  {
    $exitCode = Artisan::call('cms:plugin-migrate', ['handle' => 'example-auto', 'version' => $version]);

    return Process::result(exitCode: $exitCode);
  }

  private function manifest(string $version): array
  {
    return ['handle' => 'example-auto', 'label' => 'Example Plugin', 'version' => $version,
      'provider' => 'ExampleAutoFixture\\Provider', 'required_cms_version' => '^1.88.0',
      'migrations' => ['database/migrations']];
  }

  private function zip(string $version, bool $migrations = true): string
  {
    $file = $this->fixtureRoot.'/'.$version.'.zip';
    $zip = new ZipArchive;
    $zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $manifest = $this->manifest($version);
    if (! $migrations) {
    $manifest['migrations'] = [];
    }
    $zip->addFromString('webblocks-plugin.json', json_encode($manifest));
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $zip->addFromString('resources/lang/'.$locale.'/admin.php', '<?php return [];');
    }
    $zip->addFromString('src/Provider.php', '<?php namespace ExampleAutoFixture; class Provider { public const VERSION = '.var_export($version, true).'; }');
    if ($migrations) {
      $zip->addFromString('database/migrations/2026_10_01_000000_create_messages.php', <<<'MIGRATION'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void { Schema::create('example_plugin_messages', function(Blueprint $table) { $table->id(); $table->string('body'); }); }
};
MIGRATION);
      if ($version === '2.0.0') {
        $zip->addFromString('database/migrations/2026_10_02_000000_add_source_version.php', <<<'MIGRATION'
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
return new class extends Migration {
  public function up(): void {
    Schema::table('example_plugin_messages', function(Blueprint $table) { $table->string('source_version')->nullable(); });
    DB::table('example_plugin_messages')->update(['source_version' => \ExampleAutoFixture\Provider::VERSION]);
  }
};
MIGRATION);
      }
    }
    $zip->close();

    return $file;
  }

  private function isolatedHost(): void
  {
    $host = $this->fixtureRoot.'/host';
    foreach (['bootstrap/cache', 'config', 'storage/framework/views', 'storage/framework/cache/data', 'storage/logs', 'resources/views', 'public'] as $directory) {
      File::ensureDirectoryExists($host.'/'.$directory);
    }
    $database = $this->fixtureRoot.'/database.sqlite';
    touch($database);
    config(['database.connections.sqlite.database' => $database]);
    DB::purge('sqlite');
    $config = [
      'app' => ['name' => 'Synthetic Plugin Host', 'env' => 'testing', 'key' => config('app.key'), 'locale' => 'en', 'fallback_locale' => 'en', 'timezone' => 'UTC'],
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
