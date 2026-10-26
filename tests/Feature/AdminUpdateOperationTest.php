<?php

namespace WebBlocks\Cms\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use WebBlocks\Cms\Actions\System\StartAdminSystemUpdate;
use WebBlocks\Cms\Http\Controllers\Admin\SystemUpdateController;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\System\DatabaseDumpWriter;
use WebBlocks\Cms\Support\System\DatabaseRestoreRunner;
use WebBlocks\Cms\Support\System\SqlDumpContentValidator;
use WebBlocks\Cms\Support\System\Updates\AdminApprovedUpdateClient;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateTargetChanged;
use WebBlocks\Cms\Support\System\Updates\CmsPackageRollback;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ProgressReporter;
use WebBlocks\Cms\Support\Updates\Client\Updates\RunHeartbeat;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateProgress;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class AdminUpdateOperationTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function operation(array $attributes = []): AdminUpdateOperation
  {
    return AdminUpdateOperation::create(array_merge([
      'id' => (string) Str::uuid(), 'user_id' => 1, 'from_version' => '1.0.0',
      'target_version' => WebBlocks::version(), 'checksum_sha256' => str_repeat('a', 64),
      'status' => 'queued', 'started_at' => now(),
    ], $attributes));
  }

  public function test_indicator_restores_cached_progress_messages_after_another_product_changes_them(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()]);
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      app()->setLocale($locale);
      $expected = require dirname(__DIR__, 2).'/src/Support/Updates/Client/Resources/lang/'.$locale.'/updates.php';
      foreach (['running', 'success', 'failed', 'restored', 'cancelled'] as $status) {
        $key = $status === 'failed' ? 'failure_message' : $status;
        app('translator')->addLines(['updates.progress.'.$key => 'Foreign status'], $locale, 'publisher-client');
        $operation->update(['status' => $status]);
        $request = Request::create('/', 'GET', ['operation' => $operation->id]);

        $response = app(SystemUpdateController::class)->indicator($request);

        $this->assertSame($expected['progress'][$key], $response->getData(true)['progress']['message']);
        $this->assertSame($status, $response->getData(true)['progress']['state']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
      }
    }
  }

  public function test_shared_reporter_exposes_durable_step_durations_and_failure_state(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()]);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'running', 'started_at' => now()]);
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $progress->start($run);
    $this->freezeTime();
    $reporter = app(ProgressReporter::class);
    $reporter->phase('download');
    $this->travel(2)->seconds();
    $reporter->phase('verify');
    $reporter->phase('verify');
    $this->travel(3)->seconds();
    $reporter->failStep();
    Cache::flush();
    $payload = $progress->payload($operation->id);
    $this->assertSame(['download', 'verify'], array_column($payload['steps'], 'phase'));
    $this->assertSame([2000, 3000], array_column($payload['steps'], 'elapsed_ms'));
    $this->assertSame(['complete', 'failed'], array_column($payload['steps'], 'state'));
    $reporter->endRun('success');
    $this->assertFalse($progress->payload($operation->id)['terminal']);
    $progress->completed('failed', 'Synthetic failure.');
    $this->assertSame(__('publisher-client::updates.progress.failure_message'), $progress->payload($operation->id)['message']);
    foreach (['fresh', 'updates'] as $folder) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$folder.'/2026_10_18_200000_add_state_to_update_run_steps.php';
      $migration->up();
      $migration->up();
      $migration->down();
    }
    $this->assertSame('failed', $progress->payload($operation->id)['steps'][1]['state']);
  }

  public function test_every_update_phase_has_the_same_readable_heading_and_step_label_in_all_panel_locales(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()]);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'running', 'started_at' => now()]);
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $progress->start($run);
    $phases = array_merge(UpdateProgress::PHASES, ['backup', 'catalog', 'assets']);
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      app()->setLocale($locale);
      foreach ($phases as $phase) {
        $progress->stage($phase);
        $payload = $progress->payload($operation->id);
        $this->assertSame($phase, $payload['stage']);
        $this->assertSame(__('webblocks-cms::admin.updates.phases.'.$phase), $payload['stage_label']);
        $this->assertSame($payload['stage_label'], $payload['steps'][array_key_last($payload['steps'])]['label']);
        $this->assertNotSame('', trim($payload['stage_label']));
        $this->assertStringNotContainsString('::', $payload['stage_label']);
      }
    }
  }

  public function test_new_shared_and_unknown_phases_never_leak_a_translation_key_into_the_heading(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()]);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'running', 'started_at' => now()]);
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $progress->start($run);
    __('publisher-client::updates.progress.step_running');
    trans()->addLines(['updates.phases.shared_only' => 'Checking the site'], 'en', 'publisher-client');
    $progress->stage('shared_only');
    $payload = $progress->payload($operation->id);
    $this->assertSame('Checking the site', $payload['stage_label']);
    $this->assertSame('Checking the site', $payload['steps'][0]['label']);
    $progress->stage('unknown_legacy_phase');
    $payload = $progress->payload($operation->id);
    $this->assertSame(__('webblocks-cms::admin.updates.updating_body'), $payload['stage_label']);
    $this->assertSame($payload['stage_label'], $payload['steps'][1]['label']);
    $this->assertStringNotContainsString('unknown_legacy_phase', $payload['stage_label']);
    $this->assertStringNotContainsString('::', $payload['stage_label']);
  }

  public function test_schema_migration_is_repeatable_and_keeps_receipts(): void
  {
    $operation = $this->operation();
    foreach (['fresh', 'updates'] as $directory) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$directory.'/2026_10_13_180000_create_admin_update_operations.php';
      $migration->up();
      $migration->up();
      $migration->down();
    }
    $this->assertNotNull($operation->fresh());
    $this->assertFalse(Schema::hasColumn('wbcms_admin_update_operations', 'result'));
  }

  public function test_a_lost_worker_is_unconfirmed_and_never_a_success(): void
  {
    $operation = $this->operation(['status' => 'running', 'started_at' => now()->subHour(), 'heartbeat_at' => now()->subMinutes(5)]);
    $payload = app(CmsUpdateProgress::class)->payload($operation->id);
    $this->assertSame('interrupted', $payload['state']);
    $this->assertFalse($payload['terminal']);
    $this->assertFalse($payload['running']);
    $this->assertSame('running', $operation->fresh()->status);
    $this->assertSame('unknown', app(CmsUpdateProgress::class)->payload((string) Str::uuid())['state']);
  }

  public function test_long_runs_survive_cache_clear_and_keep_their_exact_result(): void
  {
    $operation = $this->operation(['status' => 'running', 'started_at' => now()->subHour(), 'heartbeat_at' => now()]);
    Cache::flush();
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'running', 'started_at' => now()->subHour()]);
    $progress->start($run);
    $progress->stage('migrations');
    $payload = $progress->payload($operation->id);
    $this->assertTrue($payload['running']);
    $this->assertGreaterThanOrEqual(3600000, $payload['elapsed_ms']);
    $this->assertSame($run->id, $payload['run_id']);
    $this->assertSame('migrations', $payload['stage']);
    $run->update(['status' => 'restored', 'duration_ms' => 3600123, 'finished_at' => now()]);
    $progress->completed('restored', 'Backup restored.');
    $payload = $progress->payload($operation->id);
    $this->assertTrue($payload['terminal']);
    $this->assertSame('restored', $payload['state']);
    $this->assertSame(3600123, $payload['duration_ms']);
  }

  public function test_backup_heartbeat_keeps_tracking_alive_without_sql_phase_changes(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()->subMinutes(5)]);
    app(RunHeartbeat::class)->beat();
    $this->assertTrue(app(CmsUpdateProgress::class)->payload($operation->id)['running']);
  }

  public function test_duplicate_admission_does_not_spawn_another_worker(): void
  {
    $operation = $this->operation();
    $launcher = Mockery::mock(AdminUpdateLauncher::class);
    $launcher->shouldNotReceive('launch');
    $this->app->instance(AdminUpdateLauncher::class, $launcher);
    $this->assertSame($operation->id, app(StartAdminSystemUpdate::class)->execute(1)->id);
    $this->assertSame(1, AdminUpdateOperation::count());
  }

  public function test_worker_records_completion_and_cannot_reexecute_receipt(): void
  {
    $operation = $this->operation();
    $engine = Mockery::mock(SystemUpdater::class);
    $engine->expects('run')->with(1)->once()->andReturn(new UpdateResult(
      '1.0.0', WebBlocks::version(), 'success', 'Done.', '', 0,
      CarbonImmutable::now(), CarbonImmutable::now(), 10, null,
    ));
    $this->app->bind(SystemUpdater::class, fn () => $engine);
    $this->artisan('webblocks:updates:work', ['operation' => $operation->id])->assertSuccessful();
    $this->assertSame('success', $operation->fresh()->status);
    $this->assertNotNull($operation->fresh()->finished_at);
    $this->artisan('webblocks:updates:work', ['operation' => $operation->id])->assertSuccessful();
  }

  public function test_launcher_failure_is_an_explicit_terminal_error(): void
  {
    $engine = Mockery::mock(SystemUpdater::class);
    $engine->expects('isLocked')->andReturn(false);
    $this->app->instance(SystemUpdater::class, $engine);
    $client = Mockery::mock(UpdateServerClient::class);
    $client->expects('check')->andReturn(new UpdateCheckResult('update_available', '', '', '', true, null, '', '', '', '1.0.0', '1.1.0', true,
      ['status' => 'compatible'], ['version' => '1.1.0', 'checksum_sha256' => str_repeat('b', 64)], null, null, CarbonImmutable::now()));
    $this->app->instance(UpdateServerClient::class, $client);
    $launcher = Mockery::mock(AdminUpdateLauncher::class);
    $launcher->expects('launch')->andThrow(new \RuntimeException('Launcher failed.'));
    $this->app->instance(AdminUpdateLauncher::class, $launcher);
    try {
    app(StartAdminSystemUpdate::class)->execute(1);
    $this->fail('Expected launch failure.');
    } catch (UpdateException $exception) {
    $this->assertStringContainsString('could not be started', $exception->userMessage());
    }
    $this->assertSame('failed', AdminUpdateOperation::first()->status);
    $this->assertTrue(app(CmsUpdateProgress::class)->payload()['terminal']);
  }

  public function test_silent_subprocess_keeps_sql_heartbeat_alive_and_timeout_is_enforced(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()->subMinutes(5)]);
    app(CmsUpdateProgress::class)->operation($operation->id);
    config(['webblocks-updates.installer.command_timeout_seconds' => 1]);
    $output = [];
    try {
      app(UpdateCommandRunner::class)->run([PHP_BINARY, '-r', 'usleep(3000000);'], base_path(), $output);
      $this->fail('Expected bounded subprocess timeout.');
    } catch (ProcessTimedOutException) {
      $this->assertTrue($operation->fresh()->heartbeat_at->gt(now()->subSeconds(5)));
    }
  }

  public function test_maintenance_exempts_only_authenticated_update_status(): void
  {
    $this->withoutMiddleware('install.required');
    try {
      $this->artisan('down', ['--render' => 'errors::503'])->assertSuccessful();
      $this->getJson('/arbitrary-public-path')->assertStatus(503);
      $this->getJson(route('admin.system.updates.indicator'))->assertUnauthorized();
      $this->getJson(route('admin.system.updates.index'))->assertStatus(503);
      $down = json_decode(file_get_contents(storage_path('framework/down')), true);
      $this->assertContains(ltrim(route('admin.system.updates.indicator', [], false), '/'), $down['except']);
    } finally {
    $this->artisan('up')->assertSuccessful();
    }
  }

  public function test_worker_rejects_a_changed_release_before_applying(): void
  {
    $operation = $this->operation();
    $client = Mockery::mock(UpdateServerClient::class);
    $client->expects('check')->andReturn(new UpdateCheckResult('update_available', '', '', '', true, null, '', '', '', '1.0.0', WebBlocks::version(), true,
      ['status' => 'compatible'], ['version' => WebBlocks::version(), 'checksum_sha256' => str_repeat('b', 64)], null, null, CarbonImmutable::now()));
    $this->expectException(ApprovedUpdateTargetChanged::class);
    (new AdminApprovedUpdateClient($client, $operation))->check();
  }

  public function test_a_cleared_web_environment_can_run_composer_commands(): void
  {
    $home = getenv('HOME');
    $composerHome = getenv('COMPOSER_HOME');
    putenv('HOME');
    putenv('COMPOSER_HOME');
    try {
      $output = [];
      app(UpdateCommandRunner::class)->run([PHP_BINARY, '-r', 'exit(getenv("COMPOSER_HOME") && is_dir(getenv("COMPOSER_HOME")) ? 0 : 1);'], base_path(), $output);
      $this->assertDirectoryExists(storage_path('app/private/system-updates/composer-home'));
    } finally {
      $home === false ? putenv('HOME') : putenv('HOME='.$home);
      $composerHome === false ? putenv('COMPOSER_HOME') : putenv('COMPOSER_HOME='.$composerHome);
    }
  }

  public function test_automatic_restore_keeps_the_current_receipt_and_phase_durations(): void
  {
    $operation = $this->operation(['status' => 'running', 'heartbeat_at' => now()]);
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'running', 'started_at' => now()]);
    $progress->start($run);
    $progress->stage('backup');
    $this->travel(2)->minutes();
    $progress->stage('migrations');
    $this->travel(2)->minutes();
    $progress->restorePreservingProgress(function () use ($operation, $run): void {
      DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->delete();
      DB::table('wbcms_admin_update_operations')->where('id', $operation->id)->delete();
      DB::table('wbcms_system_update_runs')->where('id', $run->id)->delete();
    });
    $progress->finish();
    $this->assertNotNull($operation->fresh());
    $steps = DB::table('wbcms_system_update_run_steps')->where('system_update_run_id', $run->id)->orderBy('id')->get();
    $this->assertSame(['backup', 'migrations', 'rollback'], $steps->pluck('stage')->all());
    $this->assertSame(120000, $steps[1]->duration_ms);
    $this->assertSame($run->id, $operation->fresh()->system_update_run_id);
  }

  public function test_failed_post_apply_restores_the_original_package_tree(): void
  {
    $root = sys_get_temp_dir().'/cms-package-rollback-'.Str::uuid();
    File::ensureDirectoryExists($root.'/package/src');
    File::put($root.'/package/src/version.php', 'old');
    try {
      $rollback = new CmsPackageRollback;
      $rollback->capture($root.'/package');
      File::put($root.'/package/src/version.php', 'new');
      File::put($root.'/package/src/new-file.php', 'new');
      $this->assertTrue($rollback->restore());
      $this->assertSame('old', file_get_contents($root.'/package/src/version.php'));
      $this->assertFileDoesNotExist($root.'/package/src/new-file.php');
      $this->assertFalse($rollback->restore());
      $this->assertSame([], glob($root.'/package.cms-*'));
    } finally {
      File::deleteDirectory($root);
    }
  }

  public function test_first_future_update_does_not_depend_on_a_retired_license_helper(): void
  {
    $root = dirname(__DIR__, 2);
    $before = hash_file('sha256', $root.'/LICENSE');
    $migration = require $root.'/database/migrations/updates/2026_09_17_090000_promote_package_license.php';
    $migration->up();
    $migration->up();
    $this->assertSame($before, hash_file('sha256', $root.'/LICENSE'));
  }

  public function test_sqlite_backup_round_trips_multiline_update_logs_with_sql_and_quotes(): void
  {
    $text = "Migration SQL ends here;\nNext line contains 'quotes' and\r\nmore output;\nComplete.";
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => WebBlocks::version(), 'status' => 'failed', 'started_at' => now(), 'output' => $text]);
    $path = sys_get_temp_dir().'/cms-update-log-dump-'.Str::uuid().'.sql';
    try {
      $output = [];
      app(DatabaseDumpWriter::class)->dumpTo($path, $output);
      app(SqlDumpContentValidator::class)->assertValidFile($path);
      $run->update(['output' => 'changed']);
      app(DatabaseRestoreRunner::class)->restoreFrom($path, $output);
      $this->assertSame($text, $run->fresh()->output);
    } finally {
    @unlink($path);
    }
  }

  public function test_a_fresh_install_can_run_pending_historical_update_migrations(): void
  {
    $code = Artisan::call('migrate', ['--path' => dirname(__DIR__, 2).'/database/migrations/updates', '--realpath' => true, '--force' => true]);
    $this->assertSame(0, $code, Artisan::output());
    $this->assertFalse(Schema::hasTable('comment_entries'));
    $this->assertFalse(Schema::hasTable('content_ratings'));
    $this->assertTrue(Schema::hasTable('wbcms_admin_update_operations'));
  }
}
