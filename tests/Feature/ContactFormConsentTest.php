<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Http\Controllers\Public\ContactMessageController;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Http\Requests\ContactMessageRequest;
use WebBlocks\Cms\Mail\ContactMessageNotification;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactMessageNotifier;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The native contact form stored submissions but offered no way to record that
 * the visitor agreed to that storage, and the contract asserted completeness
 * with known_gaps: []. A site needing the agreement had to demote the wording
 * into the form's intro prose — text beside the form, not a fact attached to
 * the submission, and therefore not provable afterwards.
 *
 * settings.consent_required is the shared policy; consent_label is translated,
 * because the wording is the notice. An accepted submission stores the time and
 * a copy of the wording, so editing the block later cannot change what a past
 * visitor is recorded as having agreed to.
 */
class ContactFormConsentTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function hidden_fields_are_ignored_and_a_nameless_message_can_still_be_answered(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'I agree.');
    $this->assertStringContainsString('name="name"', $this->renderPage($block));
    $this->assertStringContainsString('name="subject"', $this->renderPage($block));
    $block->update(['settings' => array_replace($block->decodedSettings(), ['show_name' => false, 'show_subject' => false])]);

    $html = $this->renderPage($block->fresh());
    $this->assertStringNotContainsString('name="name"', $html);
    $this->assertStringNotContainsString('name="subject"', $html);
    $this->assertMatchesRegularExpression('/<input[^>]*name="email"[^>]*required/', $html);
    $this->assertMatchesRegularExpression('/<textarea[^>]*name="message"[^>]*required/', $html);
    $this->assertStringContainsString('name="consent"', $html);

    $payload = $this->submissionPayload($block, '1', 'Please reply to this email.', '/about');
    $invalid = ContactMessageRequest::create('/contact-messages', 'POST', array_diff_key($payload, array_flip(['email', 'message'])));
    $invalid->setContainer($this->app)->setRedirector(app('redirect'));
    try {
      $invalid->validateResolved();
      $this->fail('Email and message are still required.');
    } catch (ValidationException $exception) {
      $this->assertSame(['email', 'message'], array_keys($exception->errors()));
    }

    $request = ContactMessageRequest::create('/contact-messages', 'POST', array_replace($payload, ['name' => ['forged'], 'subject' => 'Forged subject']));
    $request->setContainer($this->app)->setRedirector(app('redirect'));
    $request->validateResolved();
    app(ContactMessageController::class)->store($request);

    $message = ContactMessage::sole();
    $this->assertNull($message->name);
    $this->assertNull($message->subject);
    $this->assertSame('I agree.', $message->consent_label);
    $this->assertSame('visitor@example.com', (new ContactMessageNotification($message))->envelope()->replyTo[0]->address);
  }

  #[Test]
  public function the_checkbox_renders_with_the_translated_wording(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'I agree to the data-processing notice.');

    $html = $this->renderPage($block);

    $this->assertStringContainsString('name="consent"', $html);
    $this->assertStringContainsString('I agree to the data-processing notice.', $html);
  }

  #[Test]
  public function a_required_consent_with_no_wording_disables_submission_and_explains_why(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: null);
    $html = $this->renderPage($block);

    $this->assertStringNotContainsString('name="consent"', $html);
    $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*disabled/', $html);
    $this->assertStringContainsString(e(app(CmsTranslator::class)->get('validation.contact_form.consent_unavailable', 'en')), $html);
  }

  #[Test]
  public function a_form_that_does_not_require_consent_is_unchanged(): void
  {
    $block = $this->seedForm(consentRequired: false, consentLabel: 'Unused wording.');

    $html = $this->renderPage($block);
    $this->assertStringNotContainsString('name="consent"', $html);
    $this->assertDoesNotMatchRegularExpression('/<button[^>]*type="submit"[^>]*disabled/', $html);
  }

  #[Test]
  #[DataProvider('missingNotices')]
  public function required_consent_with_missing_wording_refuses_even_forged_acceptance_without_storage_or_notification(?string $notice, ?string $consent): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: $notice);
    $block->settings = [...$block->decodedSettings(), 'send_email_notification' => true];
    $block->save();
    $this->mock(ContactMessageNotifier::class, fn ($mock) => $mock->shouldNotReceive('send'));

    try {
      $this->submit($block, consent: $consent);
      $this->fail('Missing required wording must prevent submission, regardless of the checkbox value.');
    } catch (ValidationException $exception) {
      $this->assertSame([
        'consent' => [app(CmsTranslator::class)->get('validation.contact_form.consent_unavailable', 'en')],
      ], $exception->errors());
    }

    $this->assertSame(0, ContactMessage::query()->count());
  }

  public static function missingNotices(): iterable
  {
    foreach (['missing' => null, 'empty' => '', 'whitespace' => " \n\t "] as $name => $notice) {
      foreach (['omitted' => null, 'unchecked' => '0', 'forged' => '1'] as $state => $consent) {
        yield $name.' '.$state => [$notice, $consent];
      }
    }
  }

  #[Test]
  public function a_blank_translated_notice_does_not_silently_disable_the_shared_requirement(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'English notice.');
    $locale = Locale::create(['code' => 'de', 'name' => 'Deutsch', 'is_default' => false, 'is_enabled' => true]);
    $block->page->site->locales()->attach($locale, ['is_enabled' => true]);
    $block->page->translations()->create(['locale_id' => $locale->id, 'name' => 'Kontakt', 'slug' => 'kontakt', 'path' => '/kontakt']);
    $block->contactFormTranslations()->create(['locale_id' => $locale->id, 'title' => 'Kontakt', 'consent_label' => '  ']);
    $noticeError = app(CmsTranslator::class)->get('validation.contact_form.consent_unavailable', 'de');
    $html = $this->renderPage($block, 'de');
    $this->assertStringContainsString(e($noticeError), $html);
    $this->assertMatchesRegularExpression('/<button[^>]*type="submit"[^>]*disabled/', $html);

    try {
      $this->submit($block, consent: '1', sourceUrl: '/de/kontakt');
      $this->fail('The English notice must not replace a blank notice in an existing German translation.');
    } catch (ValidationException $exception) {
      $this->assertSame(['consent' => [$noticeError]], $exception->errors());
    }
    $this->assertSame(0, ContactMessage::query()->count());

    $block->contactFormTranslations()->where('locale_id', $locale->id)->update(['consent_label' => 'Ich stimme zu.']);
    $this->assertDoesNotMatchRegularExpression('/<button[^>]*type="submit"[^>]*disabled/', $this->renderPage($block, 'de'));
    $this->submit($block, consent: '1', sourceUrl: '/de/kontakt');
    $this->assertSame('Ich stimme zu.', ContactMessage::query()->sole()->consent_label);
    $this->assertNotNull(ContactMessage::query()->sole()->consent_accepted_at);
  }

  #[Test]
  public function unavailable_notice_feedback_is_translated_in_every_supported_locale(): void
  {
    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $this->assertTrue(app('translator')->hasForLocale('webblocks-cms::validation.contact_form.consent_unavailable', $locale));
    }
  }

  #[Test]
  public function a_submission_without_consent_is_refused_even_though_the_client_can_drop_the_field(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'I agree.');

    $this->expectException(ValidationException::class);

    $this->submit($block, consent: null);
  }

  #[Test]
  public function an_accepted_submission_records_the_time_and_a_copy_of_the_wording(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'I agree to the data-processing notice.');

    $this->submit($block, consent: '1');

    $message = ContactMessage::query()->latest('id')->firstOrFail();

    $this->assertNotNull($message->consent_accepted_at);
    $this->assertSame('I agree to the data-processing notice.', $message->consent_label);
  }

  #[Test]
  public function editing_the_block_wording_does_not_rewrite_an_existing_record(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'Original wording.');

    $this->submit($block, consent: '1');

    $block->contactFormTranslations()->update(['consent_label' => 'Rewritten wording.']);

    $this->assertSame('Original wording.', ContactMessage::query()->latest('id')->firstOrFail()->consent_label);
  }

  #[Test]
  public function a_form_without_consent_stores_no_consent_record(): void
  {
    $block = $this->seedForm(consentRequired: false, consentLabel: null);
    $block->settings = [...$block->decodedSettings(), 'privacy_notice_url' => '/privacy'];
    $block->save();

    $this->submit($block, consent: null);

    $message = ContactMessage::query()->latest('id')->firstOrFail();

    $this->assertNull($message->consent_accepted_at);
    $this->assertNull($message->consent_label);
  }

  #[Test]
  public function suspicious_contact_messages_are_quarantined_without_notification(): void
  {
    $block = $this->seedForm(consentRequired: false, consentLabel: null);

    $this->submit($block, consent: null, message: 'We noticed your website and offer digital marketing services.');

    $message = ContactMessage::query()->latest('id')->firstOrFail();

    $this->assertSame('quarantined', $message->status);
    $this->assertSame('skipped', $message->notification_status);
    $this->assertSame(50, $message->spam_score);
  }

  #[Test]
  public function submission_returns_to_escaped_confirmation_below_only_the_submitted_forms_button(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    config()->set('cms.multisite.unknown_host_fallback', true);
    $block = $this->seedForm(consentRequired: false, consentLabel: null);
    $block->replicate()->fill(['sort_order' => 1])->save();
    $locale = Locale::create(['code' => 'de', 'name' => 'Deutsch', 'is_default' => false, 'is_enabled' => true]);
    $block->page->site->locales()->attach($locale, ['is_enabled' => true]);
    $block->page->translations()->create(['locale_id' => $locale->id, 'name' => 'Kontakt', 'slug' => 'kontakt', 'path' => '/kontakt']);
    $block->contactFormTranslations()->create(['locale_id' => $locale->id, 'success_message' => 'Empfangen <strong>als Text</strong>']);
    $url = '/de/kontakt?from=footer';
    $anchor = 'contact-form-success-'.$block->id;

    $this->post('/contact-messages', $this->submissionPayload($block, null, 'Hello there.', $url))
      ->assertRedirect($url.'#'.$anchor);
    $html = $this->get($url)->assertOk()->getContent();
    $dom = new \DOMDocument;
    @$dom->loadHTML($html);
    $xpath = new \DOMXPath($dom);
    $alerts = $xpath->query('//div[contains(@class,"wb-alert-success") and @role="status"]');
    $this->assertCount(1, $alerts);
    $alert = $alerts->item(0);
    $this->assertSame($anchor, $alert->getAttribute('id'));
    $this->assertSame(1, $xpath->query('ancestor::section[@id="contact-form-'.$block->id.'"]', $alert)->length);
    $this->assertSame(1, $xpath->query('preceding-sibling::*[1]/button[@type="submit"]', $alert)->length);
    $this->assertStringContainsString('Nachricht gesendet', $alert->textContent);
    $this->assertStringContainsString('Empfangen <strong>als Text</strong>', $alert->textContent);
    $this->assertSame(0, $xpath->query('.//strong[text()="als Text"]', $alert)->length);
    $this->get($url)->assertOk()->assertDontSee($anchor);
  }

  private function submit(Block $block, ?string $consent, string $message = 'Hello there.', string $sourceUrl = '/about'): void
  {
    $request = ContactMessageRequest::create('/contact-messages', 'POST', $this->submissionPayload($block, $consent, $message, $sourceUrl));
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $this->app->make(ContactMessageController::class)->store($request);
  }

  private function submissionPayload(Block $block, ?string $consent, string $message, string $sourceUrl): array
  {
    config()->set('contact.minimum_submit_seconds', 0);
    $check = app(ContactFormCheck::class);
    $checkField = $check->fieldName($block);
    $payload = [
      'block_id' => (string) $block->id,
      'page_id' => (string) $block->page_id,
      'name' => 'Visitor',
      'email' => 'visitor@example.com',
      'message' => $message,
      'source_url' => $sourceUrl,
      '_form_stamp' => $check->issueStamp($block),
      '_form_check_name' => $check->signedFieldName($block),
      $checkField => '',
    ];

    if ($consent !== null) {
      $payload['consent'] = $consent;
    }

    return $payload;
  }

  private function renderPage(Block $block, string $locale = 'en'): string
  {
    $page = $block->page;

    // The form template reads $errors, which the web middleware group normally
    // shares from the session; the render endpoint runs outside it.
    view()->share('errors', new ViewErrorBag);

    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['format' => 'html', 'locale' => $locale]);

    return (string) $this->app->make(InternalPageRenderController::class)->show($request, $page)->getContent();
  }

  private function seedForm(bool $consentRequired, ?string $consentLabel): Block
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    $locale = Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'about', 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->create(['locale_id' => $locale->id, 'name' => 'About', 'slug' => 'about', 'path' => '/about']);

    $slotType = SlotType::query()->firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    $block = Block::create([
      'page_id' => $page->id,
      'type' => 'contact_form',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'settings' => json_encode([
        'store_submissions' => true,
        'send_email_notification' => false,
        'consent_required' => $consentRequired,
      ]),
    ]);

    $block->contactFormTranslations()->create([
      'locale_id' => $locale->id,
      'title' => 'Contact',
      'submit_label' => 'Send message',
      'success_message' => 'Thanks.',
      'consent_label' => $consentLabel,
    ]);

    return $block->fresh(['page']);
  }
}
