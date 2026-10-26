<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Tests\TestCase;

class ContactMessageDetailTest extends TestCase
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

  #[Test]
  public function readers_can_reply_without_exposing_diagnostics_or_changing_message_status(): void
  {
    $message = $this->message();
    $message->subject = 'A&B? <script>alert(1)</script>';
    $message->message = "First line\n<script>alert(2)</script>";
    $html = $this->render($message);
    $dom = $this->dom($html);

    $this->assertSame(1, $dom->query('//a[starts-with(@href, "mailto:visitor@example.test?subject=")]')->length);
    $link = $dom->query('//a[starts-with(@href, "mailto:visitor@example.test?subject=")]')->item(0);
    $this->assertSame('mailto:visitor@example.test?subject='.rawurlencode('Re: '.$message->subject), $link->getAttribute('href'));
    $this->assertSame('read', $message->status);
    $this->assertStringContainsString(e($message->message), $html);
    $this->assertStringNotContainsString('<script>alert(', $html);
    $rows = $dom->query('//table[contains(@class, "wb-table-striped") and not(ancestor::details)]/tbody/tr');
    $this->assertSame(4, $rows->length);
    foreach (['Test Visitor', 'visitor@example.test', $message->subject, $message->message] as $index => $value) {
      $this->assertSame($value, $dom->query('./td', $rows->item($index))->item(0)->textContent);
    }
    $this->assertSame(0, $dom->query('//details[@open]')->length);
    foreach (['203.0.113.7', 'Test browser', '0'] as $detail) {
      $this->assertStringContainsString($detail, $dom->query('//details')->item(0)->textContent);
    }
    $this->assertSame(1, $dom->query('//details//a[@href="https://example.test/contact"]')->length);
    $this->assertStringNotContainsString('php artisan', $html);
    $this->assertSame(1, $dom->query('//*[@id="delete-contact-message-modal"]//form[input[@name="_method" and @value="DELETE"]]')->length);
  }

  #[Test]
  public function every_other_status_remains_available_once_and_the_current_status_is_not_an_action(): void
  {
    foreach (ContactMessage::statuses() as $current) {
      $message = $this->message();
      $message->status = $current;
      $dom = $this->dom($this->render($message));
      foreach (ContactMessage::statuses() as $status) {
        $this->assertSame($current === $status ? 0 : 1, $dom->query('//form/input[@name="status" and @value="'.$status.'"]')->length, $current.' -> '.$status);
      }
    }
  }

  #[Test]
  public function notification_problems_and_suspicious_messages_are_visible_outside_closed_details(): void
  {
    $message = $this->message();
    $translator = app(CmsTranslator::class);
    foreach (['sent', 'pending', 'skipped', 'failed', 'not_configured'] as $notification) {
      $message->notification_status = $notification;
      $dom = $this->dom($this->render($message));
      $warnings = $dom->query('//div[contains(@class, "wb-alert-warning") and not(ancestor::details)]');
      $this->assertSame(in_array($notification, ['failed', 'not_configured'], true) ? 1 : 0, $warnings->length, $notification);
    }
    $message->notification_status = 'sent';
    foreach (['spam', 'quarantined'] as $status) {
      $message->status = $status;
      $dom = $this->dom($this->render($message));
      $this->assertStringContainsString($translator->admin('contact_messages_show.review_warning', 'en'), $dom->query('//div[contains(@class, "wb-alert-warning")]')->item(0)->textContent);
    }
  }

  #[Test]
  public function consent_details_show_the_recorded_timestamp_and_original_text_as_escaped_plain_text(): void
  {
    $message = $this->message();
    $message->consent_accepted_at = '2026-10-26 08:15:30';
    $message->consent_label = "Original notice.\n<script>alert(1)</script>";
    $html = $this->render($message);
    $dom = $this->dom($html);
    $rows = $dom->query('//details//section[@aria-labelledby="contact-consent-heading"]//tbody/tr');

    $this->assertSame(2, $rows->length);
    $this->assertSame('2026-10-26 08:15:30', $dom->query('./td', $rows->item(0))->item(0)->textContent);
    $this->assertSame($message->consent_label, $dom->query('./td', $rows->item(1))->item(0)->textContent);
    $this->assertStringContainsString(e($message->consent_label), $html);
    $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
    $this->assertSame(0, $dom->query('//details[@open]')->length);
  }

  #[Test]
  #[DataProvider('incompleteConsentRecords')]
  public function missing_consent_values_are_explicit_without_inventing_historical_acceptance(?string $acceptedAt, ?string $notice): void
  {
    $message = $this->message();
    $message->consent_accepted_at = $acceptedAt;
    $message->consent_label = $notice;
    $original = $message->getAttributes();
    $dom = $this->dom($this->render($message));
    $cells = $dom->query('//section[@aria-labelledby="contact-consent-heading"]//tbody/tr/td');
    $missing = app(CmsTranslator::class)->admin('contact_messages_show.consent_not_recorded', 'en');

    $this->assertSame($acceptedAt ?? $missing, $cells->item(0)->textContent);
    $this->assertSame(filled($notice) ? $notice : $missing, $cells->item(1)->textContent);
    $this->assertSame($original, $message->getAttributes());
  }

  public static function incompleteConsentRecords(): iterable
  {
    yield 'legacy' => [null, null];
    yield 'empty notice' => [null, ''];
    yield 'whitespace notice' => [null, '  '];
    yield 'timestamp only' => ['2026-10-26 08:15:30', null];
    yield 'notice only' => [null, 'Historical wording.'];
  }

  #[Test]
  public function the_detail_actions_and_empty_subject_are_localized_in_every_admin_locale(): void
  {
    $message = $this->message();
    $message->subject = null;
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $this->app['config']->set('app.locale', $locale);
      $html = $this->render($message);
      foreach (['title', 'empty_value', 'visitor_message', 'reply_by_email', 'mark_replied', 'mark_archived', 'more_actions', 'consent_record', 'consent_accepted_at_label', 'consent_notice_label', 'consent_not_recorded'] as $key) {
        $this->assertTrue(app('translator')->hasForLocale('webblocks-cms::admin.contact_messages_show.'.$key, $locale));
        $this->assertStringContainsString(e(app(CmsTranslator::class)->admin('contact_messages_show.'.$key, $locale, ['name' => $message->name])), $html);
      }
    }
  }

  private function message(): ContactMessage
  {
    $message = new ContactMessage([
      'name' => 'Test Visitor', 'email' => 'visitor@example.test', 'subject' => 'Question',
      'message' => 'Can you help?', 'status' => 'read', 'notification_status' => 'sent',
      'ip_address' => '203.0.113.7', 'user_agent' => 'Test browser', 'source_url' => 'https://example.test/contact',
    ]);
    $message->id = 5;
    $message->created_at = '2026-10-18 12:00:00';
    $message->setRelation('page', null);
    $message->setRelation('block', null);

    return $message;
  }

  private function render(ContactMessage $message): string
  {
    $this->app['router']->get('/test-login', fn () => '')->name('webblocks.auth.login');
    view()->share('errors', new ViewErrorBag);

    return view('webblocks-cms::admin.contact-messages.show', [
      'message' => $message, 'pageTitle' => $message->detailPageTitle(), 'statuses' => ContactMessage::statuses(),
    ])->render();
  }

  private function dom(string $html): DOMXPath
  {
    $document = new DOMDocument;
    $previous = libxml_use_internal_errors(true);
    $document->loadHTML('<?xml encoding="UTF-8">'.$html);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    return new DOMXPath($document);
  }
}
