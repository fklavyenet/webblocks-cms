<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Mockery;
use WebBlocks\Cms\Http\Controllers\Admin\SystemDiagnosticReportController;
use WebBlocks\Cms\Models\SystemUpdateRun;
use WebBlocks\Cms\Queries\SystemDiagnosticReportQuery;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Support\Plugins\InstalledPluginRepository;
use WebBlocks\Cms\Support\System\SystemDiagnosticReports;
use WebBlocks\Cms\Tests\TestCase;

class SystemDiagnosticReportTest extends TestCase
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

  protected function defineRoutes($router): void
  {
    Route::get('/test-diagnostics', [SystemDiagnosticReportController::class, 'index'])->name('test.diagnostics.index');
    Route::post('/test-diagnostics', [SystemDiagnosticReportController::class, 'prepare'])->name('test.diagnostics.prepare');
    Route::post('/test-diagnostics/download', [SystemDiagnosticReportController::class, 'download'])->name('test.diagnostics.download');
  }

  private function actor(int $id = 91, bool $system = true): User
  {
    $user = Mockery::mock(User::class)->makePartial();
    $user->forceFill(['id' => $id, 'name' => 'Synthetic operator', 'is_active' => true]);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $user->shouldReceive('can')->with('access-system')->andReturn($system);
    $user->shouldReceive('isSuperAdmin')->andReturn($system);

    return $user;
  }

  public function test_opening_the_form_does_not_collect_or_send_data(): void
  {
    $this->mock(SystemDiagnosticReportQuery::class, fn ($mock) => $mock->shouldNotReceive('collect'));
    $this->actingAs($this->actor())->get('/test-diagnostics')->assertOk()->assertSee('Prepare diagnostic report');
  }

  public function test_every_operation_requires_system_authority_and_explicit_collection_consent(): void
  {
    $this->actingAs($this->actor(system: false))->get('/test-diagnostics')->assertForbidden();
    $this->post('/test-diagnostics', ['categories' => ['system_summary'], 'prepare_consent' => 1])->assertForbidden();
    $this->post('/test-diagnostics/download', ['report' => 'invalid'])->assertForbidden();
    $this->actingAs($this->actor())->post('/test-diagnostics', ['categories' => ['system_summary']])->assertSessionHasErrors('prepare_consent');
    $this->post('/test-diagnostics', ['categories' => ['arbitrary_file'], 'prepare_consent' => 1])->assertSessionHasErrors('categories.0');
  }

  public function test_download_is_exact_reviewed_content_without_recollection_and_cannot_cross_users(): void
  {
    $actor = $this->actor();
    $reports = app(SystemDiagnosticReports::class);
    $envelope = $reports->prepare($actor, ['system_summary']);
    $expected = $reports->encode($reports->open($actor, $envelope));
    $this->mock(SystemDiagnosticReportQuery::class, fn ($mock) => $mock->shouldNotReceive('collect'));
    $this->actingAs($actor)->post('/test-diagnostics/download', ['report' => $envelope])->assertOk()->assertContent($expected)->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($this->actor(92))->post('/test-diagnostics/download', ['report' => $envelope])->assertSessionHasErrors('report');
    $this->actingAs($actor);
    $this->travel(31)->minutes();
    $this->post('/test-diagnostics/download', ['report' => $envelope])->assertSessionHasErrors('report');
  }

  public function test_selected_update_never_exports_output_summary_paths_or_actor(): void
  {
    $run = SystemUpdateRun::create(['from_version' => '1.117.0', 'to_version' => '1.118.0', 'status' => 'failed', 'summary' => 'password=private-value customer@example.test', 'output' => 'Bearer private-value /private/host-data', 'warning_count' => 2]);
    $report = app(SystemDiagnosticReportQuery::class)->collect(['update_run'], $run->id);
    $text = json_encode($report);
    $this->assertSame('failed', $report['categories']['update_run']['status']);
    foreach (['private-value', 'customer@', '/private/', 'summary', 'output', 'triggered_by'] as $excluded) {
      $this->assertStringNotContainsString($excluded, $text);
    }
    $this->assertSame('unavailable', app(SystemDiagnosticReportQuery::class)->collect(['update_run'], PHP_INT_MAX)['categories']['update_run']['state']);
  }

  public function test_saved_health_exports_only_counts_and_does_not_refresh_or_invoke_plugins(): void
  {
    Cache::put(app(SystemHealthQuery::class)->cacheKey(), ['checked_at' => now()->subHour()->toIso8601String(), 'categories' => ['system' => [['status' => 'critical', 'parameters' => ['site_name' => 'PRIVATE CUSTOMER']]]]]);
    $this->mock(InstalledPluginRepository::class, fn ($mock) => $mock->shouldNotReceive('installed'));
    $report = app(SystemDiagnosticReportQuery::class)->collect(['saved_health']);
    $this->assertSame('stale', $report['categories']['saved_health']['state']);
    $this->assertSame(1, $report['categories']['saved_health']['items'][0]['critical']);
    $this->assertStringNotContainsString('PRIVATE CUSTOMER', json_encode($report));
  }

  public function test_adversarial_log_content_is_never_copied(): void
  {
    $original = storage_path();
    $temporary = sys_get_temp_dir().'/wbcms-diagnostics-'.bin2hex(random_bytes(6));
    mkdir($temporary.'/logs', 0700, true);
    $this->app->useStoragePath($temporary);
    try {
      file_put_contents($temporary.'/logs/laravel.log', '[2026-11-04 12:00:00] production.ERROR: password="private value" Bearer abcdefgh SQL SELECT customer@example.test /secret/path'.PHP_EOL);
      $report = app(SystemDiagnosticReportQuery::class)->collect(['recent_application_errors']);
      $this->assertCount(1, $report['categories']['recent_application_errors']['items']);
      foreach (['password', 'Bearer', 'private value', 'customer@', 'SELECT', '/secret/path'] as $excluded) {
        $this->assertStringNotContainsString($excluded, json_encode($report));
      }
    } finally {
      $this->app->useStoragePath($original);
      unlink($temporary.'/logs/laravel.log');
      rmdir($temporary.'/logs');
      rmdir($temporary);
    }
  }
}
