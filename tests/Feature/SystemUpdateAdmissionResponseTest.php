<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Actions\System\StartAdminSystemUpdate;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\AdminUpdateOperation;
use WebBlocks\Cms\Models\SystemUpdateRequest;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Models\UpdateChainStep;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateLauncher;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class SystemUpdateAdmissionResponseTest extends TestCase
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
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->actingAs($this->cmsActor(MaintenanceTestUser::forceCreate(['name' => 'Synthetic administrator', 'email' => 'admin@example.test',
      'password' => 'unused', 'role' => 'super_admin', 'is_active' => true])));
    config(['webblocks-updates.enabled' => true, 'webblocks-updates.chains.enabled' => true]);
    Http::preventStrayRequests();
    $this->mock(AdminUpdateLauncher::class)->shouldNotReceive('launch');
  }

  public static function blockedRuns(): iterable
  {
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      foreach (['pending', 'running'] as $status) {
        yield $locale.' '.$status => [$locale, $status];
      }
    }
  }

  #[DataProvider('blockedRuns')]
  public function test_rejected_admission_exposes_the_localized_reason_without_starting_or_rewriting_work(string $locale, string $status): void
  {
    config(['app.locale' => $locale]);
    $run = SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => '1.0.1',
      'status' => $status, 'started_at' => now()->subMonth()]);
    $original = $run->fresh()->getAttributes();
    $reason = trans('webblocks-cms::admin.update_chain.locked', [], $locale);

    $this->postJson(route('admin.system.updates.store'))
      ->assertConflict()->assertExactJson(['message' => $reason, 'error' => $reason]);

    $this->assertSame($original, $run->fresh()->getAttributes());
    $this->assertSame(1, SystemUpdateRun::count());
    $this->assertSame(0, AdminUpdateOperation::count());
    $this->assertSame(0, UpdateChainStep::count());
    $this->assertSame(0, SystemUpdateRequest::count());
    Http::assertNothingSent();
  }

  public function test_json_rejection_exposes_only_the_safe_user_message(): void
  {
    $this->mock(StartAdminSystemUpdate::class)->shouldReceive('execute')->once()
      ->andThrow(new UpdateException('Synthetic safe reason.', 'Synthetic private diagnostic.'));

    $this->postJson(route('admin.system.updates.store'))->assertConflict()
      ->assertExactJson(['message' => 'Synthetic safe reason.', 'error' => 'Synthetic safe reason.']);
  }

  public static function disabledSettings(): iterable
  {
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      yield $locale.' web updates disabled' => [$locale, 'publisher-client.web_run_enabled', 'PUBLISHER_CLIENT_WEB_RUN_ENABLED'];
    }
    yield 'CMS updates disabled' => ['en', 'webblocks-updates.enabled', 'WEBBLOCKS_UPDATES_ENABLED'];
    yield 'Publisher Client disabled' => ['en', 'publisher-client.enabled', 'PUBLISHER_CLIENT_ENABLED'];
    yield 'single-release CMS updates disabled' => ['en', 'webblocks-updates.enabled', 'WEBBLOCKS_UPDATES_ENABLED', false];
    yield 'single-release Publisher Client disabled' => ['en', 'publisher-client.enabled', 'PUBLISHER_CLIENT_ENABLED', false];
    yield 'single-release web updates disabled' => ['tr', 'publisher-client.web_run_enabled', 'PUBLISHER_CLIENT_WEB_RUN_ENABLED', false];
  }

  #[DataProvider('disabledSettings')]
  public function test_update_modal_names_the_disabled_setting_without_contacting_publisher(
    string $locale, string $configKey, string $setting, bool $chainsEnabled = true,
  ): void {
    config(['app.locale' => $locale, 'webblocks-updates.chains.enabled' => $chainsEnabled, $configKey => false]);
    $reason = trans('webblocks-cms::admin.update_chain.disabled', ['setting' => $setting], $locale);

    $this->assertStringContainsString($setting, $reason);
    $this->postJson(route('admin.system.updates.store'))
      ->assertConflict()->assertExactJson(['message' => $reason, 'error' => $reason]);

    $this->assertSame(0, AdminUpdateOperation::count());
    Http::assertNothingSent();
  }

  public function test_unavailable_plan_uses_the_general_message(): void
  {
    Http::fake(['*/api/updates/plan*' => Http::response([], 409)]);
    $reason = __('webblocks-cms::admin.update_chain.unavailable');

    $this->postJson(route('admin.system.updates.store'))
      ->assertConflict()->assertExactJson(['message' => $reason, 'error' => $reason]);

    $this->assertSame(0, AdminUpdateOperation::count());
    Http::assertSentCount(1);
  }

  public function test_html_rejection_keeps_the_existing_redirect_and_validation_error(): void
  {
    SystemUpdateRun::create(['from_version' => '1.0.0', 'to_version' => '1.0.1',
      'status' => 'running', 'started_at' => now()->subMonth()]);

    $this->post(route('admin.system.updates.store'))
      ->assertRedirect(route('admin.system.updates.index'))
      ->assertSessionHasErrors(['system_update' => __('webblocks-cms::admin.update_chain.locked')]);
    $this->assertSame(0, AdminUpdateOperation::count());
  }
}
