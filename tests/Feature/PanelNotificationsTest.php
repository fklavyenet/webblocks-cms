<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Foundation\Auth\User as AuthenticatableUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\ContactMessageController;
use WebBlocks\Cms\Http\Controllers\Admin\DashboardController;
use WebBlocks\Cms\Http\Requests\Admin\ContactMessageIndexRequest;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Support\Admin\PanelNotificationsComposer;
use WebBlocks\Cms\Support\ContactMessages\ContactMessageIndexState;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Tests\TestCase;

class PanelNotificationsTest extends TestCase
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
    // Exercise the actual FormRequest and controller without the host's login
    // bootstrap. Return IDs instead of rendering customer data in the inbox.
    Route::get('/test-panel-inbox', function (ContactMessageIndexRequest $request) {
      return app(ContactMessageController::class)->index($request)->getData()['messages']->pluck('id')->all();
    });
  }

  private function user(array $sites, bool $manager = true, bool $super = false)
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(AuthenticatableUser::class, 'App\\Models\\User');
    }
    $user = Mockery::mock('App\\Models\\User')->makePartial();
    $user->forceFill(['id' => 99, 'name' => 'Test Operator']);
    $user->shouldReceive('can')->andReturn(false)->byDefault();
    $user->shouldReceive('can')->with('manage-site-operations')->andReturn($manager);
    $user->shouldReceive('isSuperAdmin')->andReturn($super);
    $user->shouldReceive('accessibleSiteIds')->andReturn(collect($sites));

    return $user;
  }

  private function site(string $handle, bool $scheduled = false): Site
  {
    return Site::create(['name' => $handle, 'handle' => $handle, 'notification_settings' => $scheduled ? SiteNotificationPolicy::DEFAULTS : SiteNotificationPolicy::LEGACY]);
  }

  private function message(Site $site, string $status = 'new'): ContactMessage
  {
    $page = Page::firstOrCreate(['site_id' => $site->id, 'slug' => 'contact'], ['status' => 'published']);

    return ContactMessage::create([
      'page_id' => $page->id, 'name' => 'Private Visitor', 'email' => 'private@example.test',
      'subject' => 'PrivateSubject', 'message' => 'PrivateBody', 'ip_address' => '192.0.2.71',
      'status' => $status, 'notification_enabled' => false, 'notification_status' => 'skipped',
    ]);
  }

  #[Test]
  public function stored_work_is_visible_without_scheduler_mail_configuration_or_email_opt_in(): void
  {
    $site = $this->site('allowed');
    foreach (ContactMessage::statuses() as $status) {
      $this->message($site, $status);
    }
    $data = app(PanelNotificationQuery::class)->forUser($this->user([$site->id]));
    $this->assertTrue($data['inbox_available']);
    $this->assertSame(1, $data['unread']);
    $this->assertSame(2, $data['awaiting']);
    $this->assertSame(0, $data['warnings']);
    $this->assertSame('unverified', $data['scheduler']['health']['status']);
    $this->assertSame(route('admin.contact-messages.index', ['site' => $site->id]), $data['inboxes']->first()['url']);
    $html = $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data]);
    $html->assertSee('allowed')->assertSee('neither a scheduler nor outgoing email');
    foreach (['Private Visitor', 'private@example.test', 'PrivateSubject', 'PrivateBody', '192.0.2.71'] as $private) {
      $html->assertDontSee($private);
    }
  }

  #[Test]
  public function panel_reads_never_send_mail_mark_messages_read_consume_pending_events_or_manufacture_health(): void
  {
    Mail::fake();
    $dispatcher = Mockery::mock(SiteNotificationDispatcher::class);
    $dispatcher->shouldNotReceive('run');
    $dispatcher->shouldNotReceive('submit');
    $this->app->instance(SiteNotificationDispatcher::class, $dispatcher);
    $site = $this->site('scheduled', true);
    $message = $this->message($site);
    DB::table('wbcms_site_notification_events')->insert([
      'site_id' => $site->id, 'channel' => 'contact', 'source_id' => $message->id,
      'status' => 'pending', 'created_at' => now(), 'updated_at' => now(),
    ]);
    $user = $this->user([$site->id]);
    $before = $message->fresh()->getAttributes();
    for ($i = 0; $i < 2; $i++) {
      $data = app(PanelNotificationQuery::class)->forUser($user);
      $this->assertSame(1, $data['awaiting']);
      $this->assertSame(1, $data['warnings']);
      $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => $data])
        ->assertSee('1 unread message(s).')->assertSee('class="wb-btn-badge"', false);
    }
    $this->assertSame($before, $message->fresh()->getAttributes());
    $this->assertSame('pending', DB::table('wbcms_site_notification_events')->value('status'));
    $this->assertSame(0, DB::table('wbcms_site_notification_states')->count());
    $this->assertNull(SystemSetting::query()->where('key', SchedulerHealth::STATE_KEY)->value('value'));
    Mail::assertNothingSent();
    Mail::assertNothingQueued();
  }

  #[Test]
  public function restricted_sites_and_non_managers_cannot_influence_counts_links_or_warnings(): void
  {
    $allowed = $this->site('allowed');
    $private = $this->site('secret-site', true);
    $this->message($allowed);
    $this->message($private);
    $query = app(PanelNotificationQuery::class);
    $data = $query->forUser($this->user([$allowed->id]));
    $this->assertSame(1, $data['unread']);
    $this->assertSame(0, $data['warnings']);
    $this->assertSame([$allowed->id], $data['inboxes']->pluck('site.id')->all());
    $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data])
      ->assertDontSee('secret-site');
    $this->assertNull($query->forUser($this->user([$allowed->id], false)));
    $this->assertNull($query->forUser(null));
    $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => null])
      ->assertDontSee('data-wb-panel-notifications');
    $this->assertSame(0, $query->forUser($this->user([]))['unread']);
    $this->assertSame(2, $query->forUser($this->user([], true, true))['unread']);
  }

  #[Test]
  public function counts_follow_real_message_workflow_without_a_background_refresh(): void
  {
    $site = $this->site('workflow');
    $message = $this->message($site);
    $query = app(PanelNotificationQuery::class);
    $user = $this->user([$site->id]);
    $this->assertSame(1, $query->forUser($user)['unread']);
    $message->update(['status' => 'read']);
    $data = $query->forUser($user);
    $this->assertSame(0, $data['unread']);
    $this->assertSame(1, $data['awaiting']);
    $message->update(['status' => 'replied']);
    $data = $query->forUser($user);
    $this->assertSame(0, $data['awaiting']);
    $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data])
      ->assertSee('No contact messages await a reply.');
  }

  #[Test]
  public function notification_bookkeeping_schema_is_not_needed_but_a_missing_inbox_is_not_reported_as_empty(): void
  {
    $site = $this->site('schema');
    $this->message($site);
    Schema::drop('wbcms_site_notification_events');
    $query = app(PanelNotificationQuery::class);
    $user = $this->user([$site->id]);
    $this->assertSame(1, $query->forUser($user)['awaiting']);
    Schema::drop((new ContactMessage)->getTable());
    $data = $query->forUser($user);
    $this->assertFalse($data['inbox_available']);
    $this->assertSame(1, $data['warnings']);
    $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data])
      ->assertSee('Apply the CMS database migrations')->assertDontSee('No contact messages await a reply.');
  }

  #[Test]
  public function the_shared_layout_composer_loads_current_state_for_non_dashboard_pages_and_reuses_dashboard_data(): void
  {
    $site = $this->site('layout');
    $this->message($site);
    request()->setUserResolver(fn () => $this->user([$site->id]));
    $view = view('webblocks-cms::layouts.admin');
    app(PanelNotificationsComposer::class)->compose($view);
    $this->assertSame(1, $view->getData()['panelNotifications']['awaiting']);
    $query = Mockery::mock(PanelNotificationQuery::class);
    $query->shouldNotReceive('forUser');
    (new PanelNotificationsComposer($query))->compose($view);
  }

  #[Test]
  public function the_real_dashboard_and_other_panel_pages_render_the_authorized_indicator(): void
  {
    $site = $this->site('dashboard-render');
    $message = $this->message($site);
    $user = $this->user([$site->id]);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);
    $dashboard = app(DashboardController::class)(request())->render();
    $this->assertStringContainsString('data-wb-panel-notifications', $dashboard);
    $this->assertStringContainsString('data-wb-panel-notification-summary', $dashboard);
    $this->assertStringContainsString('1 unread message(s).', $dashboard);
    $this->assertStringNotContainsString('PrivateBody', $dashboard);
    $layout = view('webblocks-cms::layouts.admin')->render();
    $this->assertStringContainsString('data-wb-panel-notifications', $layout);
    $this->assertSame('new', $message->fresh()->status);
  }

  #[Test]
  public function read_messages_and_scheduler_warnings_do_not_create_an_unread_badge(): void
  {
    $site = $this->site('reported-case', true);
    $message = $this->message($site, 'read');
    $data = app(PanelNotificationQuery::class)->forUser($this->user([$site->id]));
    $this->assertSame(0, $data['unread']);
    $this->assertSame(1, $data['awaiting']);
    $this->assertSame(1, $data['warnings']);
    $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => $data])
      ->assertSee('0 unread message(s).')->assertDontSee('wb-btn-badge');
    $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data])
      ->assertSee('Review messages awaiting reply')->assertSee('reported-case');
    $this->assertSame('read', $message->fresh()->status);
  }

  #[Test]
  public function opening_a_message_clears_its_unread_badge_while_pending_reply_and_health_remain(): void
  {
    $site = $this->site('read-message', true);
    $message = $this->message($site);
    $user = $this->user([$site->id]);
    request()->setUserResolver(fn () => $user);
    $query = app(PanelNotificationQuery::class);
    $this->assertSame(1, $query->forUser($user)['unread']);
    app(ContactMessageController::class)->show($message);
    $data = $query->forUser($user);
    $this->assertSame(0, $data['unread']);
    $this->assertSame(1, $data['awaiting']);
    $this->assertSame(1, $data['warnings']);
    $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => $data])
      ->assertDontSee('wb-btn-badge');
  }

  #[Test]
  public function inbox_links_filter_the_selected_site_and_reject_inaccessible_or_malformed_filters(): void
  {
    $first = $this->site('first');
    $second = $this->site('second');
    $private = $this->site('private');
    $message = $this->message($first);
    $other = $this->message($second);
    $this->message($private);
    $this->actingAs($this->user([$first->id, $second->id]));
    $this->getJson('/test-panel-inbox?site='.$first->id)->assertOk()->assertExactJson([$message->id]);
    $this->getJson('/test-panel-inbox')->assertOk()->assertJsonCount(2);
    $this->getJson('/test-panel-inbox?site='.$private->id)->assertUnprocessable()->assertJsonValidationErrors('site');
    $this->getJson('/test-panel-inbox?site[]=1')->assertUnprocessable()->assertJsonValidationErrors('site');
    $this->getJson('/test-panel-inbox?site=99999')->assertUnprocessable();
    $this->actingAs($this->user([$first->id], false));
    $this->getJson('/test-panel-inbox?site='.$first->id)->assertForbidden();
    $state = app(ContactMessageIndexState::class);
    $this->assertSame(['site' => (string) $first->id], $state->normalizeQuery(['site' => $first->id]));
    $this->assertSame([], $state->normalizeQuery(['site' => -1]));
    $this->assertSame(route('admin.contact-messages.index', ['site' => $first->id]), $state->sanitizeReturnUrl(route('admin.contact-messages.index', ['site' => $first->id])));
  }
}
