<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use WebBlocks\Cms\Actions\System\StartCmsUpdateChain;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\CmsChainAdmission;
use WebBlocks\Cms\Support\System\Updates\CmsChainStepExecutor;
use WebBlocks\Cms\Support\System\Updates\CmsUpdateProgress;
use WebBlocks\Cms\Support\System\Updates\RunCmsUpdateChain;
use WebBlocks\Cms\Support\System\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class AutomaticUpdateChainTest extends TestCase
{
  private string $secret;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $pair = sodium_crypto_sign_keypair();
    $this->secret = sodium_crypto_sign_secretkey($pair);
    config(['webblocks-updates.chains.enabled' => true, 'webblocks-updates.enabled' => true,
      'publisher-client.signature.public_key' => base64_encode(sodium_crypto_sign_publickey($pair))]);
  }

  private function step(string $from, string $to): array
  {
    $hash = hash('sha256', $to);

    return ['product' => 'webblocks-cms', 'channel' => 'stable', 'from_version' => $from, 'version' => $to,
      'checksum_sha256' => $hash, 'signature' => base64_encode(sodium_crypto_sign_detached($hash, $this->secret)),
      'artifact_url' => 'https://fixture.example.test/'.$to.'.zip'];
  }

  private function bridgeVersion(): string
  {
    [$major, $minor] = array_map('intval', explode('.', WebBlocks::version()));

    return $major.'.'.($minor + 1).'.0';
  }

  private function targetVersion(): string
  {
    $major = (int) explode('.', WebBlocks::version())[0];

    return ($major + 1).'.0.0';
  }

  private function followingVersion(): string
  {
    $major = (int) explode('.', WebBlocks::version())[0];

    return ($major + 1).'.1.0';
  }

  private function admit(): AdminUpdateOperation
  {
    $from = WebBlocks::version();
    Http::fake(['*/api/updates/plan*' => Http::response(['data' => ['protocol_version' => 1,
      'product' => 'webblocks-cms', 'channel' => 'stable', 'installed_version' => $from, 'target_version' => $this->targetVersion(),
      'steps' => [$this->step($from, $this->bridgeVersion()), $this->step($this->bridgeVersion(), $this->targetVersion())]]])]);

    return app(StartCmsUpdateChain::class)->execute(null, false);
  }

  public function test_admission_pins_final_target_and_all_steps_in_relational_records(): void
  {
    $operation = $this->admit();
    $this->assertSame($this->targetVersion(), $operation->target_version);
    $this->assertSame(WebBlocks::version(), $operation->last_verified_version);
    $this->assertSame([$this->bridgeVersion(), $this->targetVersion()], UpdateChainStep::where('operation_id', $operation->id)->orderBy('position')->pluck('version')->all());
    $this->expectException(UpdateException::class);
    $this->admit();
  }

  public function test_active_or_unresolved_chain_blocks_engine_and_manual_finalizer(): void
  {
    $operation = $this->admit();
    $this->assertTrue(app(SystemUpdater::class)->isLocked());
    $this->artisan('webblocks:updates:finalize', ['--force' => true])->assertFailed();
    $operation->update(['status' => 'interrupted', 'recovery_required' => true]);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $this->expectException(UpdateException::class);
    app(SystemUpdater::class)->run();
  }

  public function test_step_completion_does_not_finish_the_parent_or_reset_total_elapsed_time(): void
  {
    $operation = $this->admit();
    $operation->update(['status' => 'running', 'started_at' => now()->subSeconds(90), 'heartbeat_at' => now()]);
    $run = SystemUpdateRun::create(['from_version' => WebBlocks::version(), 'to_version' => $this->bridgeVersion(),
      'status' => 'success', 'duration_ms' => 1000, 'started_at' => now()->subSeconds(5)]);
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $progress->start($run);
    $progress->completed('success', 'Step complete');
    $payload = $progress->payload($operation->id);
    $this->assertFalse($payload['terminal']);
    $this->assertSame('running', $operation->fresh()->status);
    $this->assertGreaterThanOrEqual(90000, $payload['elapsed_ms']);
    $this->assertCount(2, $payload['chain_steps']);
    $this->assertStringContainsString($this->targetVersion(), $payload['chain_label']);
  }

  public function test_database_rollback_preserves_the_parent_and_every_chain_receipt(): void
  {
    $operation = $this->admit();
    $operation->update(['status' => 'running', 'last_verified_version' => $this->bridgeVersion()]);
    $runs = [];
    foreach (UpdateChainStep::where('operation_id', $operation->id)->orderBy('position')->get() as $step) {
      $run = SystemUpdateRun::create(['from_version' => $step->from_version, 'to_version' => $step->version,
        'status' => $step->position === 1 ? 'success' : 'running', 'started_at' => now()]);
      $step->update(['system_update_run_id' => $run->id, 'status' => $run->status]);
      $runs[] = $run;
    }
    $progress = app(CmsUpdateProgress::class);
    $progress->operation($operation->id);
    $progress->start($runs[1]);
    $progress->restorePreservingProgress(function (): void {
      DB::table('wbcms_update_chain_steps')->delete();
      DB::table('wbcms_admin_update_operations')->delete();
      DB::table('wbcms_system_update_run_steps')->delete();
      DB::table('wbcms_system_update_runs')->delete();
    });
    $this->assertSame(2, SystemUpdateRun::count());
    $this->assertSame(2, UpdateChainStep::count());
    $this->assertSame($this->bridgeVersion(), $operation->fresh()->last_verified_version);
    $this->assertSame('running', $operation->fresh()->status);
  }

  public function test_failed_second_step_restores_its_checkpoint_and_never_replays_the_first(): void
  {
    $operation = $this->admit();
    $runner = $this->mock(UpdateCommandRunner::class)->makePartial();
    $runner->shouldReceive('run')->with(Mockery::type('array'), base_path(), Mockery::type('array'))->andReturnNull();
    $executor = Mockery::mock(CmsChainStepExecutor::class);
    $executor->expects('execute')->with(1, Mockery::type('array'))->andReturnUsing(function () use ($operation): string {
      $probe = Cache::lock('system-updates:run', 1);
      $this->assertFalse($probe->get());
      UpdateChainStep::where('operation_id', $operation->id)->where('position', 1)->update(['status' => 'success']);
      $operation->update(['last_verified_version' => $this->bridgeVersion()]);

      return $this->bridgeVersion();
    });
    $executor->expects('execute')->with(2, Mockery::type('array'))->andReturnUsing(function () use ($operation): string {
      UpdateChainStep::where('operation_id', $operation->id)->where('position', 2)->update(['status' => 'restored']);
      throw new UpdateException('Synthetic second-step failure.');
    });
    $this->app->bind(CmsChainStepExecutor::class, fn () => $executor);
    $this->assertFalse(app(RunCmsUpdateChain::class)->run($operation));
    $this->assertSame('restored', $operation->fresh()->status);
    $this->assertSame($this->bridgeVersion(), $operation->fresh()->last_verified_version);
    $this->assertFalse($operation->fresh()->recovery_required);
    $this->assertFalse(app(CmsChainAdmission::class)->blocked());
  }

  public function test_unknown_child_exit_keeps_maintenance_recovery_required_and_blocks_replay(): void
  {
    $operation = $this->admit();
    $runner = $this->mock(UpdateCommandRunner::class)->makePartial();
    $runner->shouldReceive('run')->andReturnNull();
    $executor = Mockery::mock(CmsChainStepExecutor::class);
    $executor->expects('execute')->once()->andReturnUsing(function () use ($operation): string {
      UpdateChainStep::where('operation_id', $operation->id)->where('position', 1)->update(['status' => 'running']);
      throw new UpdateException('Synthetic lost process.');
    });
    $this->app->bind(CmsChainStepExecutor::class, fn () => $executor);
    $this->assertFalse(app(RunCmsUpdateChain::class)->run($operation));
    $this->assertSame('interrupted', $operation->fresh()->status);
    $this->assertTrue($operation->fresh()->recovery_required);
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $this->assertFalse(app(RunCmsUpdateChain::class)->run($operation->fresh()));
  }

  public function test_migration_profiles_are_idempotent_and_keep_recovery_receipts(): void
  {
    $operation = $this->admit();
    foreach (['fresh', 'updates'] as $profile) {
      $migration = require dirname(__DIR__, 2).'/database/migrations/'.$profile.'/2026_11_02_200000_add_update_chains.php';
      $migration->up();
      $migration->up();
      $migration->down();
    }
    $this->assertSame(2, UpdateChainStep::where('operation_id', $operation->id)->count());
    $this->assertNull($operation->fresh()->user_id);
  }

  public function test_disabled_updates_do_not_fetch_or_admit_a_chain(): void
  {
    config(['webblocks-updates.enabled' => false]);
    Http::preventStrayRequests();
    try {
    app(StartCmsUpdateChain::class)->execute(null, false);
    $this->fail('Expected disabled update');
    } catch (UpdateException) {
    $this->assertSame(0, AdminUpdateOperation::count());
    }
  }

  public function test_private_step_cannot_claim_an_unstarted_operation(): void
  {
    $operation = $this->admit();
    Http::preventStrayRequests();
    $this->artisan('webblocks:updates:chain-step', ['operation' => $operation->id, 'position' => 1])->assertFailed();
    $this->assertSame('queued', UpdateChainStep::first()->status);
  }

  public function test_clearing_cache_does_not_release_the_chain_or_interrupt_its_sql_progress(): void
  {
    $operation = $this->admit();
    $operation->update(['status' => 'running', 'heartbeat_at' => now(), 'worker_pid' => getmypid()]);
    Cache::flush();
    $this->assertTrue(app(CmsChainAdmission::class)->blocked());
    $this->assertSame('running', app(CmsUpdateProgress::class)->payload($operation->id)['state']);
    $this->expectException(UpdateException::class);
    $this->admit();
  }

  public function test_terminal_chain_does_not_hide_a_later_independent_api_run(): void
  {
    $operation = $this->admit();
    $earlier = SystemUpdateRun::create(['from_version' => WebBlocks::version(), 'to_version' => $this->targetVersion(),
      'status' => 'success', 'started_at' => now()]);
    $operation->update(['status' => 'success', 'system_update_run_id' => $earlier->id, 'finished_at' => now()]);
    $later = SystemUpdateRun::create(['from_version' => $this->targetVersion(), 'to_version' => $this->followingVersion(),
      'status' => 'success', 'started_at' => now()]);
    $this->assertSame($later->id, app(CmsUpdateProgress::class)->payload()['run_id']);
    $this->assertSame($operation->id, app(CmsUpdateProgress::class)->payload($operation->id)['operation_id']);
  }

  public function test_busy_execution_lease_does_not_leave_an_unstarted_chain_queued(): void
  {
    $operation = $this->admit();
    $lock = Cache::lock((string) config('publisher-client.lock.name'), 60);
    $this->assertTrue($lock->get());
    try {
      $this->assertFalse(app(RunCmsUpdateChain::class)->run($operation));
      $this->assertSame('failed', $operation->fresh()->status);
      $this->assertFalse($operation->fresh()->maintenance_owned);
      $this->assertFalse(app(CmsChainAdmission::class)->blocked());
      $this->assertSame(['queued', 'queued'], UpdateChainStep::orderBy('position')->pluck('status')->all());
    } finally {
      $lock->release();
    }
  }
}
