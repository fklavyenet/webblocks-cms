<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Http\Controllers\Public\ContactMessageController;
use WebBlocks\Cms\Http\Requests\ContactMessageRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
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
  public function the_checkbox_renders_with_the_translated_wording(): void
  {
    $block = $this->seedForm(consentRequired: true, consentLabel: 'I agree to the data-processing notice.');

    $html = $this->renderPage($block);

    $this->assertStringContainsString('name="consent"', $html);
    $this->assertStringContainsString('I agree to the data-processing notice.', $html);
  }

  #[Test]
  public function a_required_consent_with_no_wording_renders_no_checkbox(): void
  {
    // A required checkbox with no label would be a consent record with nothing
    // recorded against it, so the missing wording disables the field entirely.
    $block = $this->seedForm(consentRequired: true, consentLabel: null);

    $this->assertStringNotContainsString('name="consent"', $this->renderPage($block));
  }

  #[Test]
  public function a_form_that_does_not_require_consent_is_unchanged(): void
  {
    $block = $this->seedForm(consentRequired: false, consentLabel: 'Unused wording.');

    $this->assertStringNotContainsString('name="consent"', $this->renderPage($block));
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

    $this->submit($block, consent: null);

    $message = ContactMessage::query()->latest('id')->firstOrFail();

    $this->assertNull($message->consent_accepted_at);
    $this->assertNull($message->consent_label);
  }

  private function submit(Block $block, ?string $consent): void
  {
    $payload = [
      'block_id' => (string) $block->id,
      'page_id' => (string) $block->page_id,
      'name' => 'Visitor',
      'email' => 'visitor@example.com',
      'message' => 'Hello there.',
      'source_url' => '/about',
      // The controller drops anything submitted faster than the minimum, which
      // would short-circuit before the record is written.
      'submitted_at' => (string) (now()->timestamp - 60),
    ];

    if ($consent !== null) {
      $payload['consent'] = $consent;
    }

    $request = ContactMessageRequest::create('/contact-messages', 'POST', $payload);
    $request->setContainer($this->app)->setRedirector($this->app->make('redirect'));
    $request->validateResolved();

    $this->app->make(ContactMessageController::class)->store($request);
  }

  private function renderPage(Block $block): string
  {
    $page = $block->page;

    // The form template reads $errors, which the web middleware group normally
    // shares from the session; the render endpoint runs outside it.
    view()->share('errors', new ViewErrorBag);

    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['format' => 'html']);

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
