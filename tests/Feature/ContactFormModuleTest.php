<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Mail\ContactMessageNotification as PackageContactMessageNotification;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;

class ContactFormModuleTest extends TestCase
{
    use RefreshDatabase;

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function contactBlockType(): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => 'contact_form'],
            [
                'name' => 'Contact Form',
                'category' => 'form',
                'source_type' => 'form',
                'is_system' => false,
                'is_container' => false,
                'sort_order' => 31,
                'status' => 'published',
            ],
        );
    }

    private function createContactFormPage(): array
    {
        $slotType = $this->slotType();
        $blockType = $this->contactBlockType();
        $site = $this->defaultSite();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $blockType->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Contact us',
            'content' => 'Send a message to the editorial team.',
            'settings' => json_encode([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->contactFormTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact us',
            'content' => 'Send a message to the editorial team.',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message. We will get back to you soon.',
        ]);

        return [$page, $block];
    }

    private function updateContactFormSettings(Block $block, array $settings): void
    {
        $block->update([
            'settings' => json_encode(array_merge([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], $settings), JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function submissionPayload(Block $block, ?array $overrides = []): array
    {
        return array_merge([
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'source_url' => route('pages.show', $block->page?->slug ?? 'contact', false),
            'submitted_at' => now()->subSeconds(5)->timestamp,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Partnership request',
            'message' => 'We would like to discuss a new project.',
            'website' => '',
        ], $overrides ?? []);
    }

    #[Test]
    public function contact_form_submission_stores_message_in_database(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block));

        $response->assertRedirect(route('pages.show', ['slug' => 'contact'], false).'#contact-form-'.$block->id);
        $response->assertSessionHas('contact_form_success_block_id', $block->id);
        $this->assertDatabaseHas('contact_messages', [
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'email' => 'taylor@example.com',
            'status' => 'new',
        ]);
    }

    #[Test]
    public function contact_form_success_flash_renders_after_submission_redirect(): void
    {
        Mail::fake();
        [$page, $block] = $this->createContactFormPage();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect(route('pages.show', $page->slug, false).'#contact-form-'.$block->id);

        $this->withSession(['contact_form_success_block_id' => $block->id])
            ->get(route('pages.show', $page->slug, false))
            ->assertOk()
            ->assertSee('Message sent')
            ->assertSee('Thanks for your message. We will get back to you soon.')
            ->assertSee('data-wb-contact-success-dismiss', false)
            ->assertSee('data-wb-contact-success-dismiss-delay="7000"', false)
            ->assertSee('cms/js/public/contact-form.js', false);
    }

    #[Test]
    public function contact_form_validation_errors_redirect_back_to_form_anchor(): void
    {
        [, $block] = $this->createContactFormPage();

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'name' => '',
            'message' => '',
        ]));

        $response->assertRedirect(route('pages.show', 'contact', false).'#contact-form-'.$block->id);
        $response->assertSessionHasErrors(['name', 'message']);

        $viewErrors = new \Illuminate\Support\ViewErrorBag;
        $viewErrors->put('default', new \Illuminate\Support\MessageBag([
            'name' => ['The name field is required.'],
        ]));

        session()->flashInput(['block_id' => (string) $block->id]);

        $html = view('webblocks-cms::pages.partials.blocks.contact_form', [
            'block' => $block->fresh(),
            'page' => $block->page,
            'errors' => $viewErrors,
        ])->render();

        $this->assertStringContainsString('Please review the form', $html);
        $this->assertStringNotContainsString('data-wb-contact-success-dismiss', $html);
    }

    #[Test]
    public function contact_form_submission_does_not_leave_browser_on_post_endpoint(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block));

        $response->assertRedirect(route('pages.show', 'contact', false).'#contact-form-'.$block->id);
        $this->assertNotSame('/contact-messages', parse_url((string) $response->headers->get('Location'), PHP_URL_PATH));
    }

    #[Test]
    public function contact_form_can_be_created_without_explicit_store_submissions_input(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $slotType = $this->slotType();
        $blockType = $this->contactBlockType();
        $site = $this->defaultSite();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);
        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
            ['site_id' => $site->id, 'name' => 'Contact', 'slug' => 'contact', 'path' => '/p/contact'],
        );

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'block_type_id' => $blockType->id,
            'sort_order' => 0,
            'heading' => 'Contact us',
            'intro_text' => 'Send a message to the editorial team.',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message. We will get back to you soon.',
            'recipient_email' => 'team@example.com',
            'send_email_notification' => '1',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $block = Block::query()->where('page_id', $page->id)->where('type', 'contact_form')->firstOrFail();
        $settings = json_decode((string) $block->getRawOriginal('settings'), true);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));
        $this->assertSame('team@example.com', $settings['recipient_email'] ?? null);
        $this->assertTrue((bool) ($settings['send_email_notification'] ?? false));
        $this->assertTrue((bool) ($settings['store_submissions'] ?? false));
        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact us',
            'content' => 'Send a message to the editorial team.',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message. We will get back to you soon.',
        ]);
    }

    #[Test]
    public function contact_form_can_be_edited_without_explicit_store_submissions_input(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $block] = $this->createContactFormPage();
        $slotType = $this->slotType();

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'block_type_id' => $block->block_type_id,
            'sort_order' => 0,
            'heading' => 'Contact the editorial team',
            'intro_text' => 'Use this form to reach the editorial team.',
            'submit_label' => 'Send update',
            'success_message' => 'Thanks, we received your update.',
            'recipient_email' => 'hello@example.com',
            'send_email_notification' => '0',
            'status' => 'published',
        ]);

        $settings = json_decode((string) $block->fresh()->getRawOriginal('settings'), true);

        $response->assertRedirect();
        $response->assertSessionDoesntHaveErrors(['store_submissions']);
        $this->assertSame('hello@example.com', $settings['recipient_email'] ?? null);
        $this->assertFalse((bool) ($settings['send_email_notification'] ?? true));
        $this->assertTrue((bool) ($settings['store_submissions'] ?? false));
        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact the editorial team',
            'content' => 'Use this form to reach the editorial team.',
            'submit_label' => 'Send update',
            'success_message' => 'Thanks, we received your update.',
        ]);
    }

    #[Test]
    public function mail_notification_is_attempted_after_persistence(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block));

        Mail::assertSent(PackageContactMessageNotification::class, function (PackageContactMessageNotification $mail) use ($block): bool {
            return $mail->contactMessage->block_id === $block->id
                && $mail->hasTo('team@example.com');
        });
    }

    #[Test]
    public function notification_email_renders_visitor_source_and_technical_sections(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['display_name' => 'F Klavye']);

        $this->withServerVariables([
            'HTTP_USER_AGENT' => 'FeatureTest Browser',
            'REMOTE_ADDR' => '203.0.113.10',
        ])->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'name' => 'Ada Visitor',
            'email' => 'ada@example.com',
            'subject' => 'Keyboard request',
            'message' => 'Please send more details.',
        ]))->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();
        $html = (new PackageContactMessageNotification($message))->render();

        $this->assertStringContainsString('New contact message', $html);
        $this->assertStringContainsString('Visitor message', $html);
        $this->assertStringContainsString('Ada Visitor', $html);
        $this->assertStringContainsString('ada@example.com', $html);
        $this->assertStringContainsString('Keyboard request', $html);
        $this->assertStringContainsString('Please send more details.', $html);
        $this->assertStringContainsString('Submission details', $html);
        $this->assertStringContainsString('F Klavye', $html);
        $this->assertStringContainsString('Contact', $html);
        $this->assertStringContainsString('/p/contact', $html);
        $this->assertStringContainsString('Technical details', $html);
        $this->assertStringContainsString('203.0.113.10', $html);
        $this->assertStringContainsString('FeatureTest Browser', $html);
        $this->assertStringContainsString((string) $block->id, $html);
    }

    #[Test]
    public function notification_email_escapes_visitor_content_and_preserves_message_line_breaks(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'name' => '<strong>Taylor</strong>',
            'subject' => '<script>alert("subject")</script>',
            'message' => "First <b>line</b>\nSecond line",
        ]))->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();
        $html = (new PackageContactMessageNotification($message))->render();

        $this->assertStringContainsString('&lt;strong&gt;Taylor&lt;/strong&gt;', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&quot;subject&quot;)&lt;/script&gt;', $html);
        $this->assertStringContainsString('First &lt;b&gt;line&lt;/b&gt;<br', $html);
        $this->assertStringContainsString('Second line', $html);
        $this->assertStringNotContainsString('<strong>Taylor</strong>', $html);
        $this->assertStringNotContainsString('<script>alert("subject")</script>', $html);
        $this->assertStringNotContainsString('First <b>line</b>', $html);
    }

    #[Test]
    public function contact_form_notification_uses_block_recipient_before_site_default(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => 'site@example.com']);
        $this->updateContactFormSettings($block, ['recipient_email' => 'block@example.com']);

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('block@example.com', $message->notification_recipient);
        $this->assertNotNull($message->notification_sent_at);
        $this->assertNull($message->notification_error);
        Mail::assertSent(PackageContactMessageNotification::class, fn (PackageContactMessageNotification $mail): bool => $mail->hasTo('block@example.com'));
    }

    #[Test]
    public function contact_form_notification_uses_site_default_when_block_recipient_is_empty(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => 'site@example.com']);
        $this->updateContactFormSettings($block, ['recipient_email' => null]);

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('site@example.com', $message->notification_recipient);
        $this->assertNotNull($message->notification_sent_at);
        $this->assertNull($message->notification_error);
        Mail::assertSent(PackageContactMessageNotification::class, fn (PackageContactMessageNotification $mail): bool => $mail->hasTo('site@example.com'));
    }

    #[Test]
    public function contact_form_notification_falls_back_to_contact_recipient_config_when_site_and_block_are_empty(): void
    {
        config()->set('contact.recipient_email', 'config@example.com');
        config()->set('mail.from.address', 'from@example.com');
        Mail::fake();
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => null]);
        $this->updateContactFormSettings($block, ['recipient_email' => null]);

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('config@example.com', $message->notification_recipient);
        $this->assertNotNull($message->notification_sent_at);
        $this->assertNull($message->notification_error);
        Mail::assertSent(PackageContactMessageNotification::class, fn (PackageContactMessageNotification $mail): bool => $mail->hasTo('config@example.com'));
    }

    #[Test]
    public function submission_is_stored_even_when_mail_fails(): void
    {
        [, $block] = $this->createContactFormPage();

        Mail::shouldReceive('to')->once()->with('team@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable for password=secret token=abcdef1234567890abcdef123456'));

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertSame('SMTP unavailable for password=[redacted] token=[redacted]', $message->notification_error);
        $this->assertSame('new', $message->status);
    }

    #[Test]
    public function notification_falls_back_to_mail_from_address_when_block_and_contact_recipient_are_empty(): void
    {
        config()->set('contact.recipient_email', null);
        config()->set('mail.from.address', 'hello@example.com');
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => null]);
        $this->updateContactFormSettings($block, ['recipient_email' => null]);

        Mail::fake();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('hello@example.com', $message->notification_recipient);
        $this->assertNotNull($message->notification_sent_at);
        $this->assertNull($message->notification_error);
        Mail::assertSent(PackageContactMessageNotification::class, fn (PackageContactMessageNotification $mail): bool => $mail->hasTo('hello@example.com'));
    }

    #[Test]
    public function missing_recipient_marks_failure_without_claiming_a_transport_send(): void
    {
        config()->set('contact.recipient_email', null);
        config()->set('mail.from.address', null);
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => null]);
        $this->updateContactFormSettings($block, ['recipient_email' => null]);

        Mail::fake();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertSame('No contact recipient email is configured.', $message->notification_error);
        $this->assertNull($message->notification_sent_at);
        $this->assertNull($message->notification_recipient);
        Mail::assertNothingSent();
    }

    #[Test]
    public function disabled_notification_is_not_marked_as_failed(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();
        $block->page->site->update(['contact_recipient_email' => 'site@example.com']);
        $this->updateContactFormSettings($block, [
            'recipient_email' => 'team@example.com',
            'send_email_notification' => false,
        ]);

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->firstOrFail();

        $this->assertFalse($message->notification_enabled);
        $this->assertNull($message->notification_error);
        $this->assertSame('Skipped', $message->notificationLabel());
        Mail::assertNothingSent();
    }

    #[Test]
    public function honeypot_submission_is_treated_as_success_without_persisting(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'website' => 'https://spam.example.com',
        ]));

        $response->assertStatus(302);
        $this->assertSame('/p/contact', parse_url((string) $response->baseResponse->headers->get('Location'), PHP_URL_PATH));
        $this->assertSame('contact-form-'.$block->id, parse_url((string) $response->baseResponse->headers->get('Location'), PHP_URL_FRAGMENT));
        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingSent();
    }

    #[Test]
    public function admin_messages_list_requires_authentication(): void
    {
        $this->get(route('admin.contact-messages.index'))
            ->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_update_message_status(): void
    {
        $user = User::factory()->create();
        [, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Status change',
            'message' => 'Please update this status.',
            'status' => 'new',
        ]);

        $this->actingAs($user)
            ->patch(route('admin.contact-messages.status', $message), ['status' => 'replied'])
            ->assertRedirect();

        $this->assertSame('replied', $message->fresh()->status);
    }

    #[Test]
    public function admin_messages_list_still_shows_message_rows_after_compacting_the_list(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();

        ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => null,
            'message' => 'List source check.',
            'status' => 'new',
            'source_url' => route('pages.show', $page->slug),
            'notification_enabled' => true,
            'notification_recipient' => 'team@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.index'));

        $response->assertOk();
        $response->assertSee('Contact');
        $response->assertSee('Taylor Editor');
        $response->assertSee('&mdash;', false);
        $response->assertDontSee('<th>Source</th>', false);
    }

    #[Test]
    public function admin_messages_list_supports_filters_and_compact_actions(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();

        $matching = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Launch checklist',
            'message' => 'Please confirm the launch checklist.',
            'status' => 'new',
            'notification_enabled' => true,
            'notification_sent_at' => now(),
        ]);

        $filteredOut = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Jordan Writer',
            'email' => 'jordan@example.com',
            'subject' => 'Archive me',
            'message' => 'Old note.',
            'status' => 'archived',
            'notification_enabled' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.index', [
            'search' => 'launch',
            'status' => 'new',
            'notification' => 'sent',
        ]));

        $response->assertOk();
        $response->assertSee(route('admin.contact-messages.show', $matching), false);
        $response->assertDontSee(route('admin.contact-messages.show', $filteredOut), false);
        $response->assertSee('data-admin-listing-filters', false);
        $response->assertSee('data-admin-listing-filters-search', false);
        $response->assertSee('data-admin-listing-filters-fields', false);
        $response->assertSee('data-admin-listing-filters-actions', false);
        $response->assertSee('Search');
        $response->assertSee('Notification');
        $response->assertSee('id="contact_messages_search"', false);
        $response->assertSee('id="contact_messages_status"', false);
        $response->assertSee('id="contact_messages_notification"', false);
        $response->assertSee('Apply', false);
        $response->assertSee(route('admin.contact-messages.index'), false);
        $response->assertSee('wb-action-group', false);
        $response->assertSee('wb-icon-menu', false);
        $response->assertDontSee('<th>Source</th>', false);
    }

    #[Test]
    public function admin_messages_list_renders_bulk_delete_selection_ui_and_modal_without_browser_confirm(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Bulk UI',
            'message' => 'Please bulk delete this.',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.index'));

        $response->assertOk();
        $response->assertSee('data-wb-admin-bulk-listing', false);
        $response->assertSee('data-wb-admin-select-all-visible', false);
        $response->assertSee('data-wb-admin-row-select', false);
        $response->assertSee('data-wb-target="#bulk-delete-contact-messages-modal"', false);
        $response->assertSee(route('admin.contact-messages.bulk-destroy'), false);
        $response->assertSee('name="contact_message_ids[]"', false);
        $response->assertSee('value="'.$message->id.'"', false);
        $response->assertSee('data-wb-admin-bulk-modal-count', false);
        $response->assertDontSee('confirm(', false);
    }

    #[Test]
    public function contact_message_detail_delete_uses_cms_modal_instead_of_browser_confirm(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Modal delete',
            'message' => 'Delete through modal.',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.show', $message));

        $response->assertOk();
        $response->assertSee('data-wb-target="#delete-contact-message-modal"', false);
        $response->assertSee(route('admin.contact-messages.destroy', $message), false);
        $response->assertSee('Delete Contact Message');
        $response->assertDontSee('confirm(', false);
    }

    #[Test]
    public function contact_messages_bulk_delete_requires_authentication(): void
    {
        $this->delete(route('admin.contact-messages.bulk-destroy'), [
            'contact_message_ids' => [1],
        ])->assertRedirect(route('login'));
    }

    #[Test]
    public function admin_can_bulk_delete_selected_contact_messages(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $first = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'First Sender',
            'email' => 'first@example.com',
            'subject' => 'First',
            'message' => 'First message.',
            'status' => 'new',
        ]);
        $second = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Second Sender',
            'email' => 'second@example.com',
            'subject' => 'Second',
            'message' => 'Second message.',
            'status' => 'new',
        ]);
        $unselected = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Third Sender',
            'email' => 'third@example.com',
            'subject' => 'Third',
            'message' => 'Third message.',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->delete(route('admin.contact-messages.bulk-destroy'), [
            'contact_message_ids' => [$first->id, $second->id],
        ]);

        $response->assertRedirect(route('admin.contact-messages.index'));
        $response->assertSessionHas('status', '2 selected messages deleted.');
        $this->assertDatabaseMissing('contact_messages', ['id' => $first->id]);
        $this->assertDatabaseMissing('contact_messages', ['id' => $second->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $unselected->id]);
    }

    #[Test]
    public function contact_messages_bulk_delete_rejects_missing_or_invalid_ids_safely(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->from(route('admin.contact-messages.index'))
            ->delete(route('admin.contact-messages.bulk-destroy'), [
                'contact_message_ids' => [],
            ])
            ->assertRedirect(route('admin.contact-messages.index'))
            ->assertSessionHasErrors(['contact_message_ids']);

        $this->actingAs($user)
            ->from(route('admin.contact-messages.index'))
            ->delete(route('admin.contact-messages.bulk-destroy'), [
                'contact_message_ids' => [999999],
            ])
            ->assertRedirect(route('admin.contact-messages.index'))
            ->assertSessionHasErrors(['contact_message_ids.0']);
    }

    #[Test]
    public function contact_messages_bulk_delete_reports_partial_success_for_inaccessible_messages(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $otherSite = Site::query()->create([
            'name' => 'Other Site',
            'handle' => 'other-site',
            'domain' => 'other.test',
            'is_primary' => false,
            'status' => 'active',
        ]);
        $otherPage = Page::query()->create([
            'site_id' => $otherSite->id,
            'title' => 'Other Contact',
            'slug' => 'other-contact',
            'status' => 'published',
        ]);
        $allowed = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Allowed Sender',
            'email' => 'allowed@example.com',
            'subject' => 'Allowed',
            'message' => 'Allowed message.',
            'status' => 'new',
        ]);
        $inaccessible = ContactMessage::create([
            'page_id' => $otherPage->id,
            'name' => 'Other Sender',
            'email' => 'other@example.com',
            'subject' => 'Other',
            'message' => 'Other message.',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->delete(route('admin.contact-messages.bulk-destroy'), [
            'contact_message_ids' => [$allowed->id, $inaccessible->id],
        ]);

        $response->assertRedirect(route('admin.contact-messages.index'));
        $response->assertSessionHas('status', '1 selected message deleted. 1 could not be deleted.');
        $response->assertSessionHasErrors(['contact_messages']);
        $this->assertDatabaseMissing('contact_messages', ['id' => $allowed->id]);
        $this->assertDatabaseHas('contact_messages', ['id' => $inaccessible->id]);
    }

    #[Test]
    public function admin_messages_list_pagination_preserves_filters_and_uses_compact_summary(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();

        foreach (range(1, 35) as $index) {
            ContactMessage::create([
                'block_id' => $block->id,
                'page_id' => $page->id,
                'name' => 'Pattern Sender '.$index,
                'email' => 'pattern-'.$index.'@example.com',
                'subject' => sprintf('Pattern Subject %02d', $index),
                'message' => 'Pattern pagination message '.$index,
                'status' => 'new',
                'notification_enabled' => true,
                'notification_sent_at' => now(),
            ]);
        }

        $response = $this->actingAs($user)->get(route('admin.contact-messages.index', [
            'search' => 'Pattern',
            'status' => 'new',
            'notification' => 'sent',
        ]));

        $response->assertOk();
        $response->assertSee('data-admin-pagination', false);
        $response->assertSee('class="wb-pagination wb-pagination-compact"', false);
        $response->assertSee('aria-label="Contact messages pagination"', false);
        $response->assertSee('aria-current="page">1</span>', false);
        $response->assertSee('data-admin-pagination-summary', false);
        $response->assertSee('1-15/35', false);
        $response->assertDontSee('Showing 1-15 of 35', false);
        $response->assertSee(e(route('admin.contact-messages.index', [
            'search' => 'Pattern',
            'status' => 'new',
            'notification' => 'sent',
            'page' => 2,
        ])), false);
        $response->assertSee('<span class="wb-pagination-link">Previous</span>', false);

        $pageTwo = $this->actingAs($user)->get(route('admin.contact-messages.index', [
            'search' => 'Pattern',
            'status' => 'new',
            'notification' => 'sent',
            'page' => 2,
        ]));

        $pageTwo->assertOk();
        $pageTwo->assertSee('aria-current="page">2</span>', false);
        $pageTwo->assertSee('16-30/35', false);
        $pageTwo->assertSee(e(route('admin.contact-messages.index', [
            'search' => 'Pattern',
            'status' => 'new',
            'notification' => 'sent',
            'page' => 1,
        ])), false);
    }

    #[Test]
    public function admin_message_detail_shows_editorial_source_context(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Context check',
            'message' => 'Detail source check.',
            'status' => 'new',
            'source_url' => route('pages.show', $page->slug),
            'referer' => 'https://example.test/origin',
            'ip_address' => '203.0.113.25',
            'user_agent' => 'FeatureTest Browser',
            'notification_enabled' => true,
            'notification_recipient' => 'team@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.show', $message));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Visitor message',
            'Name:',
            'Taylor Editor',
            'Email:',
            'taylor@example.com',
            'Subject:',
            'Context check',
            'Message:',
            'Detail source check.',
            'Submission details',
            'Notification',
            'Technical details',
        ]);
        $response->assertSee('Detail source check.');
        $response->assertSee('Taylor Editor');
        $response->assertSee('taylor@example.com');
        $response->assertSee('Submission details');
        $response->assertSee('Path:');
        $response->assertSee('/p/contact');
        $response->assertSee('Source URL:');
        $response->assertSee('Open source');
        $response->assertSee(route('pages.show', $page->slug), false);
        $response->assertSee('Referrer:');
        $response->assertSee('https://example.test/origin');
        $response->assertSee('Received at:');
        $response->assertSee('Block / Slot:');
        $response->assertSee('Notification');
        $response->assertSee('Status:');
        $response->assertSee('Recipient:');
        $response->assertSee('Technical details');
        $response->assertSee('Admin-only request metadata captured with the submission.');
        $response->assertSee('IP address:');
        $response->assertSee('203.0.113.25');
        $response->assertSee('User agent:');
        $response->assertSee('FeatureTest Browser');
        $response->assertSee('Block ID:');
        $response->assertSee('Page ID:');
        $response->assertSee('Mark read');
        $response->assertSee('Mark replied');
        $response->assertSee('Back to Inbox');
        $response->assertSee('data-wb-target="#delete-contact-message-modal"', false);
    }

    #[Test]
    public function contact_message_detail_escapes_visitor_provided_content(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => '<strong>Taylor</strong>',
            'email' => 'taylor@example.com',
            'subject' => '<script>alert("subject")</script>',
            'message' => '<img src=x onerror=alert(1)>',
            'status' => 'new',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.show', $message));

        $response->assertOk();
        $response->assertSee('&lt;strong&gt;Taylor&lt;/strong&gt;', false);
        $response->assertSee('&lt;script&gt;alert(&quot;subject&quot;)&lt;/script&gt;', false);
        $response->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        $response->assertDontSee('<strong>Taylor</strong>', false);
        $response->assertDontSee('<script>alert("subject")</script>', false);
        $response->assertDontSee('<img src=x onerror=alert(1)>', false);
    }

    #[Test]
    public function admin_views_show_compact_notification_failure_reason_and_full_failure_detail(): void
    {
        $user = User::factory()->create();
        [$page, $block] = $this->createContactFormPage();
        $message = ContactMessage::create([
            'block_id' => $block->id,
            'page_id' => $page->id,
            'name' => 'Taylor Editor',
            'email' => 'taylor@example.com',
            'subject' => 'Failed notification',
            'message' => 'Detail source check.',
            'status' => 'new',
            'source_url' => route('pages.show', $page->slug),
            'notification_enabled' => true,
            'notification_recipient' => 'team@example.com',
            'notification_error' => 'SMTP unavailable',
        ]);

        $this->actingAs($user)
            ->get(route('admin.contact-messages.index'))
            ->assertOk()
            ->assertSee('Failed', false)
            ->assertSee('SMTP unavailable', false);

        $this->actingAs($user)
            ->get(route('admin.contact-messages.show', $message))
            ->assertOk()
            ->assertSee('Failure detail:', false)
            ->assertSee('SMTP unavailable', false);
    }

    #[Test]
    public function contact_form_block_renders_on_a_public_page(): void
    {
        [$page] = $this->createContactFormPage();

        $response = $this->get(route('pages.show', $page->slug));

        $response->assertOk();
        $response->assertSee('Contact us');
        $response->assertSee('Send a message to the editorial team.');
        $response->assertSee('Name');
        $response->assertSee('Email');
        $response->assertSee('Subject');
        $response->assertSee('Message');
        $response->assertSee(route('contact-messages.store'), false);
        $response->assertSee('method="POST"', false);
        $response->assertSee('name="_token"', false);
        $response->assertSee('name="source_url" value="/p/contact"', false);
    }

    #[Test]
    public function public_contact_form_renderer_does_not_depend_on_host_blade_components(): void
    {
        $contents = File::get(base_path('packages/webblocks-cms/resources/views/pages/partials/blocks/contact_form.blade.php'));

        $this->assertStringNotContainsString('<x-input-label', $contents);
        $this->assertStringNotContainsString('<x-text-input', $contents);
        $this->assertStringNotContainsString('<x-input-error', $contents);
        $this->assertStringNotContainsString('<x-primary-button', $contents);
    }

    #[Test]
    public function public_rendering_does_not_require_canonical_contact_form_copy(): void
    {
        [$page, $block] = $this->createContactFormPage();

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        $freshBlock = $block->fresh();

        $this->assertNull($freshBlock->getRawOriginal('title'));
        $this->assertNull($freshBlock->getRawOriginal('content'));

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee('Send a message to the editorial team.');
    }

    #[Test]
    public function migration_backfills_contact_form_copy_into_translation_rows_and_removes_json_keys(): void
    {
        $slotType = $this->slotType();
        $blockType = $this->contactBlockType();
        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $blockType->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Contact us',
            'content' => 'Send a message to the editorial team.',
            'settings' => json_encode([
                'submit_label' => 'Legacy send',
                'success_message' => 'Legacy success',
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => false,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $migration = require base_path('database/migrations/2026_04_25_120000_move_contact_form_copy_out_of_block_settings.php');
        $migration->up();

        $translation = $block->fresh()->contactFormTranslations()->where('locale_id', $this->defaultLocale()->id)->first();
        $settings = json_decode((string) $block->fresh()->getRawOriginal('settings'), true);

        $this->assertNotNull($translation);
        $this->assertSame('Legacy send', $translation->submit_label);
        $this->assertSame('Legacy success', $translation->success_message);
        $this->assertArrayNotHasKey('submit_label', $settings);
        $this->assertArrayNotHasKey('success_message', $settings);
        $this->assertSame('team@example.com', $settings['recipient_email']);
        $this->assertTrue($settings['send_email_notification']);
        $this->assertFalse($settings['store_submissions']);
    }

    #[Test]
    public function authoritative_block_translation_migration_backfills_default_rows_and_clears_canonical_contact_copy(): void
    {
        $slotType = $this->slotType();
        $blockType = $this->contactBlockType();
        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $blockType->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Contact us',
            'content' => 'Send a message to the editorial team.',
            'settings' => json_encode([
                'submit_label' => 'Legacy send',
                'success_message' => 'Legacy success',
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => false,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $migration = require base_path('database/migrations/2026_04_25_130000_make_block_translations_authoritative.php');
        $migration->up();

        $translation = $block->fresh()->contactFormTranslations()->where('locale_id', $this->defaultLocale()->id)->first();
        $freshBlock = $block->fresh();
        $settings = json_decode((string) $freshBlock->getRawOriginal('settings'), true);

        $this->assertNotNull($translation);
        $this->assertSame('Contact us', $translation->title);
        $this->assertSame('Send a message to the editorial team.', $translation->content);
        $this->assertSame('Legacy send', $translation->submit_label);
        $this->assertSame('Legacy success', $translation->success_message);
        $this->assertNull($freshBlock->getRawOriginal('title'));
        $this->assertNull($freshBlock->getRawOriginal('content'));
        $this->assertArrayNotHasKey('submit_label', $settings);
        $this->assertArrayNotHasKey('success_message', $settings);
        $this->assertSame('team@example.com', $settings['recipient_email']);
    }

    #[Test]
    public function authoritative_translation_normalization_can_backfill_contact_form_copy_from_legacy_canonical_fields(): void
    {
        $slotType = $this->slotType();
        $blockType = $this->contactBlockType();
        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $blockType->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Legacy contact heading',
            'content' => 'Legacy intro copy',
            'settings' => json_encode([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block);

        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Legacy contact heading',
            'content' => 'Legacy intro copy',
        ]);
        $this->assertNull($block->fresh()->getRawOriginal('title'));
        $this->assertNull($block->fresh()->getRawOriginal('content'));
    }

    #[Test]
    public function public_rendering_uses_translation_values_for_each_locale_and_safe_defaults_when_copy_is_missing(): void
    {
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        [$page, $block] = $this->createContactFormPage();

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Iletisim',
            'slug' => 'iletisim',
            'path' => '/tr/p/iletisim',
        ]);

        $block->contactFormTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Bize ulasin',
            'content' => 'Turkce tanitim',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler.',
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Send message');

        $this->withSession(['contact_form_success_block_id' => $block->id])
            ->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Thanks for your message. We will get back to you soon.');

        $this->get('/tr/p/iletisim')
            ->assertOk()
            ->assertSee('Mesaj gonder');

        $this->withSession(['contact_form_success_block_id' => $block->id])
            ->get('/tr/p/iletisim')
            ->assertOk()
            ->assertSee('Tesekkurler.');

        $block->contactFormTranslations()->where('locale_id', $this->defaultLocale()->id)->update([
            'submit_label' => null,
            'success_message' => null,
        ]);
        $block->contactFormTranslations()->where('locale_id', $turkish->id)->update([
            'submit_label' => null,
            'success_message' => null,
        ]);

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Send message')
            ->assertSee(config('contact.success_message'));
    }

    #[Test]
    public function contact_form_submit_and_success_copy_come_from_translations_only_after_cleanup(): void
    {
        [$page, $block] = $this->createContactFormPage();

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        $freshBlock = $block->fresh(['contactFormTranslations']);

        $this->assertNull($freshBlock->getRawOriginal('title'));
        $this->assertNull($freshBlock->getRawOriginal('content'));

        $this->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee('Send message');

        $this->withSession(['contact_form_success_block_id' => $block->id])
            ->get(route('pages.show', $page->slug))
            ->assertOk()
            ->assertSee('Thanks for your message. We will get back to you soon.');
    }

    #[Test]
    public function non_default_locale_contact_form_renders_localized_copy_after_translation_cleanup(): void
    {
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->update(['domain' => 'primary.example.test']);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        [$page, $block] = $this->createContactFormPage();

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Iletisim',
            'slug' => 'iletisim',
            'path' => '/p/iletisim',
        ]);

        $block->contactFormTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Bize ulasin',
            'content' => 'Turkce tanitim',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler.',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        $this->get('http://primary.example.test/tr/p/iletisim')
            ->assertOk()
            ->assertSee('Bize ulasin')
            ->assertSee('Mesaj gonder');

        $this->withSession(['contact_form_success_block_id' => $block->id])
            ->get('http://primary.example.test/tr/p/iletisim')
            ->assertOk()
            ->assertSee('Tesekkurler.');
    }

    #[Test]
    public function submission_flow_still_works_after_translation_cleanup(): void
    {
        Mail::fake();
        [$page, $block] = $this->createContactFormPage();

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'source_url' => route('pages.show', ['slug' => $page->slug], false),
        ]));

        $response->assertRedirect(route('pages.show', ['slug' => 'contact'], false).'#contact-form-'.$block->id);
        $this->assertDatabaseHas('contact_messages', [
            'block_id' => $block->id,
            'page_id' => $page->id,
            'email' => 'taylor@example.com',
            'status' => 'new',
        ]);
    }
}
