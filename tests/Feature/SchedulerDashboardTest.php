<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\SiteNotificationHealthQuery;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Tests\TestCase;

class SchedulerDashboardTest extends TestCase
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

  private function user(array $sites, bool $manager = true)
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $user = Mockery::mock('App\\Models\\User');
    $user->shouldReceive('can')->with('manage-site-operations')->andReturn($manager);
    $user->shouldReceive('isSuperAdmin')->andReturn(false);
    $user->shouldReceive('accessibleSiteIds')->andReturn($sites);

    return $user;
  }

  private function site(string $handle, bool $scheduled): Site
  {
    return Site::create(['name' => $handle, 'handle' => $handle, 'notification_settings' => $scheduled ? SiteNotificationPolicy::DEFAULTS : SiteNotificationPolicy::LEGACY]);
  }

  #[Test]
  public function upgraded_immediate_only_sites_keep_a_visible_informational_scheduler_card(): void
  {
    $legacy = $this->site('legacy', false);
    $data = app(SiteNotificationHealthQuery::class)->dashboardForUser($this->user([$legacy->id]));
    $this->assertCount(1, $data['sites']);
    $this->assertFalse($data['health']['required']);
    $this->assertSame('unverified', $data['health']['status']);
    $this->view('webblocks-cms::admin.partials.dashboard-scheduler-health', ['notificationHealthSites' => $data['sites'], 'dashboardSchedulerHealth' => $data['health']])
      ->assertSee('Scheduler and notification worker')->assertSee('Not yet verified')
      ->assertSee('wb-alert-info', false)->assertSee('do not require scheduled delivery')
      ->assertDontSee('may remain pending');
  }

  #[Test]
  public function warnings_consider_every_accessible_site_instead_of_only_the_first(): void
  {
    $legacy = $this->site('legacy', false);
    $scheduled = $this->site('scheduled', true);
    $data = app(SiteNotificationHealthQuery::class)->dashboardForUser($this->user([$legacy->id, $scheduled->id]));
    $this->assertCount(2, $data['sites']);
    $this->assertTrue($data['health']['required']);
    $this->view('webblocks-cms::admin.partials.dashboard-scheduler-health', ['notificationHealthSites' => $data['sites'], 'dashboardSchedulerHealth' => $data['health']])
      ->assertSee('wb-alert-warning', false)->assertSee('may remain pending')->assertSee('legacy')->assertSee('scheduled');
  }

  #[Test]
  public function inaccessible_scheduled_sites_do_not_influence_the_notice_or_appear_in_it(): void
  {
    $legacy = $this->site('allowed-legacy', false);
    $this->site('private-scheduled', true);
    $data = app(SiteNotificationHealthQuery::class)->dashboardForUser($this->user([$legacy->id]));
    $this->assertFalse($data['health']['required']);
    $this->assertSame([$legacy->id], $data['sites']->pluck('site.id')->all());
    $this->view('webblocks-cms::admin.partials.dashboard-scheduler-health', ['notificationHealthSites' => $data['sites'], 'dashboardSchedulerHealth' => $data['health']])
      ->assertSee('allowed-legacy')->assertDontSee('private-scheduled');
  }

  #[Test]
  public function non_managers_and_users_without_accessible_sites_do_not_receive_health_data(): void
  {
    $legacy = $this->site('legacy', false);
    $query = app(SiteNotificationHealthQuery::class);
    foreach ([$this->user([$legacy->id], false), $this->user([])] as $user) {
      $data = $query->dashboardForUser($user);
      $this->assertCount(0, $data['sites']);
      $this->assertNull($data['health']);
      $this->view('webblocks-cms::admin.partials.dashboard-scheduler-health', ['notificationHealthSites' => $data['sites'], 'dashboardSchedulerHealth' => $data['health']])
        ->assertDontSee('Scheduler and notification worker');
    }
  }
}
