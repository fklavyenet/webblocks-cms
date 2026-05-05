<?php

namespace Tests\Feature\Integrity;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationResolver;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockTranslationIntegrityTest extends TestCase
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

    private function createLocale(string $code): Locale
    {
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => true,
        ]);
    }

    private function slotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function blockType(string $slug, string $sourceType = 'static'): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => str($slug)->headline()->toString(), 'source_type' => $sourceType, 'status' => 'published'],
        );
    }

    private function pageWithMainSlot(Site $site, string $title = 'About', string $slug = 'about'): Page
    {
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => $title,
            'slug' => $slug,
            'status' => Page::STATUS_PUBLISHED,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
        ]);

        return $page;
    }

    #[Test]
    public function translatable_blocks_render_correctly_when_canonical_fields_are_null(): void
    {
        $page = $this->pageWithMainSlot($this->defaultSite());
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'header',
            'block_type_id' => $this->blockType('header')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero heading',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $freshBlock = $block->fresh();

        $this->assertNull($freshBlock->getRawOriginal('title'));
        $this->assertNull($freshBlock->getRawOriginal('content'));
        $this->get('/p/about')->assertOk()->assertSee('<h1 data-wb-public-block-type="header">Hero heading</h1>', false);
    }

    #[Test]
    public function default_locale_translation_is_used_when_present_and_missing_localized_copy_falls_back_consistently(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageWithMainSlot($site);
        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $this->blockType('plain_text')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'content' => 'Canonical should not render',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'content' => 'Default content',
        ]);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $resolved = app(BlockTranslationResolver::class)->resolve($block->fresh(['textTranslations']), 'tr');

        $this->assertSame('fallback', $resolved->translation_state);
        $this->assertSame('en', $resolved->resolved_locale_code);
        $this->assertSame('Default content', $resolved->content);
        $this->get('http://primary.example.test/tr/p/hakkinda')->assertOk()->assertSee('<p>Default content</p>', false);
    }

    #[Test]
    public function non_default_locale_translation_does_not_overwrite_shared_contact_form_fields(): void
    {
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageWithMainSlot($site, 'Contact', 'contact');

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $this->blockType('contact_form', 'form')->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'settings' => json_encode([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'title' => 'Contact us',
            'content' => 'English copy',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks',
        ], null, true);

        app(BlockTranslationWriter::class)->sync($block->fresh(['contactFormTranslations']), [
            'title' => 'Bize ulasin',
            'content' => 'Turkce kopya',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler',
        ], 'tr');

        $freshBlock = $block->fresh(['contactFormTranslations']);
        $settings = json_decode((string) $freshBlock->getRawOriginal('settings'), true);

        $this->assertSame('team@example.com', $settings['recipient_email']);
        $this->assertTrue($settings['send_email_notification']);
        $this->assertTrue($settings['store_submissions']);
        $this->assertNull($freshBlock->getRawOriginal('title'));
        $this->assertNull($freshBlock->getRawOriginal('content'));
    }

    #[Test]
    public function contact_form_translations_render_correctly_across_locales_even_with_invalid_extra_translation_rows_present(): void
    {
        $site = $this->defaultSite();
        $site->update(['domain' => 'primary.example.test']);
        $turkish = $this->createLocale('tr');
        $french = $this->createLocale('fr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageWithMainSlot($site, 'Contact', 'contact');
        $page->translations()->create([
            'locale_id' => $turkish->id,
            'name' => 'Iletisim',
            'slug' => 'iletisim',
            'path' => '/p/iletisim',
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $this->blockType('contact_form', 'form')->id,
            'source_type' => 'form',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'settings' => json_encode([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'title' => 'Contact us',
            'content' => 'English copy',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message.',
        ], null, true);
        app(BlockTranslationWriter::class)->sync($block->fresh(['contactFormTranslations']), [
            'title' => 'Bize ulasin',
            'content' => 'Turkce kopya',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler.',
        ], 'tr');
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        DB::table('block_contact_form_translations')->insert([
            'block_id' => $block->id,
            'locale_id' => $french->id,
            'title' => 'French orphan',
            'content' => 'French orphan content',
            'submit_label' => 'Envoyer',
            'success_message' => 'Merci',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get('http://primary.example.test/p/contact')
            ->assertOk()
            ->assertSee('Contact us')
            ->assertSee('Send message');

        $this->get('http://primary.example.test/tr/p/iletisim')
            ->assertOk()
            ->assertSee('Bize ulasin')
            ->assertSee('Mesaj gonder')
            ->assertDontSee('French orphan');
    }

    #[Test]
    public function invalid_block_locale_is_rejected_at_request_level(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $german = $this->createLocale('de');
        $page = $this->pageWithMainSlot($site);
        $blockType = $this->blockType('header');

        $response = $this->actingAs($user)
            ->from(route('admin.blocks.create', ['page_id' => $page->id, 'slot_type_id' => $this->slotType()->id]))
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'parent_id' => null,
                'block_type_id' => $blockType->id,
                'slot_type_id' => $this->slotType()->id,
                'sort_order' => 0,
                'text' => 'Titel',
                'level' => 'h2',
                'status' => 'published',
                'locale' => $german->code,
            ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors(['locale' => 'Selected locale must be enabled for the page site.']);
        $this->assertSame(0, Block::query()->where('page_id', $page->id)->count());
    }

    #[Test]
    public function stat_card_translation_payload_keeps_zero_as_string(): void
    {
        $page = $this->pageWithMainSlot($this->defaultSite());
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'stat-card',
            'block_type_id' => $this->blockType('stat-card')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'url' => '/package',
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'type' => 'stat-card',
            'title' => '0',
            'subtitle' => 'Dependencies',
            'content' => 'No framework requirement for the package itself',
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $translation = DB::table('block_text_translations')
            ->where('block_id', $block->id)
            ->where('locale_id', $this->defaultLocale()->id)
            ->first();

        $this->assertNotNull($translation);
        $this->assertSame('0', $translation->title);
        $this->assertSame('Dependencies', $translation->subtitle);
        $this->assertSame('No framework requirement for the package itself', $translation->content);
        $this->assertNull($block->fresh()->getRawOriginal('title'));
    }

    #[Test]
    public function translated_sidebar_nav_item_updates_do_not_overwrite_shared_url_target_icon_or_active_settings(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageWithMainSlot($site);

        $navigation = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'sidebar-navigation',
            'block_type_id' => $this->blockType('sidebar-navigation')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $navigation->id,
            'type' => 'sidebar-nav-item',
            'block_type_id' => $this->blockType('sidebar-nav-item')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'settings' => json_encode([
                'url' => '/p/about',
                'target' => '_blank',
                'icon' => 'code',
                'active_mode' => 'manual',
                'manual_active' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $block->block_type_id,
            'sort_order' => 0,
            'locale' => 'tr',
            'title' => 'Baslangic',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $fresh = $block->fresh(['textTranslations']);
        $settings = json_decode((string) $fresh->getRawOriginal('settings'), true);

        $this->assertSame('/p/about', $settings['url']);
        $this->assertSame('_blank', $settings['target']);
        $this->assertSame('code', $settings['icon']);
        $this->assertSame('manual', $settings['active_mode']);
        $this->assertTrue($settings['manual_active']);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $turkish->id,
            'title' => 'Baslangic',
        ]);
    }

    #[Test]
    public function translated_sidebar_brand_updates_do_not_overwrite_shared_logo_url_or_target(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page = $this->pageWithMainSlot($site);
        $asset = \App\Models\Asset::query()->create([
            'disk' => 'public',
            'path' => 'media/images/sidebar-brand.png',
            'filename' => 'sidebar-brand.png',
            'original_name' => 'sidebar-brand.png',
            'extension' => 'png',
            'mime_type' => 'image/png',
            'size' => 100,
            'kind' => 'image',
            'visibility' => 'public',
            'uploaded_by' => $user->id,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'sidebar-brand',
            'block_type_id' => $this->blockType('sidebar-brand')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'asset_id' => $asset->id,
            'settings' => json_encode([
                'url' => '/p/about',
                'target' => '_blank',
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType()->id,
            'block_type_id' => $block->block_type_id,
            'sort_order' => 0,
            'locale' => 'tr',
            'title' => 'WebBlocks TR',
            'subtitle' => 'Turkce aciklama',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $fresh = $block->fresh(['textTranslations']);
        $settings = json_decode((string) $fresh->getRawOriginal('settings'), true);

        $this->assertSame($asset->id, $fresh->asset_id);
        $this->assertSame('/p/about', $settings['url']);
        $this->assertSame('_blank', $settings['target']);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $turkish->id,
            'title' => 'WebBlocks TR',
            'subtitle' => 'Turkce aciklama',
        ]);
    }

    #[Test]
    public function rich_text_block_type_exists_after_seeding(): void
    {
        $this->seed(\Database\Seeders\FoundationSiteLocaleSeeder::class);
        $this->seed(\Database\Seeders\BlockTypeSeeder::class);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'rich-text',
            'name' => 'Rich Text',
            'status' => 'published',
            'category' => 'content',
            'is_container' => false,
        ]);
    }

    #[Test]
    public function rich_text_updates_are_stored_in_translation_backed_content_and_canonical_content_is_cleared(): void
    {
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithMainSlot($this->defaultSite());
        $blockType = $this->blockType('rich-text');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $blockType->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'content' => 'Hello `world`',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $response->assertRedirect();

        $block = Block::query()->where('page_id', $page->id)->where('type', 'rich-text')->firstOrFail();

        $this->assertNull($block->fresh()->getRawOriginal('content'));
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'content' => 'Hello `world`',
        ]);
    }

    #[Test]
    public function rich_text_input_is_kept_as_plain_text_in_translation_rows(): void
    {
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithMainSlot($this->defaultSite());
        $blockType = $this->blockType('rich-text');

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $blockType->id,
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'content' => '<script>alert(1)</script> `good`',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $response->assertRedirect();

        $block = Block::query()->where('page_id', $page->id)->where('type', 'rich-text')->firstOrFail();
        $content = DB::table('block_text_translations')->where('block_id', $block->id)->value('content');

        $this->assertSame('<script>alert(1)</script> `good`', $content);
    }

    #[Test]
    public function whitespace_only_rich_text_normalizes_to_null_translation_content(): void
    {
        $page = $this->pageWithMainSlot($this->defaultSite());
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'rich-text',
            'block_type_id' => $this->blockType('rich-text')->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->slotType()->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'content' => '  <p> </p>  ',
        ], null, true);

        $this->assertNull(DB::table('block_text_translations')->where('block_id', $block->id)->value('content'));
    }
}
