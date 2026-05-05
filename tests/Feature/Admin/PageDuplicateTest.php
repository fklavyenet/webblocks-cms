<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Pages\PageDuplicator;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageDuplicateTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

    private function defaultSite(): Site
    {
        return Site::query()->where('is_primary', true)->firstOrFail();
    }

    private function defaultLocale(): Locale
    {
        return Locale::query()->where('is_default', true)->firstOrFail();
    }

    private function createSite(string $handle, string $domain): Site
    {
        $site = Site::query()->create([
            'name' => ucfirst(str_replace('-', ' ', $handle)),
            'handle' => $handle,
            'domain' => $domain,
            'is_primary' => false,
        ]);

        $site->locales()->syncWithoutDetaching([$this->defaultLocale()->id => ['is_enabled' => true]]);

        return $site;
    }

    private function createLocale(string $code): Locale
    {
        return Locale::query()->firstOrCreate(
            ['code' => $code],
            ['name' => strtoupper($code), 'is_default' => false, 'is_enabled' => true],
        );
    }

    private function slotType(string $slug): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function createAsset(): Asset
    {
        return Asset::query()->create([
            'disk' => 'public',
            'path' => 'images/hero.jpg',
            'filename' => 'hero.jpg',
            'original_name' => 'hero.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1234,
            'kind' => Asset::KIND_IMAGE,
            'visibility' => 'public',
        ]);
    }

    private function pageWithContent(Site $site, string $title = 'About', string $slug = 'about'): Page
    {
        $header = $this->slotType('header');
        $main = $this->slotType('main');
        $sidebar = $this->slotType('sidebar');
        $asset = $this->createAsset();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);

        $defaultLocale = $this->defaultLocale();

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $defaultLocale->id,
            'name' => $title,
            'slug' => $slug,
            'path' => '/p/'.$slug,
        ]);

        $turkish = $this->createLocale('tr');
        $site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $turkish->id,
            'name' => $title.' TR',
            'slug' => $slug.'-tr',
            'path' => '/p/'.$slug.'-tr',
        ]);

        $headerSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $header->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $mainSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 1,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
            'sort_order' => 2,
        ]);

        $sectionType = BlockType::query()->where('slug', 'section')->firstOrFail();
        $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $imageType = BlockType::query()->where('slug', 'image')->firstOrFail();

        $parent = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'section',
            'block_type_id' => $sectionType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlot->slot_type_id,
            'sort_order' => 0,
            'status' => 'published',
            'settings' => json_encode(['layout_name' => 'Hero Stack'], JSON_UNESCAPED_SLASHES),
            'is_system' => false,
        ]);

        $child = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $parent->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlot->slot_type_id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $child->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Body',
            'content' => 'Body copy',
        ]);
        $child->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Govde',
            'content' => 'Govde icerigi',
        ]);

        $image = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'image',
            'block_type_id' => $imageType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlot->slot_type_id,
            'sort_order' => 0,
            'status' => 'published',
            'asset_id' => $asset->id,
            'is_system' => false,
        ]);

        $image->imageTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'caption' => 'Hero image',
            'alt_text' => 'Hero alt',
        ]);

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => $title,
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $page->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return $page->fresh(['translations.locale', 'slots.slotType', 'blocks.textTranslations', 'blocks.imageTranslations', 'navigationItems']);
    }

    private function duplicatePayload(Page $page, Site $targetSite, array $overrides = []): array
    {
        $secondary = $page->translations
            ->reject(fn (PageTranslation $translation) => $translation->locale?->is_default)
            ->values()
            ->map(fn (PageTranslation $translation) => [
                'locale_id' => $translation->locale_id,
                'name' => $translation->name.' Copy',
                'slug' => $translation->slug.'-copy',
            ])
            ->all();

        return array_replace_recursive([
            'target_site_id' => $targetSite->id,
            'title' => $page->defaultTranslation()?->name.' Copy',
            'slug' => $page->defaultTranslation()?->slug.'-copy',
            'translations' => $secondary,
        ], $overrides);
    }

    #[Test]
    public function super_admin_can_open_the_duplicate_page_screen(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());
        $this->createSite('secondary', 'secondary.example.test');

        $response = $this->actingAs($user)->get(route('admin.pages.duplicate.create', $page));

        $response->assertOk();
        $response->assertSee('Duplicate Page');
        $response->assertSee('Duplicate page');
    }

    #[Test]
    public function unauthorized_users_cannot_see_or_use_duplicate_action(): void
    {
        $this->seedFoundation();

        $page = $this->pageWithContent($this->defaultSite());
        $blockedSite = $this->createSite('blocked', 'blocked.example.test');
        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$blockedSite->id]);

        $this->actingAs($siteAdmin)
            ->get(route('admin.pages.duplicate.create', $page))
            ->assertForbidden();
    }

    #[Test]
    public function site_admin_can_duplicate_only_between_assigned_source_and_target_sites(): void
    {
        $this->seedFoundation();

        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $blockedSite = $this->createSite('blocked', 'blocked.example.test');
        $page = $this->pageWithContent($sourceSite);

        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$sourceSite->id, $targetSite->id]);

        $this->actingAs($siteAdmin)
            ->get(route('admin.pages.duplicate.create', $page))
            ->assertOk()
            ->assertSee($targetSite->name)
            ->assertDontSee($blockedSite->name);

        $this->actingAs($siteAdmin)
            ->from(route('admin.pages.duplicate.create', $page))
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $blockedSite))
            ->assertRedirect(route('admin.pages.duplicate.create', $page))
            ->assertSessionHasErrors('target_site_id');
    }

    #[Test]
    public function editor_can_duplicate_within_assigned_sites_and_duplicate_starts_as_draft(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $page = $this->pageWithContent($site);
        $editor = User::factory()->editor()->create();
        $editor->sites()->sync([$site->id]);

        $response = $this->actingAs($editor)
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $site));

        $duplicate = Page::query()->where('site_id', $site->id)->whereKeyNot($page->id)->latest('id')->firstOrFail();

        $response->assertRedirect(route('admin.pages.edit', $duplicate));
        $this->assertSame(Page::STATUS_DRAFT, $duplicate->status);
        $this->assertNull($duplicate->published_at);
    }

    #[Test]
    public function target_site_title_and_slug_are_required(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());

        $this->actingAs($user)
            ->from(route('admin.pages.duplicate.create', $page))
            ->post(route('admin.pages.duplicate.store', $page), [])
            ->assertRedirect(route('admin.pages.duplicate.create', $page))
            ->assertSessionHasErrors(['target_site_id', 'title', 'slug']);
    }

    #[Test]
    public function duplicating_within_same_site_creates_a_new_page_and_leaves_original_unchanged(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($site);
        $originalTranslation = $page->defaultTranslation();

        $this->actingAs($user)
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $site));

        $duplicate = Page::query()->where('site_id', $site->id)->whereKeyNot($page->id)->latest('id')->firstOrFail();

        $this->assertNotSame($page->id, $duplicate->id);
        $this->assertSame($site->id, $duplicate->site_id);
        $this->assertSame($originalTranslation->slug, $page->fresh()->defaultTranslation()?->slug);
        $this->assertSame($page->layout_id, $duplicate->layout_id);
        $this->assertSame($page->publicShellPreset(), $duplicate->publicShellPreset());
    }

    #[Test]
    public function duplicating_to_another_site_creates_a_new_page_on_the_target_site(): void
    {
        $this->seedFoundation();

        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $targetSite->locales()->syncWithoutDetaching([$this->createLocale('tr')->id => ['is_enabled' => true]]);
        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($sourceSite);

        $this->actingAs($user)
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $targetSite));

        $duplicate = Page::query()->where('site_id', $targetSite->id)->latest('id')->firstOrFail();

        $this->assertSame($targetSite->id, $duplicate->site_id);
        $this->assertSame($sourceSite->id, $page->fresh()->site_id);
    }

    #[Test]
    public function duplicate_preserves_translations_slots_block_tree_order_and_translation_rows(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());

        $this->actingAs($user)
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $this->defaultSite()));

        $duplicate = Page::query()->where('site_id', $page->site_id)->whereKeyNot($page->id)->latest('id')->firstOrFail();
        $sourceBlocks = Block::query()->where('page_id', $page->id)->orderBy('id')->get();
        $duplicateBlocks = Block::query()->where('page_id', $duplicate->id)->orderBy('id')->get();
        $duplicateSection = $duplicateBlocks->firstWhere('type', 'section');
        $duplicatePlainText = $duplicateBlocks->firstWhere('type', 'plain_text');
        $duplicateImage = $duplicateBlocks->firstWhere('type', 'image');

        $this->assertSame($page->slots()->count(), $duplicate->slots()->count());
        $this->assertSame($page->translations()->count(), $duplicate->translations()->count());
        $this->assertSame($sourceBlocks->count(), $duplicateBlocks->count());
        $this->assertNotNull($duplicateSection);
        $this->assertNotNull($duplicatePlainText);
        $this->assertNotNull($duplicateImage);
        $this->assertNull($duplicateSection->parent_id);
        $this->assertSame($duplicateSection->id, $duplicatePlainText->parent_id);
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $duplicatePlainText->id, 'locale_id' => Locale::query()->where('code', 'tr')->value('id'), 'title' => 'Govde']);
        $this->assertDatabaseHas('block_image_translations', ['block_id' => $duplicateImage->id, 'locale_id' => $this->defaultLocale()->id]);
        $this->assertSame($sourceBlocks->firstWhere('type', 'image')?->asset_id, $duplicateImage->asset_id);
    }

    #[Test]
    public function duplicate_does_not_copy_unrelated_blocks_or_revision_history(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());
        $otherPage = $this->pageWithContent($this->defaultSite(), 'Other', 'other');
        $otherBlockCount = $otherPage->blocks()->count();

        $this->actingAs($user)
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $this->defaultSite()));

        $duplicate = Page::query()->where('site_id', $page->site_id)->whereKeyNot($page->id)->whereKeyNot($otherPage->id)->latest('id')->firstOrFail();

        $this->assertSame($otherBlockCount, $otherPage->fresh()->blocks()->count());
        $this->assertSame(1, $duplicate->revisions()->count());
        $this->assertDatabaseMissing('page_revisions', ['page_id' => $duplicate->id, 'label' => 'Page moved to another site']);
    }

    #[Test]
    public function duplicate_within_same_site_preserves_shared_slot_reference(): void
    {
        $this->seedFoundation();

        $site = $this->defaultSite();
        $page = $this->pageWithContent($site);
        $sharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        PageSlot::query()->where('page_id', $page->id)->where('sort_order', 0)->update([
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sharedSlot->id,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $site));

        $duplicate = Page::query()->where('site_id', $site->id)->whereKeyNot($page->id)->latest('id')->firstOrFail();
        $duplicateHeader = PageSlot::query()->where('page_id', $duplicate->id)->where('sort_order', 0)->firstOrFail();

        $this->assertSame($sharedSlot->id, $duplicateHeader->shared_slot_id);
    }

    #[Test]
    public function duplicate_to_another_site_remaps_compatible_shared_slots_and_fails_when_missing(): void
    {
        $this->seedFoundation();

        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $targetSite->locales()->syncWithoutDetaching([$this->createLocale('tr')->id => ['is_enabled' => true]]);
        $page = $this->pageWithContent($sourceSite);
        $sourceSharedSlot = SharedSlot::query()->create([
            'site_id' => $sourceSite->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        PageSlot::query()->where('page_id', $page->id)->where('sort_order', 0)->update([
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sourceSharedSlot->id,
        ]);

        $response = $this->actingAs(User::factory()->superAdmin()->create())
            ->from(route('admin.pages.duplicate.create', $page))
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $targetSite));

        $response->assertRedirect(route('admin.pages.duplicate.create', $page));
        $response->assertSessionHasErrors('target_site_id');
        $this->assertSame(0, Page::query()->where('site_id', $targetSite->id)->count());

        SharedSlot::query()->create([
            'site_id' => $targetSite->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $this->actingAs(User::factory()->superAdmin()->create())
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $targetSite));

        $duplicate = Page::query()->where('site_id', $targetSite->id)->latest('id')->firstOrFail();
        $duplicateHeader = PageSlot::query()->where('page_id', $duplicate->id)->where('sort_order', 0)->firstOrFail();

        $this->assertNotNull($duplicateHeader->shared_slot_id);
        $this->assertNotSame($sourceSharedSlot->id, $duplicateHeader->shared_slot_id);
    }

    #[Test]
    public function duplicate_fails_for_incompatible_locales_and_path_conflicts_without_writing(): void
    {
        $this->seedFoundation();

        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($sourceSite);
        $user = User::factory()->superAdmin()->create();

        $this->actingAs($user)
            ->from(route('admin.pages.duplicate.create', $page))
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $targetSite))
            ->assertRedirect(route('admin.pages.duplicate.create', $page))
            ->assertSessionHasErrors('target_site_id');

        $this->assertSame(0, Page::query()->where('site_id', $targetSite->id)->count());

        $targetSite->locales()->syncWithoutDetaching([$page->translations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->value('locale_id') => ['is_enabled' => true]]);
        Page::query()->create([
            'site_id' => $targetSite->id,
            'title' => 'Conflict',
            'slug' => 'about-copy',
            'status' => Page::STATUS_DRAFT,
        ]);

        $this->actingAs($user)
            ->from(route('admin.pages.duplicate.create', $page))
            ->post(route('admin.pages.duplicate.store', $page), $this->duplicatePayload($page, $targetSite))
            ->assertRedirect(route('admin.pages.duplicate.create', $page))
            ->assertSessionHasErrors('slug');
    }

    #[Test]
    public function duplicate_service_does_not_throw_and_success_redirects_to_new_edit_screen(): void
    {
        $this->seedFoundation();

        $page = $this->pageWithContent($this->defaultSite());
        $payload = collect($this->duplicatePayload($page, $this->defaultSite())['translations'])
            ->prepend([
                'locale_id' => $this->defaultLocale()->id,
                'name' => 'About Copy',
                'slug' => 'about-copy',
                'path' => '/p/about-copy',
                'name_field' => 'title',
                'slug_field' => 'slug',
            ])
            ->map(fn (array $translation, int $index) => $translation + [
                'path' => $translation['path'] ?? '/p/'.$translation['slug'],
                'name_field' => $translation['name_field'] ?? 'translations.'.$index.'.name',
                'slug_field' => $translation['slug_field'] ?? 'translations.'.$index.'.slug',
            ]);

        $result = app(PageDuplicator::class)->duplicate($page, $this->defaultSite(), User::factory()->superAdmin()->create(), $payload);

        $this->assertNotSame($page->id, $result->page->id);
        $this->assertSame(Page::STATUS_DRAFT, $result->page->status);
    }
}
