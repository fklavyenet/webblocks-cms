<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemBackup;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\CmsComposerState;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunReconciler;
use WebBlocks\Cms\Support\System\Updates\UpdateChainSchema;
use WebBlocks\Cms\Support\System\Updates\UpdateInstaller;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class UpdateChainSchemaRepairTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', MaintenanceTestUser::class);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->actingAs($this->cmsActor(MaintenanceTestUser::forceCreate(['name' => 'Fixture admin', 'email' => 'schema@example.test',
      'password' => 'unused', 'role' => 'super_admin', 'is_active' => true])));
    config(['webblocks-updates.chains.enabled' => true, 'webblocks-updates.enabled' => true]);
    Http::preventStrayRequests();
  }

  public static function gaps(): array
  {
    return [['table'], ['protocol'], ['step_column'], ['both']];
  }

  #[DataProvider('gaps')]
  public function test_panel_and_cli_refuse_incomplete_schema_before_contacting_publisher(string $gap): void
  {
    $this->introduceGap($gap);
    $this->mock(AdminUpdateLauncher::class)->shouldNotReceive('launch');
    $message = __('webblocks-cms::update_schema.missing');

    $this->postJson(route('admin.system.updates.store'))
      ->assertConflict()->assertExactJson(['message' => $message, 'error' => $message]);
    $this->artisan('webblocks:updates:run', ['--force' => true])->expectsOutput($message)->assertExitCode(1);

    Http::assertNothingSent();
    $this->assertSame(0, AdminUpdateOperation::count());
  }

  public function test_panel_creates_a_verified_backup_before_running_only_the_pending_chain_migration(): void
  {
    $this->introduceGap('both');
    $this->useBackupStorage();
    $notifications = $this->mock(PanelNotificationQuery::class);
    $notifications->allows('forUser')->andReturnNull();
    $notifications->allows('pendingCommentsForUser')->andReturn(0);
    $notifications->allows('pluginItemsForUser')->andReturn([]);
    Http::fake(['*/api/updates/latest*' => Http::response(['data' => ['version' => '99.0.0', 'artifact_url' => 'https://release.example.test/cms.zip']])]);

    $this->get(route('admin.system.updates.index'))->assertOk()
      ->assertSee(__('webblocks-cms::update_schema.repair'))->assertDontSee('data-wb-update-submit');
    $this->post(route('admin.system.updates.repair-schema'))->assertRedirect(route('admin.system.updates.index'))->assertSessionHas('status');

    $backup = SystemBackup::sole();
    $this->assertTrue($backup->isSuccessful());
    $this->assertTrue($backup->includes_database);
    $archive = new ZipArchive;
    $this->assertTrue($archive->open(app(SystemBackupManager::class)->archiveDisk()->path($backup->archive_path)));
    try {
      $dump = $archive->getFromName('database/database.sql');
      $this->assertIsString($dump);
      $this->assertStringNotContainsString('wbcms_update_chain_steps', $dump);
      $this->assertStringNotContainsString('chain_protocol', $dump);
    } finally {
      $archive->close();
    }
    $this->assertDatabaseHas('migrations', ['migration' => UpdateChainSchema::MIGRATION]);
    $this->assertTrue(app(UpdateChainSchema::class)->ready());
    $this->get(route('admin.system.updates.index'))->assertOk()->assertDontSee('data-wb-update-schema')->assertSee('data-wb-update-submit');
    $this->post(route('admin.system.updates.repair-schema'))->assertRedirect();
    $this->assertSame(1, SystemBackup::count());
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/updates/plan'));
  }

  public function test_backup_failure_and_recorded_partial_migration_leave_the_database_untouched(): void
  {
    $this->introduceGap('table');
    $this->useBackupStorage();
    $this->post(route('admin.system.updates.repair-schema'))->assertSessionHasErrors('update_schema');
    $this->assertFalse(Schema::hasTable('wbcms_update_chain_steps'));
    $this->assertSame(0, SystemBackup::count());

    DB::table('migrations')->where('migration', UpdateChainSchema::MIGRATION)->delete();
    $this->mock(SystemBackupManager::class)->expects('createPreUpdateBackup')->andReturn(new SystemBackup(['status' => SystemBackup::STATUS_FAILED]));
    $this->post(route('admin.system.updates.repair-schema'))->assertSessionHasErrors('update_schema');
    $this->assertFalse(Schema::hasTable('wbcms_update_chain_steps'));
  }

  public function test_finalizer_does_not_record_success_when_migrations_leave_a_schema_gap(): void
  {
    $this->introduceGap('table');
    $this->mock(CmsComposerState::class)->expects('assertCurrent');
    $installer = $this->mock(UpdateInstaller::class);
    $installer->expects('installDependencies');
    $installer->expects('runPostInstallCommands');
    $installer->shouldNotReceive('syncRuntimeAssets');
    $this->mock(InstalledVersionStore::class)->shouldNotReceive('persist');

    $this->artisan('webblocks:updates:finalize', ['--force' => true])
      ->expectsOutput(__('webblocks-cms::update_schema.missing'))->assertExitCode(1);
  }

  public function test_publisher_timeout_with_complete_schema_does_not_report_a_schema_problem(): void
  {
    Http::fake(['*/api/updates/plan*' => Http::failedConnection()]);
    $this->mock(AdminUpdateLauncher::class)->shouldNotReceive('launch');
    $message = __('webblocks-cms::admin.update_chain.unavailable');

    $this->postJson(route('admin.system.updates.store'))
      ->assertConflict()->assertExactJson(['message' => $message, 'error' => $message]);
    $this->assertSame(0, AdminUpdateOperation::count());
  }

  public function test_reconciliation_requires_the_schema_even_if_the_logged_package_version_matches(): void
  {
    $this->introduceGap('step_column');
    $version = WebBlocks::version();
    $run = SystemUpdateRun::create(['from_version' => '1.137.0', 'to_version' => $version, 'status' => 'failed',
      'output' => 'Post-update version verified as '.$version.' from canonical WebBlocks version source.']);

    $this->assertNull(app(SystemUpdateRunReconciler::class)->reconcile($run));
    $this->assertSame('failed', $run->fresh()->status);
  }

  private function introduceGap(string $gap): void
  {
    if (in_array($gap, ['table', 'both'], true)) {
      Schema::drop('wbcms_update_chain_steps');
    }
    if ($gap === 'step_column') {
      Schema::table('wbcms_update_chain_steps', fn (Blueprint $table) => $table->dropColumn('signature'));
    }
    if ($gap === 'both') {
      Schema::table('wbcms_admin_update_operations', fn (Blueprint $table) => $table->dropColumn(['chain_protocol', 'product', 'channel', 'last_verified_version', 'maintenance_owned']));
      Schema::table('wbcms_admin_update_operations', fn (Blueprint $table) => $table->unsignedBigInteger('user_id')->nullable(false)->change());
      DB::table('migrations')->where('migration', UpdateChainSchema::MIGRATION)->delete();
    } elseif ($gap === 'protocol') {
      Schema::table('wbcms_admin_update_operations', fn (Blueprint $table) => $table->dropColumn('chain_protocol'));
    }
  }

  private function useBackupStorage(): void
  {
    $backups = Storage::fake('backups');
    $public = Storage::fake('public');
    config(['filesystems.disks.backups.root' => $backups->path(''), 'filesystems.disks.public.root' => $public->path('')]);
  }
}
