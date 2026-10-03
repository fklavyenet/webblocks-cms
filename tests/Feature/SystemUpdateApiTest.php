<?php

namespace WebBlocks\Cms\Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\HttpException;
use WebBlocks\Cms\Actions\System\RunApiSystemUpdate;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Requests\Admin\CmsApiTokenRequest;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenAuthenticator;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateServerClient;
use WebBlocks\Cms\Support\System\Updates\ApprovedUpdateTargetChanged;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherClientConfigurator;
use WebBlocks\Cms\Support\System\Updates\SystemUpdateApiPresenter;
use WebBlocks\Cms\Support\Updates\Client\Contracts\BackupManager;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCheckResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateServerClient;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateApiTest extends TestCase
{
  private int $executions = 0;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function token(array $overrides = [], bool $systemAccess = true): CmsApiToken
  {
    DB::table('users')->insertOrIgnore([
      'id' => 1, 'name' => 'Synthetic operator', 'email' => 'operator@example.test', 'password' => 'unused',
    ]);
    $token = CmsApiToken::query()->create(array_merge([
      'name' => 'Synthetic token',
      'token_hash' => hash('sha256', (string) Str::uuid()),
      'token_preview' => 'synthetic',
      'token_type' => 'system',
      'allowed_site_ids' => null,
      'created_by_user_id' => 1,
      'capabilities' => [CmsApiTokenCapabilities::SYSTEM_UPDATES_READ, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN],
    ], $overrides));
    $creator = new UpdateApiTestCreator(['is_active' => true]);
    $creator->systemAccess = $systemAccess;
    $token->setRelation('creator', $creator);

    return $token;
  }

  private function authenticate(CmsApiToken $token): void
  {
    $this->withoutMiddleware('install.required');
    $authenticator = Mockery::mock(CmsApiTokenAuthenticator::class);
    $authenticator->shouldReceive('authenticate')->andReturn($token);
    $this->app->instance(CmsApiTokenAuthenticator::class, $authenticator);
  }

  private function input(): array
  {
    return [
      'idempotency_key' => 'operator-approval-1',
      'expected_current_version' => '1.90.0',
      'target_version' => '1.91.0',
      'checksum_sha256' => str_repeat('a', 64),
      'confirmed' => true,
    ];
  }

  private function checkResult(string $version = '1.91.0', string $checksum = ''): UpdateCheckResult
  {
    return new UpdateCheckResult(
      'update_available', 'Update available', 'Update available', 'info', true, '1',
      'https://updates.example.test', 'webblocks-cms', 'stable', '1.90.0', $version, true,
      ['status' => 'compatible'], ['version' => $version, 'checksum_sha256' => $checksum ?: str_repeat('a', 64)],
      null, null, CarbonImmutable::now(),
    );
  }

  private function fakeEngine(string $outcome = 'success'): void
  {
    $client = Mockery::mock(UpdateServerClient::class);
    $client->shouldReceive('check')->andReturn($this->checkResult());
    $this->app->instance(UpdateServerClient::class, $client);
    $this->app->bind(SystemUpdater::class, function ($app, array $parameters) use ($outcome) {
      $engine = Mockery::mock(SystemUpdater::class);
      $engine->shouldReceive('isLocked')->andReturn(false);
      if ($parameters !== []) {
        $engine->shouldReceive('run')->once()->andReturnUsing(function ($userId) use ($parameters, $outcome): UpdateResult {
          $this->executions++;
          $parameters['client']->check();
          // Null actor keeps this synthetic test independent of host User schema.
          $run = $parameters['runRecorder']->start('1.90.0', '1.91.0', null);
          $parameters['runRecorder']->finish($run, $outcome, 'Update result', 'token=private-value', 0, 100);
          if ($outcome === 'restored') {
            throw new UpdateException('Failed, backup restored');
          }

          return new UpdateResult('1.90.0', '1.91.0', $outcome, 'Update result', '', 0, CarbonImmutable::now(), CarbonImmutable::now(), 100, null);
        });
      }

      return $engine;
    });
  }

  #[Test]
  public function capabilities_are_opt_in_and_never_granted_by_legacy_defaults(): void
  {
    $capabilities = app(CmsApiTokenCapabilities::class);
    foreach ([CmsApiTokenCapabilities::SYSTEM_UPDATES_READ, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN] as $capability) {
      $this->assertContains($capability, $capabilities->grantable());
      $this->assertContains($capability, CmsApiTokenCapabilities::ADVANCED);
      $this->assertNotContains($capability, CmsApiTokenCapabilities::DEFAULT);
      $this->assertFalse($capabilities->has($this->token(['capabilities' => []]), $capability));
    }
    $this->assertContains(CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN, CmsApiTokenCapabilities::DESTRUCTIVE);
  }

  #[Test]
  public function site_personal_demo_revoked_expired_and_demoted_credentials_are_denied(): void
  {
    $capabilities = app(CmsApiTokenCapabilities::class);
    foreach ([
      $this->token(['allowed_site_ids' => [1]]),
      $this->token(['allowed_site_ids' => []]),
      $this->token(['token_type' => 'personal']),
      $this->token([], false),
      $this->token(['revoked_at' => now()]),
      $this->token(['expires_at' => now()->subMinute()]),
      $this->token()->setRelation('creator', new UpdateApiTestCreator(['is_active' => false])),
    ] as $token) {
      foreach ([CmsApiTokenCapabilities::SYSTEM_UPDATES_READ, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN] as $capability) {
        $this->assertFalse($capabilities->has($token, $capability));
      }
    }
    $token = $this->token();
    $this->assertTrue($capabilities->has($token, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN));
    $token->creator->systemAccess = false;
    $this->assertFalse($capabilities->has($token, CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN));
  }

  #[Test]
  public function editing_legacy_system_tokens_normalizes_type_without_relaxing_scope_checks(): void
  {
    $tokens = [
      [$this->token(['token_type' => 'system']), true],
      [$this->token(['token_type' => 'system', 'allowed_site_ids' => [1]]), false],
      [$this->token(['token_type' => 'system'], false), false],
      [$this->token(['token_type' => 'personal']), false],
      [$this->token(['token_type' => 'system', 'revoked_at' => now()]), false],
      [$this->token(['token_type' => 'system', 'expires_at' => now()->subMinute()]), false],
      [$this->token(['token_type' => 'system'])->setRelation('creator', new UpdateApiTestCreator(['is_active' => false])), false],
    ];
    foreach ($tokens as [$token, $allowed]) {
      if ($token->token_type === 'system') {
        $token->token_type = null;
      }
      $originalType = $token->token_type;
      $request = new CmsApiTokenRequest;
      $request->replace(['name' => 'Edited token', 'capabilities' => [CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN]]);
      $route = new \Illuminate\Routing\Route('PUT', 'tokens/{token}', fn () => null);
      $route->bind($request);
      $route->setParameter('token', $token);
      $request->setRouteResolver(fn () => $route);
      $validator = Validator::make($request->all(), []);
      $request->withValidator($validator);
      $this->assertSame($allowed, $validator->passes());
      $this->assertSame($originalType, $token->token_type);
    }
  }

  #[Test]
  public function issuer_cannot_grant_update_authority_without_a_system_owner(): void
  {
    $this->expectException(HttpException::class);
    app(CmsApiTokenIssuer::class)->issue('unsafe', capabilities: [CmsApiTokenCapabilities::SYSTEM_UPDATES_RUN]);
  }

  #[Test]
  public function http_run_requires_capability_and_explicit_approval(): void
  {
    $this->authenticate($this->token(['capabilities' => [CmsApiTokenCapabilities::SYSTEM_UPDATES_READ]]));
    $this->postJson('/webadmin/api/system/updates', $this->input())->assertForbidden();
    $this->authenticate($this->token());
    $input = $this->input();
    $input['confirmed'] = false;
    $this->postJson('/webadmin/api/system/updates', $input)->assertUnprocessable();
    $this->assertSame(0, SystemUpdateRequest::query()->count());
  }

  #[Test]
  public function identical_retries_return_the_same_receipt_and_conflicting_retries_do_not_execute(): void
  {
    $this->fakeEngine();
    $this->authenticate($this->token());
    $first = $this->postJson('/webadmin/api/system/updates', $this->input())->assertOk()->assertJsonPath('operation.status', 'success');
    $id = $first->json('operation.id');
    $this->assertStringNotContainsString('private-value', $first->getContent());
    $this->postJson('/webadmin/api/system/updates', $this->input())->assertOk()->assertJsonPath('replayed', true)->assertJsonPath('operation.id', $id);
    $this->getJson('/webadmin/api/system/updates/operations/'.$id)->assertOk()->assertJsonPath('operation.status', 'success');
    $changed = $this->input();
    $changed['target_version'] = '1.92.0';
    $this->postJson('/webadmin/api/system/updates', $changed)->assertConflict()->assertJsonPath('code', 'system_update_idempotency_conflict');
    $this->assertSame(1, $this->executions);
  }

  #[Test]
  public function changed_release_is_rejected_before_the_run_recorder_starts(): void
  {
    $this->fakeEngine();
    $input = $this->input();
    $input['target_version'] = '1.92.0';
    $token = $this->token();
    $result = app(RunApiSystemUpdate::class)->execute($token, $input);
    $this->assertSame(409, $result['http_status']);
    $this->assertSame('system_update_target_changed', $result['operation']->code);
    $this->assertSame(0, SystemUpdateRun::query()->count());
    app(RunApiSystemUpdate::class)->execute($token, $input);
    $this->assertSame(1, $this->executions);
  }

  #[Test]
  public function changed_checksum_and_installed_version_are_rejected(): void
  {
    foreach (['checksum_sha256' => str_repeat('b', 64), 'expected_current_version' => '1.89.0'] as $field => $value) {
      $approval = new SystemUpdateRequest($this->input());
      $approval->$field = $value;
      $client = Mockery::mock(UpdateServerClient::class);
      $client->shouldReceive('check')->once()->andReturn($this->checkResult());
      try {
        (new ApprovedUpdateServerClient($client, $approval))->check();
        $this->fail('Changed approval must be rejected.');
      } catch (ApprovedUpdateTargetChanged) {
        $this->assertTrue(true);
      }
    }
  }

  #[Test]
  public function admission_lock_and_unresolved_requests_block_new_execution(): void
  {
    $lock = Cache::lock('system-updates:api-admission', 900);
    $lock->get();
    $this->assertSame('system_update_in_progress', app(RunApiSystemUpdate::class)->execute($this->token(), $this->input())['code']);
    $lock->release();
    SystemUpdateRequest::query()->create(array_merge(array_diff_key($this->input(), ['confirmed' => true]), ['id' => 'unfinished', 'cms_api_token_id' => 999, 'status' => 'running']));
    $this->assertSame('system_update_requires_attention', app(RunApiSystemUpdate::class)->execute($this->token(), $this->input())['code']);
    $this->assertSame(0, $this->executions);
  }

  #[Test]
  public function rollback_outcome_survives_error_and_history_pruning(): void
  {
    $this->fakeEngine('restored');
    $result = app(RunApiSystemUpdate::class)->execute($this->token(), $this->input());
    $receipt = $result['operation'];
    $this->assertSame('restored', $receipt->status);
    SystemUpdateRun::query()->delete();
    $payload = app(SystemUpdateApiPresenter::class)->operation($receipt);
    $this->assertSame('restored', $payload['status']);
    $this->assertStringNotContainsString('private-value', $payload['result']['output']);
  }

  #[Test]
  public function approved_target_check_runs_inside_the_real_engine_lock_before_backup(): void
  {
    app(CmsPublisherClientConfigurator::class)->configure();
    $approval = new SystemUpdateRequest($this->input());
    $approval->target_version = '1.92.0';
    $client = Mockery::mock(UpdateServerClient::class);
    $client->shouldReceive('check')->once()->andReturnUsing(function (): UpdateCheckResult {
      $lock = Cache::lock(config('publisher-client.lock.name'), 900);
      $this->assertFalse($lock->get(), 'Approval must be validated while the shared engine owns its lock.');

      return $this->checkResult();
    });
    $backup = Mockery::mock(BackupManager::class);
    $backup->shouldNotReceive('create');
    $backup->shouldNotReceive('restore');
    $engine = app()->makeWith(SystemUpdater::class, [
      'client' => new ApprovedUpdateServerClient($client, $approval),
      'backupManager' => $backup,
    ]);
    try {
      $engine->run();
      $this->fail('The unapproved target must not run.');
    } catch (ApprovedUpdateTargetChanged) {
      $this->assertSame(0, SystemUpdateRun::query()->count());
      $this->assertFalse($engine->isLocked());
    }
  }

  #[Test]
  public function interrupted_requests_are_reported_without_reexecution(): void
  {
    $this->fakeEngine();
    $token = $this->token();
    $receipt = SystemUpdateRequest::query()->create(array_merge(array_diff_key($this->input(), ['confirmed' => true]), [
      'id' => 'interrupted', 'cms_api_token_id' => $token->getKey(), 'status' => 'running', 'created_at' => now()->subHour(),
    ]));
    $payload = app(SystemUpdateApiPresenter::class)->operation($receipt);
    $this->assertTrue($payload['requires_attention']);
    $this->assertSame('running', $payload['status']);
    $result = app(RunApiSystemUpdate::class)->execute($token, $this->input());
    $this->assertTrue($result['replayed']);
    $this->assertSame(0, $this->executions);
  }

  #[Test]
  public function api_reconciles_verified_post_apply_warnings_without_opening_the_panel(): void
  {
    $version = WebBlocks::version();
    $run = SystemUpdateRun::query()->create([
      'from_version' => '1.89.0', 'to_version' => $version, 'status' => 'failed',
      'summary' => 'Finalization failed', 'output' => 'Post-update version verified as '.$version.' from canonical WebBlocks version source.',
      'started_at' => now(),
    ]);
    $receipt = SystemUpdateRequest::query()->create(array_merge(array_diff_key($this->input(), ['confirmed' => true]), [
      'id' => 'verified', 'cms_api_token_id' => 1, 'status' => 'failed', 'code' => 'system_update_failed', 'system_update_run_id' => $run->id,
    ]));
    $payload = app(SystemUpdateApiPresenter::class)->operation($receipt);
    $this->assertSame('success_with_warnings', $payload['status']);
    $this->assertNull($payload['code']);
    SystemUpdateRun::query()->delete();
    $this->assertSame('success_with_warnings', app(SystemUpdateApiPresenter::class)->operation($receipt->fresh())['status']);
  }

  #[Test]
  public function fresh_and_update_migrations_are_idempotent_and_discovery_describes_the_contract(): void
  {
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_02_140000_create_system_update_requests_table.php';
    $migration->up();
    $migration->up();
    $this->assertTrue(Schema::hasTable('wbcms_system_update_requests'));
    $paths = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $run = $paths['/system/updates']['post'];
    $this->assertSame('synchronous', $run['x-execution']);
    $this->assertSame('system-updates.run', $run['x-required-capability']);
    $this->assertContains('confirmed', $run['requestBody']['content']['application/json']['schema']['required']);
    foreach (['check', 'store', 'show'] as $action) {
      $route = Route::getRoutes()->getByName('internal-content-api.system.updates.'.$action);
      $this->assertNotNull($route);
      $this->assertContains('internal-api.capability:system-updates.'.($action === 'store' ? 'run' : 'read'), $route->gatherMiddleware());
    }
  }
}

class UpdateApiTestCreator extends Model
{
  protected $guarded = [];

  public bool $systemAccess = true;

  public function can(string $ability): bool
  {
    return $this->systemAccess && $ability === 'access-system';
  }
}
