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
