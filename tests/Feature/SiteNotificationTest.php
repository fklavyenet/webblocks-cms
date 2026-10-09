<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Symfony\Component\Mime\Email;
use WebBlocks\Cms\Actions\Sites\UpdateSiteNotifications;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Mail\SiteMessageAlert;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationDispatcher;
use WebBlocks\Cms\Services\SiteNotifications\SiteNotificationMailer;
use WebBlocks\Cms\Support\Contact\ContactMessageNotifier;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\SiteNotifications\SiteNotificationPolicy;
use WebBlocks\Cms\Tests\TestCase;

class SiteNotificationTest extends TestCase
{
  private array $sent = [];

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('app.url', 'https://cms.example.test');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(EnsureInstalled::class);
    Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(6, 0));
    $record = function (Email $mail): void {
    $this->sent[] = clone $mail;
    };
    Mail::extend('notification-test', fn () => new class($record) extends AbstractTransport
    {
      public function __construct(private $record)
      {
      parent::__construct();
      }

      protected function doSend(SentMessage $message): void
      {
      ($this->record)($message->getOriginalMessage());
      }

      public function __toString(): string
      {
      return 'notification-test';
      }
    });
    config(['mail.default' => 'notification-test', 'mail.mailers.notification-test' => ['transport' => 'notification-test'], 'mail.from.address' => 'sender@example.test']);
  }

  private function site(array $settings = []): Site
  {
    return Site::create(['name' => 'Northstar', 'handle' => 'northstar-'.Site::count(), 'timezone' => 'Europe/Berlin', 'notification_settings' => array_replace(SiteNotificationPolicy::DEFAULTS, $settings)]);
  }

  private function message(Site $site, string $recipient = 'operator@example.test'): ContactMessage
  {
    $page = Page::firstOrCreate(['site_id' => $site->id, 'slug' => 'contact'], ['status' => 'published']);

    return ContactMessage::create([
      'page_id' => $page->id, 'name' => 'Sentinel Visitor', 'email' => 'sentinel-visitor@example.test',
      'subject' => 'SentinelSubject', 'message' => 'SentinelBody üğ secret', 'ip_address' => '192.0.2.73',
      'user_agent' => 'SentinelAgent', 'source_url' => 'https://visitor.example.test/?private=SentinelQuery',
      'referer' => 'SentinelReferrer', 'status' => 'new', 'notification_enabled' => true,
      'notification_recipient' => $recipient, 'notification_recipient_source' => 'site', 'notification_status' => 'pending',
    ]);
  }

  private function send(ContactMessage $message): void
  {
    app(ContactMessageNotifier::class)->send($message);
  }

  private function assertPrivate(Email $email): void
  {
    // Scan both raw serialized MIME and decoded parts/headers. Encodings must
    // not hide leaks from a substring-only assertion.
    $all = $email->toString().' '.$email->getHeaders()->toString().' '.$email->getHtmlBody().' '.$email->getTextBody();
    foreach (['Sentinel Visitor', 'sentinel-visitor@example.test', 'SentinelSubject', 'SentinelBody', '192.0.2.73', 'SentinelAgent', 'SentinelQuery', 'SentinelReferrer', 'visitor.example.test'] as $sentinel) {
      $this->assertStringNotContainsString($sentinel, $all);
    }
    $this->assertSame([], $email->getReplyTo());
    $this->assertSame([], $email->getAttachments());
    $this->assertStringContainsString('https://cms.example.test/webadmin/contact-messages', $all);
  }

  #[Test]
  public function alert_only_excludes_visitor_data_from_complete_mime_and_global_reply_to(): void
  {
    $site = $this->site(['notification_frequency' => 'immediate', 'daily_summary' => false]);
    Mail::alwaysReplyTo('sentinel-visitor@example.test', 'Sentinel Visitor');
    $this->send($message = $this->message($site));
    $this->assertSame('sent', $message->refresh()->notification_status);
    $this->assertCount(1, $this->sent);
    $this->assertPrivate($this->sent[0]);
    $this->assertNotEmpty($this->sent[0]->getHtmlBody());
    $this->assertNotEmpty($this->sent[0]->getTextBody());
  }

  #[Test]
  public function custom_headers_are_removed_and_unexpected_attachments_prevent_delivery(): void
  {
    $mail = (new SiteMessageAlert('Northstar', 'https://cms.example.test/webadmin/contact-messages'))
      ->withSymfonyMessage(fn (Email $email) => $email->getHeaders()->addTextHeader('X-Visitor', 'SentinelBody'));
    $mailer = app(SiteNotificationMailer::class);
    $this->assertSame('sent', $mailer->send($mail, 'operator@example.test', true));
    $this->assertPrivate($this->sent[0]);
    $attachment = new SiteMessageAlert('Northstar', 'https://cms.example.test/webadmin/contact-messages');
    $attachment->attachData('SentinelBody', 'visitor.txt');
    $this->assertSame('failed', $mailer->send($attachment, 'operator@example.test', true));
    $this->assertCount(1, $this->sent);
  }

  #[Test]
  public function daily_once_disabled_does_not_repeat_and_duplicate_submissions_and_locks_are_safe(): void
  {
    $site = $this->site(['notification_frequency' => 'daily', 'daily_summary' => false, 'summary_hour' => 9]);
    $message = $this->message($site);
    $this->send($message);
    $this->send($message);
    $this->travel(1)->hours();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(1, $this->sent);
    $this->travel(1)->days();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(1, $this->sent);
    app(UpdateSiteNotifications::class)->handle($site, ['notification_frequency' => 'immediate']);
    $lock = Cache::lock('wbcms-site-notifications:'.$site->id.':contact', 120);
    $this->assertTrue($lock->get());
    $second = $this->message($site);
    $this->send($second);
    $this->assertSame('pending', $second->refresh()->notification_status);
    $lock->release();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(2, $this->sent);
    app(SiteNotificationDispatcher::class)->submit($site, 'contact', $second->id);
    $this->assertCount(2, $this->sent);
  }

  #[Test]
  public function full_retains_subject_body_and_reply_to_and_batch_contents(): void
  {
    $site = $this->site(['notification_mode' => 'full', 'daily_summary' => false]);
    $this->send($this->message($site));
    $this->assertSame('New contact message: SentinelSubject', $this->sent[0]->getSubject());
    $this->assertSame('sentinel-visitor@example.test', $this->sent[0]->getReplyTo()[0]->getAddress());
    $this->send($this->message($site));
    $this->send($this->message($site));
    $this->travel(10)->minutes();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(2, $this->sent);
    $this->assertSame(2, substr_count($this->sent[1]->getHtmlBody(), 'SentinelBody'));
  }

  #[Test]
  public function batches_send_first_immediately_then_once_at_due_time_and_use_current_policy(): void
  {
    $site = $this->site(['notification_mode' => 'full', 'daily_summary' => false]);
    $this->send($this->message($site));
    $this->send($second = $this->message($site));
    $this->send($third = $this->message($site));
    $this->assertSame('pending', $second->refresh()->notification_status);
    $this->assertCount(1, $this->sent);
    app(UpdateSiteNotifications::class)->handle($site, ['notification_mode' => 'alert_only']);
    $this->travel(9)->minutes();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(1, $this->sent);
    $this->travel(1)->minutes();
    app(SiteNotificationDispatcher::class)->run();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(2, $this->sent);
    $this->assertPrivate($this->sent[1]);
    $this->assertStringContainsString('2 new message', $this->sent[1]->getTextBody());
    $this->assertSame('sent', $third->refresh()->notification_status);
  }

  #[Test]
  public function daily_delivery_and_summaries_use_site_timezone_counts_and_one_attempt_per_day(): void
  {
    $site = $this->site(['notification_frequency' => 'daily']);
    $this->send($first = $this->message($site));
    $this->send($this->message($site));
    $first->update(['status' => 'read']);
    app(SiteNotificationDispatcher::class)->run(); // 08:00 Berlin, before 09:00.
    $this->assertCount(0, $this->sent);
    $this->travel(1)->hours();
    app(SiteNotificationDispatcher::class)->run();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(1, $this->sent);
    $this->assertPrivate($this->sent[0]);
    $this->assertStringContainsString('Unread: 1. Awaiting reply: 2.', $this->sent[0]->getTextBody());
    $this->assertSame('sent', $first->refresh()->notification_status);
    $this->travel(1)->days();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(2, $this->sent);
    ContactMessage::query()->update(['status' => 'replied']);
    $this->travel(1)->days();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(2, $this->sent);
  }

  #[Test]
  public function recipients_sites_opt_out_and_spam_are_isolated(): void
  {
    $one = $this->site(['daily_summary' => false]);
    $two = $this->site(['daily_summary' => false]);
    $this->send($this->message($one, 'first@example.test'));
    $this->send($this->message($one, 'second@example.test'));
    $this->send($this->message($two, 'third@example.test'));
    $this->send($spam = $this->message($one, 'first@example.test'));
    $spam->update(['status' => 'spam']);
    $this->send($disabled = $this->message($one, 'first@example.test'));
    $disabled->update(['notification_enabled' => false]);
    $this->travel(10)->minutes();
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(3, $this->sent);
    $this->assertSame('skipped', $spam->refresh()->notification_status);
    $this->assertSame('skipped', $disabled->refresh()->notification_status);
  }

  #[Test]
  public function a_cancelled_transport_is_not_reported_as_sent_and_empty_daily_windows_stay_quiet(): void
  {
    $site = $this->site(['notification_frequency' => 'immediate']);
    $this->travel(1)->hours();
    app(SiteNotificationDispatcher::class)->run(); // Today's empty summary window.
    $this->send($this->message($site));
    app(SiteNotificationDispatcher::class)->run();
    $this->assertCount(1, $this->sent);
    Event::listen(MessageSending::class, fn () => false);
    $this->send($cancelled = $this->message($site));
    $this->assertSame('skipped', $cancelled->refresh()->notification_status);
    $this->assertCount(1, $this->sent);
  }

  #[Test]
  public function failures_are_recorded_without_payloads_and_are_not_retried(): void
  {
    Mail::extend('notification-failure', fn () => new class extends AbstractTransport
    {
      protected function doSend(SentMessage $message): void
      {
      throw new \RuntimeException('SentinelBody SMTP synthetic-secret');
      }

      public function __toString(): string
      {
      return 'notification-failure';
      }
    });
    config(['mail.default' => 'notification-failure', 'mail.mailers.notification-failure.transport' => 'notification-failure']);
    $site = $this->site(['notification_frequency' => 'immediate', 'daily_summary' => false]);
    $this->send($message = $this->message($site));
    app(SiteNotificationDispatcher::class)->run();
    $this->assertSame('failed', $message->refresh()->notification_status);
    $this->assertStringNotContainsString('synthetic-secret', $message->notification_error);
    $this->assertSame('failed', DB::table('wbcms_site_notification_events')->value('status'));
  }

  #[Test]
  public function defaults_preserve_legacy_upgrade_and_invalid_modes_fail_closed(): void
  {
    $site = $this->site();
    $this->assertSame(SiteNotificationPolicy::DEFAULTS, $site->notification_settings);
    Schema::table('wbcms_sites', fn ($table) => $table->dropColumn('notification_settings'));
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_09_090000_add_site_notification_policy.php')->up();
    $this->assertSame(SiteNotificationPolicy::LEGACY, $site->refresh()->notification_settings);
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_09_090000_add_site_notification_policy.php')->up();
    $site->forceFill(['notification_settings' => ['notification_mode' => 'broken']])->save();
    $this->assertSame('alert_only', SiteNotificationPolicy::forSite($site)['notification_mode']);
  }

  #[Test]
  public function site_settings_render_the_policy_and_delivery_outcomes_without_editing_read_only_sites(): void
  {
    $site = $this->site(['notification_frequency' => 'immediate', 'daily_summary' => false]);
    $this->send($this->message($site));
    $this->view('webblocks-cms::admin.sites.partials.notifications', ['site' => $site, 'isReadOnly' => true])
      ->assertSee('notification_settings[notification_mode]', false)
      ->assertSee('notification_settings[notification_frequency]', false)
      ->assertSee('disabled', false)
      ->assertSee('Sent to transport');
  }

  #[Test]
  public function the_alert_link_requires_a_normal_authenticated_admin_session(): void
  {
    $this->getJson('/webadmin/contact-messages')->assertUnauthorized();
    $route = Route::getRoutes()->getByName('admin.contact-messages.index');
    $this->assertContains('admin.access', $route->gatherMiddleware());
    $this->assertContains('can:manage-site-operations', $route->gatherMiddleware());
  }

  #[Test]
  public function notification_api_requires_capability_site_scope_and_valid_values(): void
  {
    $site = $this->site();
    $other = $this->site();
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic settings', capabilities: ['content.read', 'site-settings.write'], allowedSiteIds: [$site->id])->plainToken;
    $this->withToken($token)->getJson('/webadmin/api/sites/'.$site->id.'/notifications')->assertOk()->assertJsonPath('notification_settings.notification_mode', 'alert_only');
    $this->getJson('/webadmin/api/sites/'.$other->id.'/notifications')->assertForbidden();
    $this->patchJson('/webadmin/api/sites/'.$other->id.'/notifications', ['notification_mode' => 'full'])->assertForbidden();
    $this->patchJson('/webadmin/api/sites/'.$site->id.'/notifications', ['notification_mode' => 'bad'])->assertUnprocessable();
    $this->patchJson('/webadmin/api/sites/'.$site->id.'/notifications', ['notification_mode' => null])->assertUnprocessable();
    $this->patchJson('/webadmin/api/sites/'.$site->id.'/notifications', ['notification_mode' => 'full', 'visitor_email' => 'x@example.test'])->assertUnprocessable();
    $this->patchJson('/webadmin/api/sites/'.$site->id.'/notifications', ['notification_mode' => 'full'])->assertOk()->assertJsonPath('notification_settings.notification_mode', 'full')->assertJsonPath('notification_settings.batch_minutes', 10);
    $readOnly = app(CmsApiTokenIssuer::class)->issue('Synthetic read', capabilities: ['content.read'])->plainToken;
    $this->withToken($readOnly)->patchJson('/webadmin/api/sites/'.$site->id.'/notifications', ['notification_mode' => 'alert_only'])->assertForbidden();
    $this->assertContains('internal-api.capability:site-settings.write', Route::getRoutes()->getByName('internal-content-api.sites.notifications.update')->gatherMiddleware());
  }
}
