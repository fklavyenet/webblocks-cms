<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\ContactMessageController;
use WebBlocks\Cms\Http\Controllers\Admin\DashboardController;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Http\Requests\Admin\ContactMessageIndexRequest;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\CommentEntry;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Queries\PanelNotificationQuery;
use WebBlocks\Cms\Queries\SystemHealthQuery;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Support\Admin\PanelNotificationsComposer;
use WebBlocks\Cms\Support\ContactMessages\ContactMessageIndexState;
use WebBlocks\Cms\Support\SiteNotifications\SchedulerHealth;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
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
    class_alias(CmsSqlTestUser::class, 'App\\Models\\User');
    }
    $class = 'App\\Models\\User';

    return $this->cmsActor((new $class)->forceFill(['id' => 99, 'name' => 'Test Operator', 'role' => $super ? 'super_admin' : ($manager ? 'site_admin' : 'editor'), 'is_active' => true]), $sites);
  }

  private function site(string $handle, bool $scheduled = false): Site
  {
    return Site::create(['name' => $handle, 'handle' => $handle, 'notification_settings' => $scheduled ? array_replace(SiteNotificationPolicy::DEFAULTS, ['notification_frequency' => 'batched', 'daily_summary' => true]) : SiteNotificationPolicy::LEGACY]);
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
        ->assertSee('1 unread contact message')->assertSee('class="wb-btn-badge"', false);
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
    $this->assertNull($query->forUser($this->user([])));
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
    $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => $data])
      ->assertDontSee('data-wb-panel-notifications')->assertDontSee('wb-icon-bell');
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
    $query->shouldReceive('pendingCommentsForUser')->once()->andReturn(0);
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
    $this->assertStringContainsString('1 unread contact message', $dashboard);
    $this->assertStringNotContainsString('PrivateBody', $dashboard);
    $layout = view('webblocks-cms::layouts.admin')->render();
    $this->assertStringContainsString('data-wb-panel-notifications', $layout);
    $this->assertSame('new', $message->fresh()->status);

    $message->update(['status' => 'read']);
    $dashboard = app(DashboardController::class)(request())->render();
    $this->assertStringNotContainsString('data-wb-panel-notifications', $dashboard);
    $this->assertStringContainsString('data-wb-panel-notification-summary', $dashboard);
    $this->assertStringNotContainsString('data-wb-panel-notifications', view('webblocks-cms::layouts.admin')->render());
  }

  #[Test]
  public function the_bell_links_to_the_same_authorized_queues_it_counts(): void
  {
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_07_12_140000_ensure_prefixed_engagement_tables.php')->up();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $allowed = $this->site('actionable');
    $readOnly = $this->site('read-only');
    $private = $this->site('private');
    $message = $this->message($allowed);
    $this->message($allowed, 'read');
    $this->message($readOnly);
    $this->message($private);

    $comments = collect([$allowed, $readOnly, $private])->map(fn (Site $site) => CommentEntry::create([
      'site_id' => $site->id,
      'page_id' => Page::where('site_id', $site->id)->firstOrFail()->id,
      'body' => 'PrivateComment',
      'status' => 'pending',
    ]));
    $comment = $comments->first();
    $user = $this->user([$allowed->id, $readOnly->id]);
    $readOnlyRole = CmsRole::create(['name' => 'Read comments', 'handle' => 'read-comments']);
    foreach (['cms.panel.access', 'cms.engagement.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $readOnlyRole->id, 'permission' => $permission]);
    }
    DB::table('wbcms_site_user')->where('user_id', $user->id)->where('site_id', $readOnly->id)->update(['role_id' => $readOnlyRole->id]);
    $this->actingAs($user);

    $dashboardUrl = route('admin.dashboard');
    $messageUrl = route('admin.contact-messages.index', ['status' => 'new']);
    $commentUrl = route('admin.engagement.comments.index', ['status' => 'pending', 'moderatable' => 1]);

    $this->get($dashboardUrl)->assertOk()
      ->assertSee('Panel notifications: 2 pending items')
      ->assertSee('1 unread contact message')->assertSee($messageUrl)
      ->assertSee('1 comment awaiting moderation')->assertSee($commentUrl)
      ->assertDontSee('PrivateBody')->assertDontSee('PrivateComment');
    $this->get($messageUrl)->assertOk()->assertViewHas('messages', fn ($messages) => $messages->pluck('id')->all() === [$message->id]);
    $this->get($commentUrl)->assertOk()->assertViewHas('comments', fn ($records) => $records->pluck('id')->all() === [$comment->id]);
    $this->assertSame('new', $message->fresh()->status);
    $this->assertSame('pending', $comment->fresh()->status);

    $this->get(route('admin.contact-messages.show', $message))->assertOk();
    $this->get($dashboardUrl)->assertOk()->assertSee('Panel notifications: 1 pending item')->assertSee($commentUrl)->assertDontSee($messageUrl);

    $this->patch(route('admin.engagement.comments.status', $comment), ['status' => 'approved', 'return_query' => parse_url($commentUrl, PHP_URL_QUERY)])
      ->assertRedirect($commentUrl);
    $this->get($dashboardUrl)->assertOk()->assertDontSee('data-wb-panel-notifications');
  }

  #[Test]
  public function site_managers_keep_scoped_scheduler_details_without_querying_installation_health(): void
  {
    $allowed = $this->site('manager-site', true);
    $this->site('inaccessible-site', true);
    $user = $this->user([$allowed->id]);
    $this->actingAs($user);
    request()->setUserResolver(fn () => $user);
    $this->mock(SystemHealthQuery::class, function ($mock) {
      $mock->shouldNotReceive('dashboardSummary');
      $mock->shouldNotReceive('report');
    });
    $dashboard = app(DashboardController::class)(request())->render();
    $this->assertStringContainsString('Scheduler and notification worker', $dashboard);
    $this->assertStringContainsString('may remain pending', $dashboard);
    $this->assertStringContainsString('manager-site', $dashboard);
    $this->assertStringNotContainsString('inaccessible-site', $dashboard);
    $this->assertStringNotContainsString('data-wb-system-health-summary', $dashboard);
    $this->assertStringNotContainsString(route('admin.system.health.index'), $dashboard);
  }

  #[Test]
  public function read_messages_and_scheduler_warnings_do_not_show_the_notification_bell(): void
  {
    $site = $this->site('reported-case', true);
    $message = $this->message($site, 'read');
    $data = app(PanelNotificationQuery::class)->forUser($this->user([$site->id]));
    $this->assertSame(0, $data['unread']);
    $this->assertSame(1, $data['awaiting']);
    $this->assertSame(1, $data['warnings']);
    $this->view('webblocks-cms::admin.partials.panel-notifications-indicator', ['panelNotifications' => $data])
      ->assertDontSee('data-wb-panel-notifications')->assertDontSee('wb-icon-bell')->assertDontSee('wb-btn-badge');
    $this->view('webblocks-cms::admin.partials.dashboard-panel-notifications', ['panelNotifications' => $data])
      ->assertSee('Review messages awaiting reply')->assertSee('reported-case');
    $this->assertSame('read', $message->fresh()->status);
  }

  #[Test]
  public function opening_the_last_unread_message_hides_the_bell_while_pending_reply_and_health_remain(): void
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
      ->assertDontSee('data-wb-panel-notifications')->assertDontSee('wb-icon-bell')->assertDontSee('wb-btn-badge');
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

  #[Test]
  public function inbox_counts_current_filtered_messages_separately_from_protection_history(): void
  {
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $site = $this->site('inbox');
    $otherSite = $this->site('other-inbox');
    $message = $this->message($site);
    $this->message($site, 'read');
    $this->message($otherSite);
    $this->actingAs($this->user([$site->id, $otherSite->id]));

    foreach ([[$site, 'contact', 4], [$site, 'comment', 2], [$otherSite, 'contact', 3]] as [$source, $surface, $allowed]) {
      DB::table('wbcms_submission_daily_totals')->insert([
        'site_id' => $source->id, 'date' => now()->toDateString(), 'surface' => $surface,
        'allowed' => $allowed, 'quarantined' => 0, 'spam' => 0,
      ]);
    }

    $url = route('admin.contact-messages.index', ['site' => $site->id, 'status' => 'new', 'notification' => 'skipped', 'search' => 'PrivateSubject']);
    $this->get($url)->assertOk()
      ->assertSeeInOrder(['Messages matching the current filters', 'Mark selected as read', 'Local protection'])
      ->assertViewHas('statusCounts', fn ($counts) => $counts->count() === 1 && (int) $counts['new'] === 1)
      ->assertViewHas('protectionSummary', fn ($summary) => $summary['total'] === 4);

    $message->delete();
    $this->get($url)->assertOk()
      ->assertViewHas('statusCounts', fn ($counts) => $counts->isEmpty())
      ->assertViewHas('protectionSummary', fn ($summary) => $summary['total'] === 4);
  }

  #[Test]
  public function bulk_read_status_only_changes_eligible_messages_on_writable_sites(): void
  {
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $site = $this->site('inbox');
    $readOnly = $this->site('read-only');
    $private = $this->site('private');
    $message = $this->message($site);
    $spam = $this->message($site, 'spam');
    $readOnlyMessage = $this->message($readOnly);
    $privateMessage = $this->message($private);
    $user = $this->user([$site->id, $readOnly->id]);
    $role = CmsRole::create(['name' => 'Read messages', 'handle' => 'read-messages']);
    foreach (['cms.panel.access', 'cms.messages.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    DB::table('wbcms_site_user')->where('user_id', $user->id)->where('site_id', $readOnly->id)->update(['role_id' => $role->id]);
    $this->actingAs($user);

    $index = route('admin.contact-messages.index', ['site' => $site->id, 'status' => 'new', 'notification' => 'skipped']);
    $this->get(route('admin.contact-messages.index'))->assertOk()
      ->assertViewHas('editableMessageIds', fn ($ids) => array_keys($ids) === [$message->id])
      ->assertSee('form="bulk-contact-message-status" data-wb-admin-bulk-editable-status="new"', false);
    $this->get(route('admin.contact-messages.index', ['site' => $readOnly->id]))
      ->assertOk()->assertDontSee('bulk-contact-message-status');

    $endpoint = route('admin.contact-messages.bulk-status');
    $payload = ['contact_message_ids' => [$message->id, $spam->id, $readOnlyMessage->id, $privateMessage->id], 'return_url' => $index];
    $this->patch($endpoint, $payload + ['status' => 'read'])->assertRedirect($index)->assertSessionHas('status_replace', ['count' => 1]);
    $this->assertSame('read', $message->fresh()->status);
    $this->assertSame('spam', $spam->fresh()->status);
    $this->assertSame('new', $readOnlyMessage->fresh()->status);
    $this->assertSame('new', $privateMessage->fresh()->status);
    $this->patch($endpoint, $payload + ['status' => 'read'])->assertSessionHas('contact_message_status_unchanged', true);
    $this->patch($endpoint, $payload + ['status' => 'new'])->assertRedirect($index)->assertSessionHas('status_replace', ['count' => 1]);
    $this->assertSame('new', $message->fresh()->status);
    $this->patchJson($endpoint, $payload + ['status' => 'spam'])->assertUnprocessable()->assertJsonValidationErrors('status');
  }
}
