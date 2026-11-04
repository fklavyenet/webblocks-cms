<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Actions\System\ReconcileStoppedSystemUpdate;
use WebBlocks\Cms\Actions\System\RunApiSystemUpdate;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\SystemUpdateInspector;
use WebBlocks\Cms\Support\System\Updates\CmsChainAdmission;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateAdmissionContext;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateApiPresenter;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateAttention;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateLiveness;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunOutputSanitizer;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateRunRetention;
use WebBlocks\Cms\Support\System\Updates\UpdateWorkerProcess;
use WebBlocks\Cms\Support\Updates\Client\Contracts\RunRecorder;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class UnfinishedSystemUpdateTest extends TestCase
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

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $actor = User::forceCreate(['name' => 'Synthetic administrator', 'email' => 'admin@example.test',
      'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($this->cmsActor($actor));
    app(CmsPublisherClientConfigurator::class)->configure();
    Http::preventStrayRequests();
    $this->mock(SystemUpdateInspector::class)->shouldReceive('report')->andReturn(['can_update' => false, 'version' => ['state' => 'unknown']]);
  }

  private function makeRun(array $attributes = []): SystemUpdateRun
  {
    return SystemUpdateRun::create(array_merge(['from_version' => '1.0.0', 'to_version' => '1.0.1', 'status' => 'running',
      'summary' => 'Original summary', 'output' => 'Original log', 'started_at' => now()->subMonth(),
      'worker_pid' => 12345, 'worker_host' => str_repeat('a', 64), 'heartbeat_at' => now()->subHour()], $attributes));
  }

  private function operation(array $attributes = []): AdminUpdateOperation
  {
    return AdminUpdateOperation::create(array_merge(['id' => (string) Str::uuid(), 'from_version' => '1.0.0',
      'target_version' => '1.0.1', 'checksum_sha256' => str_repeat('a', 64), 'status' => 'running',
      'started_at' => now()->subMonth(), 'heartbeat_at' => now()->subHour(),
      'worker_pid' => 12346, 'worker_host' => str_repeat('a', 64)], $attributes));
  }

  private function receipt(array $attributes = []): SystemUpdateRequest
  {
    return SystemUpdateRequest::create(array_merge(['id' => (string) Str::uuid(), 'cms_api_token_id' => 1,
      'idempotency_key' => (string) Str::uuid(), 'expected_current_version' => '1.0.0', 'target_version' => '1.0.1',
      'checksum_sha256' => str_repeat('a', 64), 'status' => 'running',
      'worker_pid' => 12347, 'worker_host' => str_repeat('a', 64), 'heartbeat_at' => now()->subHour()], $attributes));
  }

  private function deadWorkers(): void
  {
    $this->mock(UpdateWorkerProcess::class)->shouldReceive('state')->andReturn('dead');
  }

  private function input(string $kind, $record): array
  {
    return ['record_type' => $kind, 'record_id' => (string) $record->getKey(), 'reason' => 'Synthetic verified stopped worker review.'];
  }

  public function test_history_and_pruning_keep_unfinished_records_outside_the_last_five(): void
  {
    $protected = collect(['pending', 'running', 'interrupted'])->map(fn ($status) => $this->makeRun(['status' => $status]));
    $recovering = $this->makeRun(['status' => 'failed', 'recovery_required' => true]);
    $discarded = $this->makeRun(['status' => 'success']);
    for ($i = 0; $i < 6; $i++) {
      $this->makeRun(['status' => 'success', 'started_at' => now()->subMinutes($i)]);
    }
    $retention = app(SystemUpdateRunRetention::class);
    $ids = $retention->retainedRuns()->pluck('id');
    foreach ($protected->push($recovering) as $run) {
      $this->assertContains($run->id, $ids);
    }
    $this->assertCount(9, $ids);
    $this->assertSame(2, $retention->prune());
    $this->assertNull($discarded->fresh());
    foreach ($protected as $run) {
      $this->assertNotNull($run->fresh());
    }
  }

  public function test_unresolved_chain_keeps_its_finished_run_outside_the_history_limit(): void
  {
    $run = $this->makeRun(['status' => 'success']);
    $operation = $this->operation(['chain_protocol' => 1, 'status' => 'interrupted', 'recovery_required' => true]);
    UpdateChainStep::create(['operation_id' => $operation->id, 'position' => 1, 'from_version' => '1.0.0',
      'version' => '1.0.1', 'checksum_sha256' => str_repeat('a', 64), 'signature' => 'fixture',
      'artifact_url' => 'https://fixture.example.test/update.zip', 'status' => 'success', 'system_update_run_id' => $run->id]);
    $this->makeRun(['status' => 'success', 'started_at' => now()]);
    $retention = app(SystemUpdateRunRetention::class);
    $this->assertContains($run->id, $retention->retainedRuns(1)->pluck('id'));
    $retention->prune(1);
    $this->assertNotNull($run->fresh());
  }

  public function test_passive_screen_shows_legacy_and_orphan_records_without_reconciliation(): void
  {
    $run = $this->makeRun(['worker_pid' => null, 'worker_host' => null]);
    $operation = $this->operation(['worker_host' => null]);
    $receipt = $this->receipt(['worker_host' => null]);
    $before = $run->fresh()->getAttributes();
    $this->get(route('admin.system.updates.index'))->assertOk()
      ->assertSee(__('webblocks-cms::update_recovery.title'))
      ->assertSee(__('webblocks-cms::update_recovery.states.unknown'))
      ->assertSee($operation->id)->assertSee($receipt->id)
      ->assertDontSee('data-wb-target="#reconcile-', false);
    $this->getJson(route('admin.system.updates.indicator'))->assertOk();
    $this->assertSame($before, $run->fresh()->getAttributes());
    $this->assertSame('running', $operation->fresh()->status);
    $this->assertSame('running', $receipt->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_confirmed_stopped_group_is_audited_atomically_and_remains_recovery_blocked(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    $original = $run->fresh()->getAttributes();
    $operation = $this->operation(['system_update_run_id' => $run->id]);
    $receipt = $this->receipt(['system_update_run_id' => $run->id]);
    $this->assertTrue(app(SystemUpdateLiveness::class)->inspect($run)['can_reconcile']);

    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertOk()->assertJsonPath('count', 3);
    foreach ([$run, $operation, $receipt] as $record) {
      $this->assertSame('interrupted', $record->fresh()->status);
      $this->assertTrue($record->fresh()->recovery_required);
    }
    foreach (['summary', 'output', 'from_version', 'to_version', 'started_at', 'finished_at', 'duration_ms'] as $field) {
      $this->assertSame($original[$field], $run->fresh()->getAttributes()[$field]);
    }
    $this->assertDatabaseCount('wbcms_update_reconciliations', 3);
    $this->assertDatabaseHas('wbcms_update_reconciliations', ['record_type' => 'run', 'record_id' => (string) $run->id,
      'previous_status' => 'running', 'status' => 'interrupted', 'actor_id' => auth()->id(), 'recovery_required' => true]);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $this->assertTrue(app(SystemUpdater::class)->isLocked());
    config(['webblocks-updates.enabled' => true, 'webblocks-updates.chains.enabled' => true]);
    $this->postJson(route('admin.system.updates.store'))->assertConflict();
    config(['webblocks-updates.chains.enabled' => false]);
    $this->postJson(route('admin.system.updates.store'))->assertConflict();
    $token = CmsApiToken::create(['name' => 'Synthetic token', 'token_hash' => hash('sha256', 'synthetic'),
      'token_preview' => 'synthetic', 'token_type' => 'system', 'created_by_user_id' => auth()->id()]);
    $result = app(RunApiSystemUpdate::class)->execute($token, ['idempotency_key' => 'new-update', 'expected_current_version' => '1.0.0',
      'target_version' => '1.0.1', 'checksum_sha256' => str_repeat('a', 64)]);
    $this->assertSame(409, $result['http_status']);
    $this->assertSame(1, AdminUpdateOperation::count());
    $this->assertSame(1, SystemUpdateRequest::count());
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertDatabaseCount('wbcms_update_reconciliations', 3);
    Http::assertNothingSent();
  }

  public static function heldLocks(): array
  {
    return [['system-updates:admission'], ['system-updates:run'], ['worker']];
  }

  #[DataProvider('heldLocks')]
  public function test_held_locks_block_reconciliation_and_are_never_force_released(string $name): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    $operation = $this->operation(['system_update_run_id' => $run->id]);
    $name = $name === 'worker' ? 'system-updates:worker:'.$operation->id : $name;
    $lock = Cache::lock($name, 900);
    $this->assertTrue($lock->get());
    try {
      $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
      $this->assertFalse(Cache::lock($name, 1)->get());
      $this->assertSame('running', $run->fresh()->status);
      $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
    } finally {
      $lock->release();
    }
  }

  public static function uncertainEvidence(): array
  {
    return [['alive', false], ['unknown', false], ['dead', true]];
  }

  #[DataProvider('uncertainEvidence')]
  public function test_live_unknown_and_fresh_heartbeat_evidence_preserves_the_saved_state(string $process, bool $fresh): void
  {
    $this->mock(UpdateWorkerProcess::class)->shouldReceive('state')->andReturn($process);
    $run = $this->makeRun(['heartbeat_at' => $fresh ? now() : now()->subHour()]);
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_cache_clear_cannot_override_a_live_sql_heartbeat_or_a_recovery_requirement(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun(['heartbeat_at' => now()]);
    Cache::flush();
    $this->assertFalse(app(SystemUpdateLiveness::class)->inspect($run)['can_reconcile']);
    $run->update(['heartbeat_at' => now()->subHour(), 'recovery_required' => true]);
    Cache::flush();
    $this->assertSame('recovery', app(SystemUpdateLiveness::class)->inspect($run)['state']);
    $this->assertTrue(app(SystemUpdater::class)->isLocked());
  }

  public function test_a_live_related_worker_and_an_unresolved_chain_cannot_be_reconciled(): void
  {
    $run = $this->makeRun();
    $operation = $this->operation(['system_update_run_id' => $run->id]);
    $probe = $this->mock(UpdateWorkerProcess::class);
    $probe->shouldReceive('state')->with(12345, str_repeat('a', 64))->andReturn('dead');
    $probe->shouldReceive('state')->with(12346, str_repeat('a', 64))->andReturn('alive');
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->deadWorkers();
    $operation->update(['chain_protocol' => 1, 'status' => 'interrupted', 'recovery_required' => true]);
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertTrue($operation->fresh()->recovery_required);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_preflight_evidence_is_rechecked_inside_the_mutation(): void
  {
    $run = $this->makeRun();
    $this->deadWorkers();
    $this->assertTrue(app(SystemUpdateLiveness::class)->inspect($run)['can_reconcile']);
    $this->mock(UpdateWorkerProcess::class)->shouldReceive('state')->andReturn('alive');
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_an_unrelated_live_worker_prevents_reconciliation(): void
  {
    $run = $this->makeRun();
    $other = $this->operation();
    $probe = $this->mock(UpdateWorkerProcess::class);
    $probe->shouldReceive('state')->with(12345, str_repeat('a', 64))->andReturn('dead');
    $probe->shouldReceive('state')->with(12346, str_repeat('a', 64))->andReturn('alive');
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertSame('running', $other->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_terminal_parent_with_an_unfinished_step_stays_visible_and_blocks_admission(): void
  {
    $operation = $this->operation(['chain_protocol' => 1, 'status' => 'success']);
    UpdateChainStep::create(['operation_id' => $operation->id, 'position' => 1, 'from_version' => '1.0.0',
      'version' => '1.0.1', 'checksum_sha256' => str_repeat('a', 64), 'signature' => 'fixture',
      'artifact_url' => 'https://fixture.example.test/update.zip', 'status' => 'running']);
    $rows = app(SystemUpdateAttention::class)->rows();
    $this->assertSame($operation->id, $rows->first()['record']->id);
    $this->assertCount(1, $rows->first()['chain_steps']);
    $this->assertFalse($rows->first()['liveness']['can_reconcile']);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
  }

  public function test_expired_lock_ownership_refuses_the_write_and_preserves_a_new_owner(): void
  {
    $run = $this->makeRun();
    $replacement = null;
    $this->mock(SystemUpdateLiveness::class)->shouldReceive('inspect')->once()->andReturnUsing(function () use (&$replacement): array {
      $this->travel(31)->seconds();
      $replacement = Cache::lock('system-updates:admission', 60);
      $this->assertTrue($replacement->get());

      return ['can_reconcile' => true];
    });
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertConflict();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
    $this->assertTrue($replacement->isOwnedByCurrentProcess());
    $replacement->release();
  }

  public function test_only_the_exact_admitted_context_is_exempt_from_its_own_receipt(): void
  {
    $operation = $this->operation();
    $context = app(CmsUpdateAdmissionContext::class);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $context->operationId = $operation->id;
    $this->assertFalse(app(CmsChainAdmission::class)->blocked());
    $receipt = $this->receipt();
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $context->requestId = $receipt->id;
    $this->assertFalse(app(CmsChainAdmission::class)->blocked());
    $this->makeRun(['worker_pid' => null, 'worker_host' => null]);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $context->operationId = null;
    $context->requestId = null;
  }

  public static function orphanKinds(): array
  {
    return [['operation'], ['request']];
  }

  #[DataProvider('orphanKinds')]
  public function test_confirmed_stopped_orphans_can_be_audited_without_a_run(string $kind): void
  {
    $this->deadWorkers();
    $record = $kind === 'operation' ? $this->operation() : $this->receipt();
    $this->postJson(route('admin.system.updates.reconcile'), $this->input($kind, $record))->assertOk()->assertJsonPath('count', 1);
    $this->assertSame('interrupted', $record->fresh()->status);
    $this->assertTrue($record->fresh()->recovery_required);
    $this->assertDatabaseHas('wbcms_update_reconciliations', ['record_type' => $kind, 'record_id' => $record->id,
      'worker_state' => 'dead', 'run_lock_verified' => true, 'admission_lock_verified' => true]);
    $this->assertSame(0, SystemUpdateRun::count());
    if ($kind === 'request') {
      $payload = app(SystemUpdateApiPresenter::class)->operation($record->fresh());
      $this->assertTrue($payload['requires_attention']);
      $this->assertTrue($payload['recovery_required']);
    }
  }

  public function test_a_failed_status_write_rolls_back_its_audit_and_releases_locks(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    DB::unprepared("CREATE TRIGGER reject_reconciliation BEFORE UPDATE OF status ON wbcms_system_update_runs BEGIN SELECT RAISE(ABORT, 'Synthetic write failure'); END");
    try {
      app(ReconcileStoppedSystemUpdate::class)->execute('run', (string) $run->id, (int) auth()->id(), 'Synthetic worker review reason.');
      $this->fail('Expected the transactional status write to fail.');
    } catch (QueryException) {
      $this->assertSame('running', $run->fresh()->status);
      $this->assertFalse($run->fresh()->recovery_required);
      $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
      $lock = Cache::lock('system-updates:admission', 1);
      $this->assertTrue($lock->get());
      $lock->release();
    }
  }

  public function test_authorization_and_reason_validation_precede_reconciliation(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    $this->postJson(route('admin.system.updates.reconcile'), ['record_type' => 'run', 'record_id' => (string) $run->id])->assertUnprocessable();
    $actor = User::forceCreate(['name' => 'Synthetic editor', 'email' => 'editor@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $this->actingAs($this->cmsActor($actor));
    $this->postJson(route('admin.system.updates.reconcile'), $this->input('run', $run))->assertForbidden();
    $this->assertSame('running', $run->fresh()->status);
    $this->assertDatabaseCount('wbcms_update_reconciliations', 0);
  }

  public function test_new_runs_capture_worker_evidence_and_durable_heartbeats(): void
  {
    $this->mock(UpdateWorkerProcess::class)->shouldReceive('host')->andReturn(str_repeat('a', 64));
    $run = app(RunRecorder::class)->start('1.0.0', '1.0.1', null);
    $this->assertSame(getmypid(), $run->worker_pid);
    $this->assertSame(str_repeat('a', 64), $run->worker_host);
    $this->assertNotNull($run->fresh()->heartbeat_at);
    $previous = $run->fresh()->heartbeat_at;
    $this->travel(30)->seconds();
    // A new recorder start resets throttling without requiring an operation record.
    app(CmsUpdateProgress::class)->start($run);
    $this->assertTrue($run->fresh()->heartbeat_at->gt($previous));
  }

  public function test_both_migration_profiles_are_idempotent_and_keep_audit_rows_on_rollback(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    app(ReconcileStoppedSystemUpdate::class)->execute('run', (string) $run->id, (int) auth()->id(), 'Synthetic stopped worker review.');
    foreach (['fresh', 'updates'] as $profile) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$profile.'/2026_11_04_230000_add_update_reconciliation_evidence.php';
      $migration->up();
      $migration->up();
      $migration->down();
    }
    $this->assertDatabaseCount('wbcms_update_reconciliations', 1);
    $this->assertTrue($run->fresh()->recovery_required);
  }

  public function test_attention_browser_fixture_uses_native_tables_and_review_modals_in_all_panel_locales(): void
  {
    $this->deadWorkers();
    $run = $this->makeRun();
    $rows = app(SystemUpdateAttention::class)->rows();
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      app()->setLocale($locale);
      $html = view('webblocks-cms::admin.system.partials.update-attention', [
        'unfinishedUpdates' => $rows, 'canReconcileUpdates' => true,
        'adminText' => fn ($key) => __('webblocks-cms::admin.'.$key),
        'sanitizer' => app(SystemUpdateRunOutputSanitizer::class),
      ])->render();
      $this->assertStringContainsString('<table class="wb-table">', $html);
      $this->assertStringContainsString('data-wb-target="#reconcile-run-'.$run->id.'"', $html);
      $this->assertStringContainsString(e(__('webblocks-cms::update_recovery.confirm_help')), $html);
      $this->assertStringNotContainsString('webblocks-cms::', $html);
      $this->assertStringNotContainsString('<fieldset', $html);
      if ($locale === 'en' && ($fixture = getenv('WEBBLOCKS_UPDATE_ATTENTION_FIXTURE'))) {
        file_put_contents($fixture, '<!doctype html><html lang="en" data-wb-theme="light"><body>'.$html.'</body></html>');
      }
    }
  }
}
