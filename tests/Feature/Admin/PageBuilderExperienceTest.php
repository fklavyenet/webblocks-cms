<?php

namespace Tests\Feature\Admin;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\CoreCatalogSeeder;
use Database\Seeders\StarterContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageBuilderExperienceTest extends TestCase
{
    use RefreshDatabase;

    private function slotType(string $slug, string $name, int $sortOrder): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
        );
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function editorWithSites(array $siteIds = []): User
    {
        $user = User::factory()->editor()->create();

        if ($siteIds !== []) {
            $user->sites()->sync($siteIds);
        }

        return $user;
    }

    private function blockPersistenceShape(Block $block, Locale $defaultLocale, ?Locale $requestedLocale = null): array
    {
        $defaultTranslation = $block->textTranslations()->where('locale_id', $defaultLocale->id)->first();
        $requestedTranslation = $requestedLocale
            ? $block->textTranslations()->where('locale_id', $requestedLocale->id)->first()
            : null;

        return [
            'canonical' => [
                'type' => $block->type,
                'slot_type_id' => $block->slot_type_id,
                'title' => $block->getRawOriginal('title'),
                'subtitle' => $block->getRawOriginal('subtitle'),
                'content' => $block->getRawOriginal('content'),
                'status' => $block->status,
            ],
            'default_translation' => $defaultTranslation ? [
                'title' => $defaultTranslation->title,
                'subtitle' => $defaultTranslation->subtitle,
                'content' => $defaultTranslation->content,
            ] : null,
            'requested_translation' => $requestedTranslation ? [
                'title' => $requestedTranslation->title,
                'subtitle' => $requestedTranslation->subtitle,
                'content' => $requestedTranslation->content,
            ] : null,
        ];
    }

    private function assertTextTranslation(Block $block, int $localeId, array $expected): void
    {
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $block->id, 'locale_id' => $localeId] + $expected);
    }

    #[Test]
    public function creating_a_page_starts_empty_and_persists_selected_slots(): void
    {
        $user = User::factory()->create();
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $site = $this->defaultSite();

        $response = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
            'slots' => [
                ['slot_type_id' => $header->id],
                ['slot_type_id' => $main->id],
            ],
        ]);

        $page = Page::query()
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $this->defaultLocale()->id)
                ->where('slug', 'about'))
            ->first();

        $this->assertNotNull($page);
        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertSame('default', $page->fresh()->page_type);
        $this->assertSame($site->id, $page->fresh()->site_id);
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale_id' => $this->defaultLocale()->id,
            'name' => 'About',
            'slug' => 'about',
        ]);
        $this->assertSame(0, Block::query()->where('page_id', $page->id)->count());
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $header->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $main->id, 'sort_order' => 1]);
    }

    #[Test]
    public function public_pages_render_slot_sections_without_debug_metadata(): void
    {
        $main = $this->slotType('main', 'Main', 2);
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $blockType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            [
                'name' => 'Section',
                'source_type' => 'static',
                'status' => 'published',
                'sort_order' => 1,
            ]
        );

        Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $blockType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'About us',
            'content' => 'Real page content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('About us');
        $response->assertDontSee('No blocks in this slot');
        $response->assertDontSee('Unsupported Block Render');
        $response->assertDontSee('Layout:');
        $response->assertDontSee('wb-navbar', false);
        $response->assertDontSee('Sign In');
        $response->assertDontSee('Create Account');
    }

    #[Test]
    public function public_pages_hide_empty_slots_and_render_supported_columns_blocks(): void
    {
        $header = $this->slotType('header', 'Header', 1);
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $header->id,
            'sort_order' => 0,
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 1,
        ]);

        $columnsBlock = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Contact Columns',
            'subtitle' => 'Three ways to reach us',
            'content' => 'Email, phone, and address.',
            'status' => 'published',
            'is_system' => true,
        ]);

        Block::create([
            'page_id' => $page->id,
            'parent_id' => $columnsBlock->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Email us',
            'content' => 'hello@example.com',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::create([
            'page_id' => $page->id,
            'parent_id' => $columnsBlock->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Call us',
            'content' => '+90 555 000 00 00',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'contact'));

        $response->assertOk();
        $response->assertSee('Contact Columns');
        $response->assertSee('Three ways to reach us');
        $response->assertSee('Email, phone, and address.');
        $response->assertSee('Email us');
        $response->assertSee('Call us');
        $response->assertDontSee('No blocks in this slot');
        $response->assertDontSee('Header');
        $response->assertDontSee('wb-navbar', false);
        $response->assertDontSee('Sign In');
        $response->assertDontSee('Create Account');
    }

    #[Test]
    public function public_pages_render_translated_block_content_and_fallback_to_default_locale(): void
    {
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $buttonType = BlockType::query()->firstOrCreate(
            ['slug' => 'button'],
            ['name' => 'Button', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2]
        );

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $section = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'About us',
            'content' => 'English section copy',
            'status' => 'published',
            'is_system' => false,
        ]);

        $button = Block::create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Contact us',
            'url' => '/p/contact',
            'status' => 'published',
            'is_system' => false,
        ]);

        $section->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'About us',
            'content' => 'English section copy',
        ]);
        $section->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Hakkimizda',
            'content' => 'Turkce bolum icerigi',
        ]);
        $button->buttonTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact us',
        ]);

        $response = $this->get('/tr/p/hakkinda');

        $response->assertOk();
        $response->assertSee('Hakkimizda');
        $response->assertSee('Turkce bolum icerigi');
        $response->assertSee('Contact us');
        $response->assertDontSee('About us');
        $response->assertDontSee('English section copy');
    }

    #[Test]
    public function pages_list_shows_slots_and_page_centered_actions(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $locale->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.index'));

        $response->assertOk();
        $response->assertSee('Main');
        $response->assertSee($site->name);
        $response->assertSee('Default');
        $response->assertSee('tr');
        $response->assertSee('/tr/p/hakkinda');
        $response->assertSee(route('admin.pages.edit', $page), false);
        $response->assertDontSee('<th>Slug</th>', false);
        $response->assertDontSee('<th>Slots</th>', false);
        $response->assertDontSee(route('admin.blocks.index', ['page_id' => $page->id]), false);
    }

    #[Test]
    public function pages_index_defaults_to_the_primary_site_context(): void
    {
        $user = User::factory()->superAdmin()->create();
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $primaryPage = Page::create([
            'site_id' => $primarySite->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $secondaryPage = Page::create([
            'site_id' => $secondarySite->id,
            'title' => 'Campaign Landing',
            'slug' => 'campaign-landing',
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.index'));

        $response->assertOk();
        $response->assertSee('Showing pages for '.$primarySite->name);
        $response->assertSee('value="'.$primarySite->id.'" selected', false);
        $response->assertSee(route('admin.pages.edit', $primaryPage), false);
        $response->assertDontSee(route('admin.pages.edit', $secondaryPage), false);
        $response->assertSee('<form method="GET" action="'.route('admin.pages.index').'" class="wb-inline-flex wb-items-center wb-gap-2 wb-flex-wrap">', false);
        $response->assertSee('<span class="wb-text-sm wb-text-muted wb-nowrap">Site</span>', false);
        $response->assertSee('id="pages_site_context" name="site" class="wb-select wb-w-auto" aria-label="Site"', false);
        $response->assertSee('<a href="'.route('admin.pages.create', ['site' => $primarySite->id]).'" class="wb-btn wb-btn-primary">New Page</a>', false);
        $response->assertDontSee('Current context');
    }

    #[Test]
    public function pages_index_respects_the_selected_site_query_param_and_legacy_site_id_param(): void
    {
        $user = User::factory()->superAdmin()->create();
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $primaryPage = Page::create([
            'site_id' => $primarySite->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $secondaryPage = Page::create([
            'site_id' => $secondarySite->id,
            'title' => 'Campaign Landing',
            'slug' => 'campaign-landing',
            'status' => 'draft',
        ]);

        $selected = $this->actingAs($user)->get(route('admin.pages.index', ['site' => $secondarySite->id]));

        $selected->assertOk();
        $selected->assertSee('Showing pages for '.$secondarySite->name);
        $selected->assertSee(route('admin.pages.edit', $secondaryPage), false);
        $selected->assertDontSee(route('admin.pages.edit', $primaryPage), false);

        $legacy = $this->actingAs($user)->get(route('admin.pages.index', ['site_id' => $secondarySite->id]));

        $legacy->assertOk();
        $legacy->assertSee('Showing pages for '.$secondarySite->name);
        $legacy->assertSee(route('admin.pages.edit', $secondaryPage), false);
        $legacy->assertDontSee(route('admin.pages.edit', $primaryPage), false);
    }

    #[Test]
    public function pages_index_can_explicitly_switch_to_all_sites_mode(): void
    {
        $user = User::factory()->superAdmin()->create();
        $primarySite = $this->defaultSite();
        $secondarySite = Site::query()->create([
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);

        $primaryPage = Page::create([
            'site_id' => $primarySite->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $secondaryPage = Page::create([
            'site_id' => $secondarySite->id,
            'title' => 'Campaign Landing',
            'slug' => 'campaign-landing',
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.index', ['site' => 'all']));

        $response->assertOk();
        $response->assertSee('value="all" selected', false);
        $response->assertSee('Showing pages across all sites');
        $response->assertSee(route('admin.pages.edit', $primaryPage), false);
        $response->assertSee(route('admin.pages.edit', $secondaryPage), false);
        $response->assertSee($primarySite->name);
        $response->assertSee($secondarySite->name);
    }

    #[Test]
    public function sites_list_links_into_the_pages_index_for_that_site(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();

        $response = $this->actingAs($user)->get(route('admin.sites.index'));

        $response->assertOk();
        $response->assertSee(route('admin.pages.index', ['site' => $site->id]), false);
    }

    #[Test]
    public function page_edit_displays_site_context_in_breadcrumb_and_header(): void
    {
        $user = User::factory()->create();
        $site = $this->defaultSite();
        $site->update(['domain' => 'default.example.test']);
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertSee(route('admin.pages.index', ['site' => $site->id]), false);
        $response->assertSeeInOrder([$site->name, '>Pages<', $page->title], false);
        $response->assertSee($site->name);
        $response->assertSee('default.example.test');
        $response->assertSee($page->title);
    }

    #[Test]
    public function page_edit_can_create_a_missing_translation(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $edit = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $edit->assertOk();
        $edit->assertSee('Translations');
        $edit->assertSee('Missing');
        $edit->assertSee(route('admin.pages.translations.create', [$page, $locale]), false);

        $store = $this->actingAs($user)->post(route('admin.pages.translations.store', [$page, $locale]), [
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
        ]);

        $store->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'locale_id' => $locale->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
        ]);
    }

    #[Test]
    public function translation_validation_enforces_slug_uniqueness_within_site_and_locale_scope(): void
    {
        $user = User::factory()->create();
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $about = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $contact = Page::create([
            'site_id' => $site->id,
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $about->id,
            'locale_id' => $locale->id,
            'name' => 'Hakkinda',
            'slug' => 'ortak',
        ]);

        $response = $this->actingAs($user)->from(route('admin.pages.edit', $contact))->post(route('admin.pages.translations.store', [$contact, $locale]), [
            'name' => 'Iletisim',
            'slug' => 'ortak',
        ]);

        $response->assertRedirect(route('admin.pages.edit', $contact));
        $response->assertSessionHasErrors('slug');
    }

    #[Test]
    public function site_locale_assignments_control_available_translation_options(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $before = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $before->assertOk();
        $before->assertDontSee('German');

        $updateSite = $this->actingAs($user)->put(route('admin.sites.update', $site), [
            'name' => $site->name,
            'handle' => $site->handle,
            'domain' => $site->domain,
            'is_primary' => 1,
            'locale_ids' => [$this->defaultLocale()->id, $locale->id],
        ]);
        $updateSite->assertRedirect(route('admin.sites.edit', $site));

        $after = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $after->assertOk();
        $after->assertSee('German');
        $after->assertSee(route('admin.pages.translations.create', [$page, $locale]), false);
    }

    #[Test]
    public function sites_and_locales_admin_enforce_primary_and_default_invariants(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $defaultLocale = $this->defaultLocale();

        $secondarySite = $this->actingAs($user)->post(route('admin.sites.store'), [
            'name' => 'Campaign Site',
            'handle' => 'campaign-site',
            'domain' => 'campaign.example.test',
            'is_primary' => 0,
            'locale_ids' => [$defaultLocale->id],
        ]);
        $secondarySite->assertRedirect();

        $secondary = Site::query()->where('handle', 'campaign-site')->firstOrFail();
        $this->assertTrue($site->fresh()->is_primary);
        $this->assertFalse($secondary->fresh()->is_primary);

        $localeResponse = $this->actingAs($user)->post(route('admin.locales.store'), [
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => 1,
            'is_enabled' => 1,
        ]);
        $localeResponse->assertRedirect();

        $turkish = Locale::query()->where('code', 'tr')->firstOrFail();
        $this->assertTrue($turkish->fresh()->is_default);
        $this->assertFalse($defaultLocale->fresh()->is_default);
        $this->assertTrue($turkish->fresh()->is_enabled);

        $disableDefault = $this->actingAs($user)->put(route('admin.locales.update', $turkish), [
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => 1,
            'is_enabled' => 0,
        ]);
        $disableDefault->assertRedirect(route('admin.locales.edit', $turkish));
        $this->assertTrue($turkish->fresh()->is_enabled);
    }

    #[Test]
    public function preview_links_resolve_for_default_and_non_default_locale_translations(): void
    {
        $user = User::factory()->create();
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $locale->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertSee($page->publicUrl(), false);
        $response->assertSee($page->publicUrl('tr'), false);
        $response->assertSee('/tr/p/hakkinda', false);
    }

    #[Test]
    public function preview_links_do_not_render_for_non_routable_locale_requests(): void
    {
        $user = User::factory()->create();
        $site = $this->defaultSite();
        $locale = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $this->assertNull($page->publicUrl('de'));
        $this->assertNull($page->publicPath('de'));

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertDontSee('/de/p/about');
    }

    #[Test]
    public function pages_list_supports_filtering_and_sorting(): void
    {
        $user = User::factory()->create();

        $about = Page::create([
            'title' => 'About Us',
            'slug' => 'about-us',
            'status' => 'published',
        ]);
        $contact = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'draft',
        ]);
        $services = Page::create([
            'title' => 'Services',
            'slug' => 'services',
            'status' => 'published',
        ]);

        $filtered = $this->actingAs($user)->get(route('admin.pages.index', [
            'search' => 'about',
            'status' => 'published',
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $filtered->assertOk();
        $filtered->assertSee('About Us');
        $filtered->assertSee(route('admin.pages.edit', $about), false);
        $filtered->assertDontSee(route('admin.pages.edit', $contact), false);
        $filtered->assertDontSee(route('admin.pages.edit', $services), false);
        $filtered->assertSee('Search');
        $filtered->assertSee('Sort by');

        $sorted = $this->actingAs($user)->get(route('admin.pages.index', [
            'sort' => 'title',
            'direction' => 'asc',
        ]));

        $sorted->assertOk();
        $sorted->assertSeeInOrder(['About Us', 'Contact', 'Services']);
        $sorted->assertSee('Clear');

        unset($about, $contact, $services);
    }

    #[Test]
    public function navigation_auto_block_renders_navigation_items_from_the_selected_location(): void
    {
        $header = $this->slotType('header', 'Header', 1);
        $footer = $this->slotType('footer', 'Footer', 4);
        $navigationAutoType = BlockType::query()->firstOrCreate(
            ['slug' => 'navigation-auto'],
            ['name' => 'Navigation Auto', 'source_type' => 'navigation', 'status' => 'published', 'sort_order' => 58, 'is_system' => true]
        );

        $home = Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);
        $about = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $contact = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageSlot::create([
            'page_id' => $home->id,
            'slot_type_id' => $header->id,
            'sort_order' => 0,
        ]);
        PageSlot::create([
            'page_id' => $home->id,
            'slot_type_id' => $footer->id,
            'sort_order' => 1,
        ]);

        Block::create([
            'page_id' => $home->id,
            'type' => 'navigation-auto',
            'block_type_id' => $navigationAutoType->id,
            'source_type' => 'navigation',
            'slot' => 'header',
            'slot_type_id' => $header->id,
            'sort_order' => 0,
            'settings' => json_encode(['menu_key' => 'primary'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);
        Block::create([
            'page_id' => $home->id,
            'type' => 'navigation-auto',
            'block_type_id' => $navigationAutoType->id,
            'source_type' => 'navigation',
            'slot' => 'footer',
            'slot_type_id' => $footer->id,
            'sort_order' => 1,
            'settings' => json_encode(['menu_key' => 'footer'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);

        $support = NavigationItem::create([
            'menu_key' => 'primary',
            'title' => 'Support',
            'link_type' => 'custom_url',
            'url' => '/support',
            'position' => 3,
            'visibility' => 'visible',
        ]);

        NavigationItem::create([
            'menu_key' => 'primary',
            'title' => 'Home',
            'link_type' => 'page',
            'page_id' => $home->id,
            'position' => 1,
            'visibility' => 'visible',
        ]);
        NavigationItem::create([
            'menu_key' => 'primary',
            'title' => 'About',
            'link_type' => 'page',
            'page_id' => $about->id,
            'position' => 2,
            'visibility' => 'visible',
        ]);
        NavigationItem::create([
            'menu_key' => 'primary',
            'title' => 'Ignored',
            'link_type' => 'custom_url',
            'url' => '/ignored',
            'position' => 9,
            'visibility' => 'hidden',
        ]);
        NavigationItem::create([
            'menu_key' => 'primary',
            'title' => 'Child Link',
            'link_type' => 'custom_url',
            'url' => '/support/docs',
            'parent_id' => $support->id,
            'position' => 1,
            'visibility' => 'visible',
        ]);
        NavigationItem::create([
            'menu_key' => 'footer',
            'title' => 'Contact',
            'link_type' => 'page',
            'page_id' => $contact->id,
            'position' => 1,
            'visibility' => 'visible',
        ]);

        $response = $this->get(route('pages.show', 'home'));

        $response->assertOk();
        $response->assertSee('Support');
        $response->assertSee('Child Link');
        $response->assertSee('Contact');
        $response->assertDontSee('Ignored');
        $response->assertSee('/support', false);
        $response->assertSee('/support/docs', false);
        $response->assertSee($contact->publicPath(), false);
    }

    #[Test]
    public function navigation_admin_screen_is_available_and_navigation_auto_is_grouped_as_a_system_block(): void
    {
        $user = User::factory()->create();
        $page = Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);
        $navigationAutoType = BlockType::query()->firstOrCreate(
            ['slug' => 'navigation-auto'],
            ['name' => 'Navigation Auto', 'source_type' => 'navigation', 'status' => 'published', 'sort_order' => 58, 'is_system' => true, 'description' => 'Renders navigation items assigned to a system location such as header or footer.']
        );

        $navigation = $this->actingAs($user)->post(route('admin.navigation.store'), [
            'menu_key' => 'primary',
            'title' => '',
            'link_type' => 'page',
            'page_id' => $page->id,
            'url' => '',
            'target' => '_self',
            'visibility' => 'visible',
        ]);

        $navigation->assertRedirect(route('admin.navigation.index', ['site_id' => $page->site_id, 'menu_key' => 'primary']));
        $this->assertDatabaseHas('navigation_items', [
            'menu_key' => 'primary',
            'title' => 'Home',
            'page_id' => $page->id,
            'visibility' => 'visible',
        ]);

        $picker = $this->actingAs($user)->get(route('admin.blocks.create', ['page_id' => $page->id, 'block_type_id' => $navigationAutoType->id]));

        $picker->assertOk();
        $picker->assertSee('System Blocks');
        $picker->assertSee('Content Blocks');
        $picker->assertSee('Navigation Auto');
        $picker->assertSee('System Block');
        $picker->assertSee('Renders navigation items assigned to a system location such as header or footer.');
        $picker->assertSee('Menu');
        $picker->assertSee('System Block');
        $picker->assertSee('Renders navigation items assigned to the selected menu.');

        $index = $this->actingAs($user)->get(route('admin.navigation.index', ['site_id' => $page->site_id, 'menu_key' => 'primary']));
        $index->assertOk();
        $index->assertSee('Navigation Items');
        $index->assertSee('Home');
        $index->assertSee('Primary');
        $index->assertSee('Visible');
    }

    #[Test]
    public function slot_blocks_screen_lists_only_blocks_for_the_selected_slot(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'sort_order' => 1,
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Main block',
            'status' => 'published',
            'is_system' => false,
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'sidebar',
            'slot_type_id' => $sidebar->id,
            'sort_order' => 1,
            'title' => 'Sidebar block',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot]));

        $response->assertOk();
        $response->assertSee('Edit Slot: Main (About)');
        $response->assertSee('Main block');
        $response->assertDontSee('Sidebar block');
        $response->assertSee('Add Block');
        $response->assertSee(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1]), false);
        $response->assertDontSee('id="slot-block-picker-modal"', false);
        $response->assertDontSee('Search block types');
        $response->assertDontSee('View Page');
        $response->assertDontSee('Manage the blocks assigned to this slot');
    }

    #[Test]
    public function slot_blocks_screen_collapses_child_rows_by_default_and_can_render_expanded_state(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'status' => 'published',
            'is_system' => true,
        ]);

        $child = Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start with meaningful defaults.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $collapsed = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot]));

        $collapsed->assertOk();
        $collapsed->assertSee('Children: 1 item');
        $collapsed->assertSee('aria-expanded="false"', false);
        $collapsed->assertSee('data-wb-slot-block-children="slot-block-children-'.$columns->id.'"', false);
        $collapsed->assertSee('hidden', false);
        $collapsed->assertSee(route('admin.pages.slots.blocks', [$page, $mainSlot, 'edit' => $child->id]), false);

        $expanded = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => (string) $columns->id]));

        $expanded->assertOk();
        $expanded->assertSee('aria-expanded="true"', false);
        $expanded->assertSee('data-wb-slot-block-children="slot-block-children-'.$columns->id.'"', false);
        $expanded->assertSee('Edit child block');
        $expanded->assertSee('data-base-url="'.route('admin.pages.slots.blocks', [$page, $mainSlot, 'edit' => $child->id]).'"', false);
    }

    #[Test]
    public function columns_admin_form_exposes_variant_choices(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $columnsType->id]));

        $response->assertOk();
        $response->assertSee('Columns Variant');
        $response->assertSee('Cards');
        $response->assertSee('Plain');
        $response->assertSee('Stats');
        $response->assertDontSee('Links');
    }

    #[Test]
    public function slot_blocks_screen_opens_picker_and_modal_without_using_a_separate_create_page(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'description' => 'Section builder']
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $pickerResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1]));

        $pickerResponse->assertOk();
        $pickerResponse->assertSee('Block Types');
        $pickerResponse->assertSee('Search block types');
        $pickerResponse->assertSee('Recommended');
        $pickerResponse->assertSee('All block types');
        $pickerResponse->assertSee('id="slot-block-picker-modal"', false);
        $pickerResponse->assertSee('role="dialog"', false);
        $pickerResponse->assertSee('aria-modal="true"', false);
        $pickerResponse->assertSee('wb-list-item wb-list-item-action', false);
        $pickerResponse->assertDontSee('Choose a block type, then complete its form in a modal.');
        $pickerResponse->assertDontSee('Keep building in this slot without leaving the page.');
        $pickerResponse->assertDontSee('id="slot-block-editor-modal"', false);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $sectionType->id]));

        $response->assertOk();
        $response->assertDontSee('id="slot-block-picker-modal"', false);
        $response->assertSee('Add Block: Section (About / Main)');
        $response->assertSee('id="slot-block-editor-modal"', false);
        $response->assertSee('wb-overlay-root', false);
        $response->assertSee('wb-modal-header', false);
        $response->assertSee('Block Info');
        $response->assertSee('Block Fields');
        $response->assertSee('Save New Block');
        $response->assertDontSee('Choose a block type, then complete its form in a modal.');
        $response->assertDontSee('Keep building in this slot without leaving the page.');
    }

    #[Test]
    public function hero_admin_form_is_available(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $heroType = BlockType::query()->where('slug', 'hero')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $heroType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Hero (About / Main)');
        $response->assertSee('Eyebrow / Label');
        $response->assertSee('Title');
        $response->assertSee('Subtitle / Intro');
        $response->assertSee('Primary CTA Label');
        $response->assertSee('Primary CTA URL');
        $response->assertSee('Secondary CTA Label');
        $response->assertSee('Secondary CTA URL');
        $response->assertSee('Title Tag');
        $response->assertSee('Shared Fields');
        $response->assertSee('Translated Fields');
        $response->assertDontSee('Generic Block Form');
    }

    #[Test]
    public function feature_grid_and_cta_admin_forms_are_available(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $featureGridType = BlockType::query()->where('slug', 'feature-grid')->firstOrFail();
        $ctaType = BlockType::query()->where('slug', 'cta')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $featureResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $featureGridType->id]));
        $featureResponse->assertOk();
        $featureResponse->assertSee('Add Block: Feature Grid (About / Main)');
        $featureResponse->assertSee('Feature Items');
        $featureResponse->assertSee('Add Feature');
        $featureResponse->assertDontSee('Generic Block Form');

        $ctaResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $ctaType->id]));
        $ctaResponse->assertOk();
        $ctaResponse->assertSee('Add Block: CTA (About / Main)');
        $ctaResponse->assertSee('Primary CTA Label');
        $ctaResponse->assertSee('Secondary CTA URL');
        $ctaResponse->assertSee('Shared Fields');
        $ctaResponse->assertDontSee('Generic Block Form');
    }

    #[Test]
    public function hero_block_store_creates_translation_backed_copy_and_managed_ctas(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $main = $this->slotType('main', 'Main', 2);
        $heroType = BlockType::query()->where('slug', 'hero')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $heroType->id,
            'sort_order' => 0,
            'subtitle' => 'Eyebrow',
            'title' => 'Hero title',
            'content' => 'Hero intro',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/p/contact',
            'secondary_cta_label' => 'Read docs',
            'secondary_cta_url' => '/p/docs',
            'variant' => 'soft',
            'layout' => 'centered',
            'title_tag' => 'h2',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $hero = Block::query()->where('page_id', $page->id)->where('type', 'hero')->firstOrFail();
        $buttons = Block::query()->where('parent_id', $hero->id)->where('type', 'button')->orderBy('sort_order')->get();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $hero->id]));
        $this->assertDatabaseHas('blocks', [
            'id' => $hero->id,
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'variant' => 'soft',
        ]);
        $this->assertTextTranslation($hero, $this->defaultLocale()->id, [
            'title' => 'Hero title',
            'subtitle' => 'Eyebrow',
            'content' => 'Hero intro',
        ]);
        $this->assertCount(2, $buttons);
        $this->assertSame('/p/contact', $buttons[0]->url);
        $this->assertSame('/p/docs', $buttons[1]->url);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $buttons[0]->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Get started',
        ]);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $buttons[1]->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Read docs',
        ]);
        $this->assertSame('centered', $hero->setting('layout'));
        $this->assertSame('h2', $hero->setting('title_tag'));
    }

    #[Test]
    public function feature_grid_store_creates_translation_backed_copy_and_managed_feature_items(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $main = $this->slotType('main', 'Main', 2);
        $featureGridType = BlockType::query()->where('slug', 'feature-grid')->firstOrFail();
        $featureItemType = BlockType::query()->where('slug', 'feature-item')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $featureGridType->id,
            'sort_order' => 0,
            'subtitle' => 'Highlights',
            'title' => 'Core features',
            'content' => 'Use child feature items for each card.',
            'feature_items' => [
                [
                    'block_type_id' => $featureItemType->id,
                    'title' => 'Fast setup',
                    'content' => 'Publish reusable marketing cards.',
                    'url' => '/p/setup',
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 0,
                    '_delete' => 0,
                ],
                [
                    'block_type_id' => $featureItemType->id,
                    'title' => 'Editorial control',
                    'content' => 'Each card is translation-backed content.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 1,
                    '_delete' => 0,
                ],
            ],
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $featureGrid = Block::query()->where('page_id', $page->id)->where('type', 'feature-grid')->firstOrFail();
        $items = Block::query()->where('parent_id', $featureGrid->id)->where('type', 'feature-item')->orderBy('sort_order')->get();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $featureGrid->id]));
        $this->assertDatabaseHas('blocks', [
            'id' => $featureGrid->id,
            'title' => null,
            'subtitle' => null,
            'content' => null,
        ]);
        $this->assertTextTranslation($featureGrid, $this->defaultLocale()->id, [
            'title' => 'Core features',
            'subtitle' => 'Highlights',
            'content' => 'Use child feature items for each card.',
        ]);
        $this->assertCount(2, $items);
        $this->assertSame('/p/setup', $items[0]->url);
        $this->assertTextTranslation($items[0], $this->defaultLocale()->id, [
            'title' => 'Fast setup',
            'content' => 'Publish reusable marketing cards.',
        ]);
        $this->assertTextTranslation($items[1], $this->defaultLocale()->id, [
            'title' => 'Editorial control',
            'content' => 'Each card is translation-backed content.',
        ]);
    }

    #[Test]
    public function cta_block_store_creates_translation_backed_copy_and_managed_buttons(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $main = $this->slotType('main', 'Main', 2);
        $ctaType = BlockType::query()->where('slug', 'cta')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $ctaType->id,
            'sort_order' => 0,
            'subtitle' => 'Ready to ship',
            'title' => 'Try WebBlocks CMS',
            'content' => 'Launch a reusable marketing section with managed actions.',
            'primary_cta_label' => 'Get started',
            'primary_cta_url' => '/p/contact',
            'secondary_cta_label' => 'Read docs',
            'secondary_cta_url' => '/p/docs',
            'variant' => 'accent',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $cta = Block::query()->where('page_id', $page->id)->where('type', 'cta')->firstOrFail();
        $buttons = Block::query()->where('parent_id', $cta->id)->where('type', 'button')->orderBy('sort_order')->get();

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $cta->id]));
        $this->assertDatabaseHas('blocks', [
            'id' => $cta->id,
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'variant' => 'accent',
        ]);
        $this->assertTextTranslation($cta, $this->defaultLocale()->id, [
            'title' => 'Try WebBlocks CMS',
            'subtitle' => 'Ready to ship',
            'content' => 'Launch a reusable marketing section with managed actions.',
        ]);
        $this->assertCount(2, $buttons);
        $this->assertSame('/p/contact', $buttons[0]->url);
        $this->assertSame('/p/docs', $buttons[1]->url);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $buttons[0]->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Get started',
        ]);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $buttons[1]->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Read docs',
        ]);
    }

    #[Test]
    public function hero_block_locale_update_only_changes_translated_fields_and_keeps_shared_settings(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $heroType = BlockType::query()->where('slug', 'hero')->firstOrFail();
        $buttonType = BlockType::query()->where('slug', 'button')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $hero = Block::create([
            'page_id' => $page->id,
            'type' => 'hero',
            'block_type_id' => $heroType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero title',
            'subtitle' => 'Eyebrow',
            'content' => 'Hero intro',
            'variant' => 'soft',
            'settings' => json_encode(['layout' => 'centered'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => true,
        ]);
        $hero->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero title',
            'subtitle' => 'Eyebrow',
            'content' => 'Hero intro',
        ]);

        $primary = Block::create([
            'page_id' => $page->id,
            'parent_id' => $hero->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Get started',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);
        $primary->buttonTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Get started',
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $hero), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $heroType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'subtitle' => 'Yerel etiket',
            'title' => 'Turkce kahraman',
            'content' => 'Turkce giris',
            'primary_cta_label' => 'Baslayin',
            'primary_cta_url' => '/should-not-change',
            'secondary_cta_label' => '',
            'secondary_cta_url' => '',
            'variant' => 'accent',
            'layout' => 'left',
            'status' => 'published',
            'locale' => 'tr',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $hero->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $slot, 'expanded' => $hero->id, 'locale' => 'tr']));
        $this->assertTextTranslation($hero, $turkish->id, [
            'title' => 'Turkce kahraman',
            'subtitle' => 'Yerel etiket',
            'content' => 'Turkce giris',
        ]);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $primary->id,
            'locale_id' => $turkish->id,
            'title' => 'Baslayin',
        ]);
        $this->assertDatabaseHas('blocks', [
            'id' => $primary->id,
            'url' => '/p/contact',
            'variant' => 'primary',
        ]);
        $this->assertDatabaseHas('blocks', [
            'id' => $hero->id,
            'variant' => 'soft',
        ]);
        $this->assertSame('centered', $hero->fresh()->setting('layout'));
    }

    #[Test]
    public function list_table_and_related_content_admin_forms_are_available(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $listResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => BlockType::query()->where('slug', 'list')->value('id')]));

        $listResponse->assertOk();
        $listResponse->assertSee('Add Block: List (About / Main)');
        $listResponse->assertSee('List Title');
        $listResponse->assertSee('List Style');
        $listResponse->assertSee('Enter one item per line.');
        $listResponse->assertDontSee('Generic Block Form');

        $tableResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => BlockType::query()->where('slug', 'table')->value('id')]));

        $tableResponse->assertOk();
        $tableResponse->assertSee('Add Block: Table (About / Main)');
        $tableResponse->assertSee('Table Title');
        $tableResponse->assertSee('Table Style');
        $tableResponse->assertSee('Separate cells with a vertical bar');
        $tableResponse->assertDontSee('Generic Block Form');

        $relatedContentResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => BlockType::query()->where('slug', 'link-list')->value('id')]));

        $relatedContentResponse->assertOk();
        $relatedContentResponse->assertSee('Add Block: Link List (About / Main)');
        $relatedContentResponse->assertSee('Eyebrow');
        $relatedContentResponse->assertSee('Heading');
        $relatedContentResponse->assertSee('Intro Text');
        $relatedContentResponse->assertSee('Link List Items');
        $relatedContentResponse->assertDontSee('Generic Block Form');

        $codeResponse = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => BlockType::query()->where('slug', 'code')->value('id')]));

        $codeResponse->assertOk();
        $codeResponse->assertSee('Add Block: Code (About / Main)');
        $codeResponse->assertSee('Filename / Language Label');
        $codeResponse->assertSee('Syntax Language');
        $relatedContentResponse->assertDontSee('Generic Block Form');
        $codeResponse->assertDontSee('Generic Block Form');
    }

    #[Test]
    public function link_list_item_block_is_rejected_when_parent_is_not_link_list(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $linkListItemType = BlockType::query()->where('slug', 'link-list-item')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $section = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Section',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.slots.blocks', [$page, $mainSlot]))
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'parent_id' => $section->id,
                'block_type_id' => $linkListItemType->id,
                'slot_type_id' => $main->id,
                'sort_order' => 1,
                'title' => 'Getting Started',
                'subtitle' => 'Guide',
                'content' => 'Basics and setup',
                'url' => '/docs/start',
                'status' => 'published',
                '_slot_block_mode' => 'create',
            ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot]));
        $response->assertSessionHasErrors('parent_id');
        $this->assertDatabaseMissing('blocks', [
            'page_id' => $page->id,
            'parent_id' => $section->id,
            'type' => 'link-list-item',
            'url' => '/docs/start',
        ]);
    }

    #[Test]
    public function accordion_admin_form_is_available(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $accordionType = BlockType::query()->where('slug', 'accordion')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $accordionType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Accordion (About / Main)');
        $response->assertSee('Accordion Title');
        $response->assertSee('Add child FAQ or content blocks as accordion items.');
        $response->assertDontSee('Generic Block Form');
    }

    #[Test]
    public function quote_admin_form_exposes_testimonial_variant(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $quoteType = BlockType::query()->where('slug', 'quote')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'picker' => 1, 'block_type_id' => $quoteType->id]));

        $response->assertOk();
        $response->assertSee('Add Block: Quote (About / Main)');
        $response->assertSee('Quote Variant');
        $response->assertSee('Testimonial');
        $response->assertSee('Use Testimonial when the quote should render as a framed social-proof card.');
        $response->assertDontSee('Generic Block Form');
    }

    #[Test]
    public function storing_a_block_from_slot_screen_redirects_back_to_the_same_slot_screen(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 0,
            'title' => 'Intro section',
            'content' => 'Slot-first flow',
            'status' => 'published',
            'is_system' => 0,
            '_slot_block_mode' => 'create',
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot]));
        $this->assertDatabaseHas('blocks', [
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'title' => null,
        ]);
        $block = Block::query()->where('page_id', $page->id)->where('slot_type_id', $main->id)->firstOrFail();
        $this->assertTextTranslation($block, $this->defaultLocale()->id, [
            'title' => 'Intro section',
            'content' => 'Slot-first flow',
        ]);
    }

    #[Test]
    public function page_builder_store_persists_default_locale_content_only_in_translation_rows(): void
    {
        $user = User::factory()->create();
        $site = $this->defaultSite();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );

        $response = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'slots' => [
                ['slot_type_id' => $main->id],
            ],
            'blocks' => [
                [
                    'block_type_id' => $sectionType->id,
                    'slot_type_id' => $main->id,
                    'title' => 'Inline hero',
                    'content' => 'Inline builder copy',
                    'status' => 'published',
                ],
            ],
        ]);

        $response->assertSessionDoesntHaveErrors();

        $page = Page::query()->latest('id')->firstOrFail();
        $block = Block::query()->where('page_id', $page->id)->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'title' => null,
            'content' => null,
        ]);
        $this->assertTextTranslation($block, $this->defaultLocale()->id, [
            'title' => 'Inline hero',
            'content' => 'Inline builder copy',
        ]);
    }

    #[Test]
    public function page_edit_can_update_slots_without_touching_existing_blocks(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $existingSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $existing = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Old title',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.pages.update', $page), [
            'title' => 'About Updated',
            'slug' => 'about',
            'slots' => [
                [
                    'id' => $existingSlot->id,
                    'slot_type_id' => $main->id,
                ],
                [
                    'slot_type_id' => $sidebar->id,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $this->assertDatabaseHas('pages', ['id' => $page->id, 'status' => 'draft']);
        $this->assertDatabaseHas('page_translations', ['page_id' => $page->id, 'name' => 'About Updated', 'slug' => 'about']);
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $main->id, 'sort_order' => 0]);
        $this->assertDatabaseHas('page_slots', ['page_id' => $page->id, 'slot_type_id' => $sidebar->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('blocks', ['id' => $existing->id, 'slot_type_id' => $main->id]);
        $this->assertSame(1, Block::query()->where('page_id', $page->id)->count());
    }

    #[Test]
    public function page_edit_shows_compact_slot_block_previews_for_filled_and_empty_slots(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sidebar = $this->slotType('sidebar', 'Sidebar', 3);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $buttonType = BlockType::query()->firstOrCreate(
            ['slug' => 'button'],
            ['name' => 'Button', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2]
        );
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $richTextType = BlockType::query()->firstOrCreate(
            ['slug' => 'rich-text'],
            ['name' => 'Rich Text', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 3]
        );
        $imageType = BlockType::query()->firstOrCreate(
            ['slug' => 'image'],
            ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 4]
        );
        $page = Page::create([
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'draft',
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'sort_order' => 1,
        ]);

        Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Get Started',
            'status' => 'published',
            'is_system' => false,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 2,
            'title' => 'Features',
            'status' => 'published',
            'is_system' => true,
        ]);
        Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start fast.',
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Flexible',
            'content' => 'Stay flexible.',
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::create([
            'page_id' => $page->id,
            'type' => 'rich-text',
            'block_type_id' => $richTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 3,
            'title' => null,
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::create([
            'page_id' => $page->id,
            'type' => 'image',
            'block_type_id' => $imageType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 4,
            'title' => 'Preview image',
            'status' => 'published',
            'is_system' => false,
        ]);
        Block::create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 5,
            'title' => 'Secondary CTA',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.edit', $page));

        $response->assertOk();
        $response->assertSee('Section');
        $response->assertSee('Button');
        $response->assertSee('Columns (2 items)');
        $response->assertSee('Rich Text');
        $response->assertSee('Image');
        $response->assertSee('+1 more');
        $response->assertSee('No blocks yet');
        $response->assertDontSee('Fast setup');
        $response->assertDontSee('Flexible');
    }

    #[Test]
    public function pages_index_restores_details_drawer_with_page_metadata(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $page = Page::create([
            'title' => 'Slot Preview Check',
            'slug' => 'slot-preview-check',
            'status' => 'draft',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $buttonType = BlockType::query()->firstOrCreate(
            ['slug' => 'button'],
            ['name' => 'Button', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 10]
        );

        Block::create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Primary CTA',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.index'));

        $response->assertOk();
        $response->assertSee('Details');
        $response->assertSee('pageDetailsDrawer-'.$page->id, false);
        $response->assertSee('Page Details');
        $response->assertSee('Name');
        $response->assertSee('Slot Preview Check');
        $response->assertSee('Slug');
        $response->assertSee('slot-preview-check');
        $response->assertSee('Path');
        $response->assertSee($page->publicPath(), false);
        $response->assertSee('Default URL');
        $response->assertSee($page->publicUrl(), false);
        $response->assertSee('Slot count');
        $response->assertSee('1');
        $response->assertSee('Block count');
        $response->assertSee('Edit Slots');
        $response->assertSee(route('admin.pages.edit', $page), false);
    }

    #[Test]
    public function privileged_page_edit_screens_show_view_page_action_and_success_messages_include_preview_link(): void
    {
        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'status' => 'published',
            'is_system' => false,
        ]);

        $pageEdit = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $pageEdit->assertOk();
        $pageEdit->assertSee($page->publicUrl(), false);
        $pageEdit->assertSee('View Page');
        $pageEdit->assertSee('target="_blank"', false);
        $pageEdit->assertSee('rel="noopener noreferrer"', false);

        $slotBlocks = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot]));
        $slotBlocks->assertOk();
        $slotBlocks->assertSee($page->publicUrl(), false);
        $slotBlocks->assertSee('View Page');

        $pageUpdate = $this->actingAs($user)->from(route('admin.pages.edit', $page))->put(route('admin.pages.update', $page), [
            'title' => 'About',
            'slug' => 'about',
            'slots' => [
                ['id' => $mainSlot->id, 'slot_type_id' => $main->id],
            ],
        ]);
        $pageUpdate->assertRedirect(route('admin.pages.edit', $page));

        $pageFollowUp = $this->actingAs($user)->get(route('admin.pages.edit', $page));
        $pageFollowUp->assertSee('Page updated successfully.');
        $pageFollowUp->assertSee('View page');
        $pageFollowUp->assertSee($page->publicUrl(), false);

        $blockUpdate = $this->actingAs($user)->from(route('admin.pages.slots.blocks', [$page, $mainSlot]))->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'Updated',
            'status' => 'published',
            'is_system' => 0,
        ]);
        $blockUpdate->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot]));

        $blockFollowUp = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot]));
        $blockFollowUp->assertSee('Block updated successfully.');
        $blockFollowUp->assertSee('View page');
        $blockFollowUp->assertSee($page->publicUrl(), false);
    }

    #[Test]
    public function columns_block_editor_shows_child_item_management(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'status' => 'published',
            'is_system' => true,
        ]);

        Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start with meaningful defaults.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'edit' => $columns->id]));

        $response->assertOk();
        $response->assertSee('Column Items');
        $response->assertSee('Add Column');
        $response->assertSee('Fast setup');
    }

    #[Test]
    public function updating_columns_block_can_create_update_and_delete_column_items(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'status' => 'published',
            'is_system' => true,
        ]);
        $existingItem = Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start with meaningful defaults.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $columns), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $columnsType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'subtitle' => 'Real child blocks',
            'content' => 'Manage each visible card below.',
            'variant' => 'stats',
            'status' => 'published',
            'is_system' => 1,
            'column_items' => [
                [
                    'id' => $existingItem->id,
                    'block_type_id' => $columnItemType->id,
                    'title' => 'Flexible content',
                    'content' => 'Update structure and content with child blocks.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 0,
                    '_delete' => 0,
                ],
                [
                    'id' => null,
                    'block_type_id' => $columnItemType->id,
                    'title' => 'Editor friendly',
                    'content' => 'Editors can add, remove, and reorder items.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 1,
                    '_delete' => 0,
                ],
            ],
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $columns->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => (string) $columns->id]));
        $this->assertDatabaseHas('blocks', ['id' => $columns->id, 'variant' => 'stats']);
        $this->assertTextTranslation($existingItem, $this->defaultLocale()->id, ['title' => 'Flexible content']);
        $newItem = Block::query()->where('parent_id', $columns->id)->where('type', 'column_item')->whereKeyNot($existingItem->id)->firstOrFail();
        $this->assertTextTranslation($newItem, $this->defaultLocale()->id, ['title' => 'Editor friendly']);
        $this->assertSame(2, Block::query()->where('parent_id', $columns->id)->where('type', 'column_item')->count());
    }

    #[Test]
    public function updating_feature_grid_can_create_update_and_delete_feature_items(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $featureGridType = BlockType::query()->where('slug', 'feature-grid')->firstOrFail();
        $featureItemType = BlockType::query()->where('slug', 'feature-item')->firstOrFail();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $featureGrid = Block::create([
            'page_id' => $page->id,
            'type' => 'feature-grid',
            'block_type_id' => $featureGridType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'status' => 'published',
            'is_system' => false,
        ]);
        $existingItem = Block::create([
            'page_id' => $page->id,
            'parent_id' => $featureGrid->id,
            'type' => 'feature-item',
            'block_type_id' => $featureItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start with meaningful defaults.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $featureGrid), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $featureGridType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'subtitle' => 'Real child blocks',
            'content' => 'Manage each visible feature below.',
            'status' => 'published',
            'feature_items' => [
                [
                    'id' => $existingItem->id,
                    'block_type_id' => $featureItemType->id,
                    'title' => 'Flexible content',
                    'content' => 'Update structure and content with child blocks.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 0,
                    '_delete' => 0,
                ],
                [
                    'id' => null,
                    'block_type_id' => $featureItemType->id,
                    'title' => 'Editor friendly',
                    'content' => 'Editors can add, remove, and reorder feature items.',
                    'url' => '/p/editor',
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 1,
                    '_delete' => 0,
                ],
            ],
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $featureGrid->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => (string) $featureGrid->id]));
        $this->assertTextTranslation($existingItem, $this->defaultLocale()->id, ['title' => 'Flexible content']);
        $newItem = Block::query()->where('parent_id', $featureGrid->id)->where('type', 'feature-item')->whereKeyNot($existingItem->id)->firstOrFail();
        $this->assertTextTranslation($newItem, $this->defaultLocale()->id, ['title' => 'Editor friendly']);
        $this->assertSame('/p/editor', $newItem->url);
        $this->assertSame(2, Block::query()->where('parent_id', $featureGrid->id)->where('type', 'feature-item')->count());
    }

    #[Test]
    public function cta_block_locale_update_only_changes_translated_fields_and_keeps_shared_buttons(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $ctaType = BlockType::query()->where('slug', 'cta')->firstOrFail();
        $buttonType = BlockType::query()->where('slug', 'button')->firstOrFail();
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $cta = Block::create([
            'page_id' => $page->id,
            'type' => 'cta',
            'block_type_id' => $ctaType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Try WebBlocks CMS',
            'subtitle' => 'Ready to ship',
            'content' => 'Launch a reusable marketing section with managed actions.',
            'variant' => 'accent',
            'status' => 'published',
            'is_system' => false,
        ]);
        $cta->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Try WebBlocks CMS',
            'subtitle' => 'Ready to ship',
            'content' => 'Launch a reusable marketing section with managed actions.',
        ]);

        $primary = Block::create([
            'page_id' => $page->id,
            'parent_id' => $cta->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Get started',
            'url' => '/p/contact',
            'variant' => 'primary',
            'status' => 'published',
            'is_system' => false,
        ]);
        $primary->buttonTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Get started',
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $cta), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $ctaType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'subtitle' => 'Yerel etiket',
            'title' => 'WebBlocks CMS deneyin',
            'content' => 'Yerel tanitim metni',
            'primary_cta_label' => 'Baslayin',
            'primary_cta_url' => '/should-not-change',
            'secondary_cta_label' => '',
            'secondary_cta_url' => '',
            'variant' => 'soft',
            'status' => 'published',
            'locale' => 'tr',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $cta->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $slot, 'expanded' => $cta->id, 'locale' => 'tr']));
        $this->assertTextTranslation($cta, $turkish->id, [
            'title' => 'WebBlocks CMS deneyin',
            'subtitle' => 'Yerel etiket',
            'content' => 'Yerel tanitim metni',
        ]);
        $this->assertDatabaseHas('block_button_translations', [
            'block_id' => $primary->id,
            'locale_id' => $turkish->id,
            'title' => 'Baslayin',
        ]);
        $this->assertDatabaseHas('blocks', [
            'id' => $primary->id,
            'url' => '/p/contact',
            'variant' => 'primary',
        ]);
        $this->assertDatabaseHas('blocks', [
            'id' => $cta->id,
            'variant' => 'accent',
        ]);
    }

    #[Test]
    public function slot_block_redirects_preserve_expanded_parent_after_edit_add_and_reorder(): void
    {
        $user = User::factory()->create();
        $main = $this->slotType('main', 'Main', 2);
        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 50, 'is_system' => true]
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 51, 'is_system' => true]
        );
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'draft',
        ]);
        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $columns = Block::create([
            'page_id' => $page->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'content' => 'Manage each visible card below.',
            'status' => 'published',
            'is_system' => true,
        ]);
        $childA = Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Start with meaningful defaults.',
            'status' => 'published',
            'is_system' => false,
        ]);
        $childB = Block::create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Flexible content',
            'content' => 'Build pages from reusable slots and blocks.',
            'status' => 'published',
            'is_system' => false,
        ]);

        $expanded = (string) $columns->id;

        $editResponse = $this->actingAs($user)->put(route('admin.blocks.update', $columns), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $columnsType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'subtitle' => null,
            'content' => 'Updated content',
            'status' => 'published',
            'is_system' => 1,
            'column_items' => [
                [
                    'id' => $childA->id,
                    'block_type_id' => $columnItemType->id,
                    'title' => 'Fast setup',
                    'content' => 'Start with meaningful defaults.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 0,
                    '_delete' => 0,
                ],
                [
                    'id' => $childB->id,
                    'block_type_id' => $columnItemType->id,
                    'title' => 'Flexible content',
                    'content' => 'Build pages from reusable slots and blocks.',
                    'url' => null,
                    'status' => 'published',
                    'is_system' => 0,
                    'sort_order' => 1,
                    '_delete' => 0,
                ],
            ],
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $columns->id,
            'expanded' => $expanded,
        ]);

        $editResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $expanded]));

        $addResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'block_type_id' => $columnItemType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 2,
            'title' => 'Editor friendly',
            'content' => 'Editors can add, remove, and reorder items.',
            'status' => 'published',
            'is_system' => 0,
            'expanded' => $expanded,
            '_slot_block_mode' => 'create',
        ]);

        $addResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $expanded]));
        $addedChild = Block::query()->where('parent_id', $columns->id)->where('sort_order', 2)->firstOrFail();
        $this->assertTextTranslation($addedChild, $this->defaultLocale()->id, ['title' => 'Editor friendly']);

        $reorderResponse = $this->actingAs($user)->post(route('admin.blocks.move-up', $childB), [
            'expanded' => $expanded,
        ]);

        $reorderResponse->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'expanded' => $expanded]));
        $this->assertSame(0, $childB->fresh()->sort_order);
        $this->assertSame(1, $childA->fresh()->sort_order);
    }

    #[Test]
    public function slot_block_editor_can_edit_translated_block_content_in_locale_context(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        $mainSlot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'English content',
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero',
            'content' => 'English content',
        ]);

        $response = $this->actingAs($user)->get(route('admin.pages.slots.blocks', [$page, $mainSlot, 'locale' => 'tr', 'edit' => $block->id]));

        $response->assertOk();
        $response->assertSee('Editing content for TR');
        $response->assertSee('Fallback');

        $update = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Kahraman',
            'content' => 'Turkce icerik',
            'status' => 'published',
            'locale' => 'tr',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $update->assertRedirect(route('admin.pages.slots.blocks', [$page, $mainSlot, 'locale' => 'tr']));

        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $turkish->id,
            'title' => 'Kahraman',
            'content' => 'Turkce icerik',
        ]);

        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'title' => null,
            'content' => null,
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Hero',
            'content' => 'English content',
        ]);
    }

    #[Test]
    public function default_locale_slot_block_edit_updates_only_the_default_translation_row(): void
    {
        $user = User::factory()->create();
        $page = Page::create([
            'title' => 'About',
            'slug' => 'about-default-sync',
            'status' => 'draft',
        ]);
        $main = $this->slotType('main', 'Main', 2);
        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'Original copy',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Updated hero',
            'content' => 'Updated default copy',
            'status' => 'published',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $slot]));
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'title' => null,
            'content' => null,
        ]);
        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Updated hero',
            'content' => 'Updated default copy',
        ]);
    }

    #[Test]
    public function page_builder_block_writes_and_block_controller_writes_persist_the_same_translation_shape(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $defaultLocale = $this->defaultLocale();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );

        $pageBuilderResponse = $this->actingAs($user)->post(route('admin.pages.store'), [
            'site_id' => $site->id,
            'title' => 'Builder Page',
            'slug' => 'builder-page',
            'slots' => [
                ['slot_type_id' => $main->id],
            ],
            'blocks' => [
                [
                    'block_type_id' => $sectionType->id,
                    'slot_type_id' => $main->id,
                    'title' => 'Shared hero',
                    'content' => 'Shared English copy',
                    'status' => 'published',
                ],
            ],
        ]);

        $pageBuilderResponse->assertSessionDoesntHaveErrors();

        $builderPage = Page::query()->latest('id')->firstOrFail();
        $builderSlot = PageSlot::query()->where('page_id', $builderPage->id)->where('slot_type_id', $main->id)->firstOrFail();
        $builderBlock = Block::query()->where('page_id', $builderPage->id)->firstOrFail();
        $pageBuilderResponse->assertRedirect(route('admin.pages.edit', $builderPage));

        $controllerPage = Page::create([
            'site_id' => $site->id,
            'title' => 'Controller Page',
            'slug' => 'controller-page',
            'status' => 'draft',
        ]);
        $controllerSlot = PageSlot::create([
            'page_id' => $controllerPage->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $blockStoreResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
            'page_id' => $controllerPage->id,
            'slot_type_id' => $main->id,
            'block_type_id' => $sectionType->id,
            'sort_order' => 0,
            'title' => 'Shared hero',
            'content' => 'Shared English copy',
            'status' => 'published',
            '_slot_block_mode' => 'create',
        ]);

        $controllerBlock = Block::query()->where('page_id', $controllerPage->id)->firstOrFail();
        $blockStoreResponse->assertRedirect(route('admin.pages.slots.blocks', [$controllerPage, $controllerSlot]));

        foreach ([$builderBlock, $controllerBlock] as $block) {
            $this->actingAs($user)->put(route('admin.blocks.update', $block), [
                'page_id' => $block->page_id,
                'parent_id' => null,
                'block_type_id' => $sectionType->id,
                'slot_type_id' => $main->id,
                'sort_order' => 0,
                'title' => 'Turkce kahraman',
                'content' => 'Turkce icerik',
                'status' => 'published',
                'locale' => 'tr',
                '_slot_block_mode' => 'edit',
                '_slot_block_id' => $block->id,
            ])->assertRedirect();
        }

        $this->assertSame(
            $this->blockPersistenceShape($builderBlock->fresh(['textTranslations']), $defaultLocale, $turkish),
            $this->blockPersistenceShape($controllerBlock->fresh(['textTranslations']), $defaultLocale, $turkish),
        );
        $this->assertTextTranslation($builderBlock, $defaultLocale->id, [
            'title' => 'Shared hero',
            'content' => 'Shared English copy',
        ]);
        $this->assertTextTranslation($controllerBlock, $defaultLocale->id, [
            'title' => 'Shared hero',
            'content' => 'Shared English copy',
        ]);
    }

    #[Test]
    public function slot_block_editor_rejects_locales_that_are_not_enabled_for_the_page_site(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $german = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_default' => false,
            'is_enabled' => true,
        ]);

        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Hero',
            'content' => 'English content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.slots.blocks', [$page, $slot, 'locale' => 'de', 'edit' => $block->id]))
            ->assertNotFound();

        $response = $this->actingAs($user)->from(route('admin.pages.slots.blocks', [$page, $slot]))->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $sectionType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Held',
            'content' => 'German content',
            'status' => 'published',
            'locale' => 'de',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $response->assertRedirect(route('admin.pages.slots.blocks', [$page, $slot]));
        $response->assertSessionHasErrors('locale');
    }

    #[Test]
    public function page_translation_routes_cannot_be_created_for_locales_not_enabled_on_the_page_site(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $german = Locale::query()->create([
            'code' => 'de',
            'name' => 'German',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        $this->actingAs($user)
            ->get(route('admin.pages.translations.create', [$page, $german]))
            ->assertNotFound();

        $this->actingAs($user)
            ->post(route('admin.pages.translations.store', [$page, $german]), [
                'name' => 'Info',
                'slug' => 'info',
            ])
            ->assertNotFound();
    }

    #[Test]
    public function contact_form_translations_keep_delivery_settings_shared_and_text_fields_localized(): void
    {
        $user = User::factory()->superAdmin()->create();
        $site = $this->defaultSite();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $main = $this->slotType('main', 'Main', 2);
        $contactType = BlockType::query()->firstOrCreate(
            ['slug' => 'contact_form'],
            ['name' => 'Contact Form', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 10]
        );

        $page = Page::create([
            'site_id' => $site->id,
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => 'Iletisim',
            'slug' => 'iletisim',
            'path' => '/p/iletisim',
        ]);

        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $contactType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Contact us',
            'content' => 'English intro',
            'settings' => json_encode([
                'submit_label' => 'Send message',
                'success_message' => 'Thanks for your message.',
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $contactType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'heading' => 'Bize ulasin',
            'intro_text' => 'Turkce tanitim',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler',
            'recipient_email' => 'ignored-change@example.com',
            'send_email_notification' => 0,
            'status' => 'published',
            'locale' => 'tr',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $this->assertStringContainsString('/admin/pages/'.$page->id.'/slots/'.$slot->id.'/blocks?locale=tr', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $turkish->id,
            'title' => 'Bize ulasin',
            'content' => 'Turkce tanitim',
            'submit_label' => 'Mesaj gonder',
            'success_message' => 'Tesekkurler',
        ]);
        $this->assertDatabaseHas('blocks', [
            'id' => $block->id,
            'title' => null,
            'content' => null,
        ]);
        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact us',
            'content' => 'English intro',
        ]);

        $settings = json_decode((string) $block->fresh()->getRawOriginal('settings'), true);

        $this->assertArrayNotHasKey('submit_label', $settings);
        $this->assertArrayNotHasKey('success_message', $settings);
        $this->assertSame('team@example.com', $settings['recipient_email']);
        $this->assertTrue($settings['send_email_notification']);
    }

    #[Test]
    public function default_locale_contact_form_edits_store_copy_in_translation_rows_not_json_settings(): void
    {
        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 2);
        $contactType = BlockType::query()->firstOrCreate(
            ['slug' => 'contact_form'],
            ['name' => 'Contact Form', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 10]
        );

        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        $slot = PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $contactType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Old title',
            'content' => 'Old intro',
            'settings' => json_encode([
                'recipient_email' => 'team@example.com',
                'send_email_notification' => true,
                'store_submissions' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)->put(route('admin.blocks.update', $block), [
            'page_id' => $page->id,
            'parent_id' => null,
            'block_type_id' => $contactType->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'heading' => 'Contact us',
            'intro_text' => 'English intro',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message.',
            'recipient_email' => 'editor@example.com',
            'send_email_notification' => 1,
            'store_submissions' => 0,
            'status' => 'published',
            '_slot_block_mode' => 'edit',
            '_slot_block_id' => $block->id,
        ]);

        $this->assertStringContainsString('/admin/pages/'.$page->id.'/slots/'.$slot->id.'/blocks', (string) $response->headers->get('Location'));
        $this->assertDatabaseHas('block_contact_form_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Contact us',
            'content' => 'English intro',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message.',
        ]);

        $settings = json_decode((string) $block->fresh()->getRawOriginal('settings'), true);

        $this->assertArrayNotHasKey('submit_label', $settings);
        $this->assertArrayNotHasKey('success_message', $settings);
        $this->assertSame('editor@example.com', $settings['recipient_email']);
        $this->assertTrue($settings['send_email_notification']);
        $this->assertFalse($settings['store_submissions']);
    }

    #[Test]
    public function standalone_admin_block_edit_form_uses_default_translation_values_when_canonical_fields_are_null(): void
    {
        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 2);
        $contactType = BlockType::query()->firstOrCreate(
            ['slug' => 'contact_form'],
            ['name' => 'Contact Form', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 10]
        );

        $page = Page::create([
            'title' => 'Contact',
            'slug' => 'contact',
            'status' => 'published',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $block = Block::create([
            'page_id' => $page->id,
            'type' => 'contact_form',
            'block_type_id' => $contactType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Contact us',
            'content' => 'English intro',
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
            'content' => 'English intro',
            'submit_label' => 'Send message',
            'success_message' => 'Thanks for your message.',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['contactFormTranslations']));

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->get(route('admin.blocks.edit', $block));

        $response->assertOk();
        $response->assertSee('value="Contact us"', false);
        $response->assertSee('English intro', false);
        $response->assertSee('Send message');
    }

    #[Test]
    public function standalone_admin_block_edit_parent_options_use_resolved_translation_labels_when_canonical_fields_are_null(): void
    {
        $user = User::factory()->superAdmin()->create();
        $main = $this->slotType('main', 'Main', 2);
        $sectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
        );
        $buttonType = BlockType::query()->firstOrCreate(
            ['slug' => 'button'],
            ['name' => 'Button', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2]
        );

        $page = Page::create([
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageSlot::create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'sort_order' => 0,
        ]);

        $parent = Block::create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'title' => 'Parent heading',
            'content' => 'Parent body',
            'status' => 'published',
            'is_system' => false,
        ]);

        $parent->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Parent heading',
            'content' => 'Parent body',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($parent->fresh(['textTranslations']));

        $child = Block::create([
            'page_id' => $page->id,
            'type' => 'button',
            'block_type_id' => $buttonType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 1,
            'title' => 'Child action',
            'url' => 'https://example.test',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->actingAs($user)
            ->followingRedirects()
            ->get(route('admin.blocks.edit', $child));

        $response->assertOk();
        $response->assertSee('Parent heading');
    }

    #[Test]
    public function starter_content_seed_creates_real_columns_children_without_duplicates(): void
    {
        $this->seed(CoreCatalogSeeder::class);
        $this->seed(StarterContentSeeder::class);
        $this->seed(StarterContentSeeder::class);

        $home = Page::query()
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', $this->defaultLocale()->id)
                ->where('slug', 'home'))
            ->firstOrFail();
        $columns = Block::query()
            ->where('page_id', $home->id)
            ->where('type', 'columns')
            ->whereHas('textTranslations', fn ($query) => $query->where('title', 'Starter features'))
            ->first();

        $this->assertNotNull($columns);
        $this->assertDatabaseCount('pages', 5);
        $this->assertDatabaseHas('sites', ['handle' => 'campaign', 'domain' => 'campaign.ddev.site']);
        $this->assertDatabaseHas('page_translations', ['page_id' => $home->site->pages()->whereHas('translations', fn ($query) => $query->where('slug', 'about'))->value('id'), 'slug' => 'about', 'name' => 'About']);
        $this->assertDatabaseHas('page_translations', ['slug' => 'about', 'name' => 'Campaign About']);
        $this->assertSame(3, Block::query()->where('parent_id', $columns->id)->where('type', 'column_item')->count());
        $children = Block::query()->where('parent_id', $columns->id)->where('type', 'column_item')->with('textTranslations')->get();
        $this->assertSame(
            ['Editor friendly', 'Fast setup', 'Flexible content'],
            $children->map(fn (Block $block) => $block->textTranslations->firstWhere('locale_id', $this->defaultLocale()->id)?->title)->sort()->values()->all()
        );
    }

    #[Test]
    public function columns_text_children_can_be_upgraded_to_column_items_for_existing_data(): void
    {
        $this->seed(BlockTypeSeeder::class);

        $page = Page::query()->create([
            'title' => 'Legacy Columns Page',
            'slug' => 'legacy-columns-page',
            'status' => 'published',
        ]);
        $slotType = $this->slotType('main', 'Main', 1);
        $columnsType = BlockType::query()->where('slug', 'columns')->firstOrFail();
        $textType = BlockType::query()->where('slug', 'text')->firstOrFail();
        $columnItemType = BlockType::query()->where('slug', 'column_item')->firstOrFail();

        $columns = Block::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'slot' => $slotType->slug,
            'block_type_id' => $columnsType->id,
            'type' => 'columns',
            'source_type' => 'static',
            'sort_order' => 0,
            'title' => 'Starter features',
            'status' => 'published',
            'is_system' => false,
        ]);

        $legacyChild = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $columns->id,
            'slot_type_id' => $slotType->id,
            'slot' => $slotType->slug,
            'block_type_id' => $textType->id,
            'type' => 'text',
            'source_type' => 'static',
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'Legacy child block',
            'status' => 'published',
            'is_system' => false,
        ]);

        DB::table('blocks')
            ->join('blocks as parents', 'parents.id', '=', 'blocks.parent_id')
            ->where('parents.type', 'columns')
            ->where('blocks.type', 'text')
            ->update([
                'blocks.block_type_id' => $columnItemType->id,
                'blocks.type' => 'column_item',
                'blocks.source_type' => $columnItemType->source_type ?? 'static',
                'blocks.updated_at' => now(),
            ]);

        $legacyChild->refresh();

        $this->assertSame('column_item', $legacyChild->type);
        $this->assertSame($columnItemType->id, $legacyChild->block_type_id);
        $this->assertSame('Fast setup', $legacyChild->title);
        $this->assertSame('Legacy child block', $legacyChild->content);
    }
}
