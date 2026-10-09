<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Tests\TestCase;

class SchedulerHealthTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function health(): SchedulerHealth
  {
    return app(SchedulerHealth::class);
  }

  #[Test]
  public function reads_and_manual_dispatch_do_not_claim_that_the_scheduler_is_running(): void
  {
    $before = SystemSetting::count();
    $this->assertSame('unverified', $this->health()->snapshot()['status']);
    $this->assertSame($before, SystemSetting::count());
    $this->assertSame(0, Artisan::call('webblocks:notifications:dispatch'));
    $snapshot = $this->health()->snapshot();
    $this->assertSame('unverified', $snapshot['scheduler_status']);
    $this->assertSame('healthy', $snapshot['notification_status']);
    $this->assertNull($snapshot['last_seen_at']);
    $this->assertNotNull($snapshot['last_completed_at']);
    $this->assertSame(1, Artisan::call('webblocks:scheduler:status', ['--json' => true]));
    $this->assertSame('unverified', json_decode(Artisan::output(), true)['status']);
  }

  #[Test]
  public function the_real_scheduled_callback_records_a_pulse_and_stale_runs_become_delayed(): void
  {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => $event->description === 'webblocks-scheduler-heartbeat');
    $this->assertNotNull($event);
    $this->assertSame('* * * * *', $event->expression);
    $event->run($this->app);
    Artisan::call('webblocks:notifications:dispatch');
    $this->assertSame('healthy', $this->health()->snapshot()['status']);
    $this->travel(6)->minutes();
    $this->assertSame('delayed', $this->health()->snapshot()['status']);
    $event->run($this->app);
    $snapshot = $this->health()->snapshot();
    $this->assertSame('healthy', $snapshot['scheduler_status']);
    $this->assertSame('delayed', $snapshot['notification_status']);
    Artisan::call('webblocks:notifications:dispatch');
    $this->assertSame('healthy', $this->health()->snapshot()['status']);
  }

  #[Test]
  public function failed_processing_is_visible_without_logging_exception_payloads_and_can_recover(): void
  {
    $this->health()->scheduledHeartbeat();
    $this->mock(SiteNotificationDispatcher::class, fn ($mock) => $mock->shouldReceive('run')->once()->andThrow(new \RuntimeException('private-payload-and-credentials')));
    $this->assertSame(1, Artisan::call('webblocks:notifications:dispatch'));
    $this->assertSame('failed', $this->health()->snapshot()['status']);
    $state = SystemSetting::where('key', SchedulerHealth::STATE_KEY)->value('value');
    $this->assertStringNotContainsString('private-payload-and-credentials', $state);
    $this->assertStringNotContainsString('private-payload-and-credentials', Artisan::output());
    $this->app->forgetInstance(SiteNotificationDispatcher::class);
    $this->assertSame(0, Artisan::call('webblocks:notifications:dispatch'));
    $this->assertSame('healthy', $this->health()->snapshot()['status']);
  }

  #[Test]
  public function future_or_relative_timestamps_are_not_accepted_as_health_evidence(): void
  {
    SystemSetting::create(['key' => SchedulerHealth::STATE_KEY, 'value' => json_encode(['scheduler_last_seen_at' => 'now', 'notifications_completed_at' => now()->addDays(2)->toIso8601String()])]);
    $snapshot = $this->health()->snapshot();
    $this->assertSame('unverified', $snapshot['status']);
    $this->assertNull($snapshot['last_seen_at']);
    $this->assertNull($snapshot['last_completed_at']);
  }

  #[Test]
  public function site_policy_selects_the_dependency_and_the_panel_explains_unverified_health(): void
  {
    $site = Site::create(['name' => 'Northstar', 'handle' => 'northstar']);
    $this->assertTrue($this->health()->forSite($site)['required']);
    $this->view('webblocks-cms::admin.partials.scheduler-health', ['site' => $site])
      ->assertSee('Not yet verified')->assertSee('wb-alert-warning', false)->assertSee('server administrator');
    $site->update(['notification_settings' => array_replace(SiteNotificationPolicy::DEFAULTS, ['notification_frequency' => 'immediate', 'daily_summary' => false])]);
    $this->assertFalse($this->health()->forSite($site)['required']);
    $this->view('webblocks-cms::admin.partials.scheduler-health', ['site' => $site])
      ->assertSee('wb-alert-info', false)->assertDontSee('may remain pending');
  }
}
