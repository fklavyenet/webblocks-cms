<?php

namespace Tests\Feature;

use App\Mail\ContactMessageNotification;
use App\Models\Block;
use App\Models\Locale;
use App\Models\BlockType;
use App\Models\ContactMessage;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ContactFormModuleTest extends TestCase
{
    use RefreshDatabase;

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
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
        $page = Page::create([
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

    private function submissionPayload(Block $block, ?array $overrides = []): array
    {
        return array_merge([
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'source_url' => route('pages.show', $block->page?->slug ?? 'contact'),
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

        $response->assertRedirect(route('pages.show', 'contact', false).'#contact-form-'.$block->id);
        $this->assertDatabaseHas('contact_messages', [
            'block_id' => $block->id,
            'page_id' => $block->page_id,
            'email' => 'taylor@example.com',
            'status' => 'new',
        ]);
    }

    #[Test]
    public function mail_notification_is_attempted_after_persistence(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $this->post(route('contact-messages.store'), $this->submissionPayload($block));

        Mail::assertSent(ContactMessageNotification::class, function (ContactMessageNotification $mail) use ($block): bool {
            return $mail->contactMessage->block_id === $block->id;
        });
    }

    #[Test]
    public function submission_is_stored_even_when_mail_fails(): void
    {
        [, $block] = $this->createContactFormPage();

        Mail::shouldReceive('to')->once()->with('team@example.com')->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('SMTP unavailable'));

        $this->post(route('contact-messages.store'), $this->submissionPayload($block))
            ->assertRedirect();

        $message = ContactMessage::query()->latest('id')->first();

        $this->assertNotNull($message);
        $this->assertSame('SMTP unavailable', $message->notification_error);
        $this->assertSame('new', $message->status);
    }

    #[Test]
    public function honeypot_submission_is_treated_as_success_without_persisting(): void
    {
        Mail::fake();
        [, $block] = $this->createContactFormPage();

        $response = $this->post(route('contact-messages.store'), $this->submissionPayload($block, [
            'website' => 'https://spam.example.com',
        ]));

        $response->assertRedirect(route('pages.show', 'contact', false).'#contact-form-'.$block->id);
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
        $response->assertSee('Search');
        $response->assertSee('Notification');
        $response->assertSee('wb-action-group', false);
        $response->assertSee('wb-icon-menu', false);
        $response->assertDontSee('<th>Source</th>', false);
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
            'notification_enabled' => true,
            'notification_recipient' => 'team@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('admin.contact-messages.show', $message));

        $response->assertOk();
        $response->assertSee('Path:');
        $response->assertSee('/p/contact');
        $response->assertSee('Block ID:');
        $response->assertSee('Slot:');
        $response->assertSee('Notification');
        $response->assertSee('Recipient:');
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
}
