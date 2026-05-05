<?php

namespace Tests\Feature\Admin;

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
use App\Support\Pages\PageSiteMover;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PageSiteMoveTest extends TestCase
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
        return Locale::query()->create([
            'code' => $code,
            'name' => strtoupper($code),
            'is_default' => false,
            'is_enabled' => true,
        ]);
    }

    private function slotType(string $slug): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => ucfirst($slug), 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function pageWithContent(Site $site, string $title = 'About', string $slug = 'about'): Page
    {
        $main = $this->slotType('main');
        $sidebar = $this->slotType('sidebar');
        $page = Page::query()->create([
            'site_id' => $site->id,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);

        PageTranslation::query()->create([
            'page_id' => $page->id,
            'site_id' => $site->id,
            'locale_id' => $this->defaultLocale()->id,
            'name' => $title,
            'slug' => $slug,
            'path' => '/p/'.$slug,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $main->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebar->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 1,
        ]);

        $blockType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'plain_text',
            'block_type_id' => $blockType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $main->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Body',
            'content' => 'Body copy',
        ]);

        return $page->fresh(['translations.locale', 'slots.slotType', 'blocks.textTranslations']);
    }

    #[Test]
    public function super_admin_can_open_the_move_page_to_site_screen(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());
        $otherSite = $this->createSite('secondary', 'secondary.example.test');

        $response = $this->actingAs($user)->get(route('admin.pages.move-site.create', $page));

        $response->assertOk();
        $response->assertSee('Move Page to Another Site');
        $response->assertSee('Move to another site');
        $response->assertSee($otherSite->name);
    }

    #[Test]
    public function editor_cannot_see_or_use_the_move_action(): void
    {
        $this->seedFoundation();

        $page = $this->pageWithContent($this->defaultSite());
        $editor = User::factory()->editor()->create();
        $editor->sites()->sync([$page->site_id]);

        $this->actingAs($editor)
            ->get(route('admin.pages.edit', $page))
            ->assertDontSee('Move to another site');

        $this->actingAs($editor)
            ->get(route('admin.pages.move-site.create', $page))
            ->assertForbidden();

        $targetSite = $this->createSite('secondary', 'secondary.example.test');

        $this->actingAs($editor)
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id])
            ->assertForbidden();
    }

    #[Test]
    public function site_admin_can_move_only_between_assigned_source_and_target_sites(): void
    {
        $this->seedFoundation();

        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $blockedSite = $this->createSite('blocked', 'blocked.example.test');
        $page = $this->pageWithContent($sourceSite);

        $siteAdmin = User::factory()->siteAdmin()->create();
        $siteAdmin->sites()->sync([$sourceSite->id, $targetSite->id]);

        $this->actingAs($siteAdmin)
            ->get(route('admin.pages.move-site.create', $page))
            ->assertOk()
            ->assertSee($targetSite->name)
            ->assertDontSee($blockedSite->name);

        $this->actingAs($siteAdmin)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $blockedSite->id])
            ->assertRedirect(route('admin.pages.move-site.create', $page))
            ->assertSessionHasErrors('target_site_id');
    }

    #[Test]
    public function target_site_is_required_and_must_differ_from_source(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $page = $this->pageWithContent($this->defaultSite());

        $this->actingAs($user)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), [])
            ->assertRedirect(route('admin.pages.move-site.create', $page))
            ->assertSessionHasErrors('target_site_id');

        $this->actingAs($user)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $page->site_id])
            ->assertRedirect(route('admin.pages.move-site.create', $page))
            ->assertSessionHasErrors([
                'target_site_id' => 'Choose a different target site.',
            ]);
    }

    #[Test]
    public function successful_move_updates_page_and_translation_site_scope_without_duplication(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($this->defaultSite());
        $turkish = $this->createLocale('tr');
        $this->defaultSite()->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page->translations()->create([
            'site_id' => $page->site_id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/tr/p/hakkinda',
        ]);
        $targetSite->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);

        $slotCount = $page->slots()->count();
        $blockCount = $page->blocks()->count();

        $response = $this->actingAs($user)
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id]);

        $response->assertRedirect(route('admin.pages.edit', $page));
        $response->assertSessionHas('status', 'Page moved to "'.$targetSite->name.'".');

        $this->assertSame($targetSite->id, $page->fresh()->site_id);
        $this->assertSame(1, Page::query()->whereKey($page->id)->count());
        $this->assertSame($slotCount, $page->fresh()->slots()->count());
        $this->assertSame($blockCount, $page->fresh()->blocks()->count());
        $this->assertDatabaseHas('page_translations', ['page_id' => $page->id, 'site_id' => $targetSite->id, 'slug' => 'about']);
        $this->assertDatabaseHas('page_translations', ['page_id' => $page->id, 'site_id' => $targetSite->id, 'slug' => 'hakkinda']);
        $this->assertDatabaseMissing('page_translations', ['page_id' => $page->id, 'site_id' => $this->defaultSite()->id, 'slug' => 'about']);
        $this->assertDatabaseHas('block_text_translations', ['block_id' => $page->blocks()->firstOrFail()->id, 'title' => 'Body']);
        $this->assertDatabaseHas('page_revisions', ['page_id' => $page->id, 'site_id' => $targetSite->id, 'label' => 'Page moved to another site']);
    }

    #[Test]
    public function moving_page_fails_clearly_when_target_site_has_same_path(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($this->defaultSite(), 'About', 'about');
        $conflictPage = $this->pageWithContent($targetSite, 'Existing', 'about');

        $response = $this->actingAs($user)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id]);

        $response->assertRedirect(route('admin.pages.move-site.create', $page));
        $response->assertSessionHasErrors('target_site_id');
        $this->assertSame($this->defaultSite()->id, $page->fresh()->site_id);
        $this->assertSame($targetSite->id, $conflictPage->fresh()->site_id);
    }

    #[Test]
    public function moving_page_fails_clearly_when_target_site_locales_are_incompatible(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($this->defaultSite());
        $turkish = $this->createLocale('tr');
        $this->defaultSite()->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
        $page->translations()->create([
            'site_id' => $page->site_id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/tr/p/hakkinda',
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id]);

        $response->assertRedirect(route('admin.pages.move-site.create', $page));
        $response->assertSessionHasErrors('target_site_id');
        $this->assertSame($this->defaultSite()->id, $page->fresh()->site_id);
    }

    #[Test]
    public function moving_page_with_compatible_same_handle_shared_slots_remaps_the_slot(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($sourceSite);
        $sidebarSlotType = $this->slotType('sidebar');
        $sourceSharedSlot = SharedSlot::query()->create([
            'site_id' => $sourceSite->id,
            'name' => 'Docs Sidebar',
            'handle' => 'docs-sidebar',
            'slot_name' => 'sidebar',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        $targetSharedSlot = SharedSlot::query()->create([
            'site_id' => $targetSite->id,
            'name' => 'Docs Sidebar',
            'handle' => 'docs-sidebar',
            'slot_name' => 'sidebar',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $slot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $sidebarSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => $sourceSharedSlot->id,
            'sort_order' => 2,
        ]);

        $this->actingAs($user)
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id])
            ->assertRedirect(route('admin.pages.edit', $page));

        $this->assertSame($targetSharedSlot->id, $slot->fresh()->shared_slot_id);
    }

    #[Test]
    public function moving_page_with_missing_target_shared_slot_fails_clearly_and_leaves_database_unchanged(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        $sourceSite = $this->defaultSite();
        $targetSite = $this->createSite('secondary', 'secondary.example.test');
        $page = $this->pageWithContent($sourceSite);

        $slot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotType('sidebar')->id,
            'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
            'shared_slot_id' => SharedSlot::query()->create([
                'site_id' => $sourceSite->id,
                'name' => 'Docs Sidebar',
                'handle' => 'docs-sidebar',
                'slot_name' => 'sidebar',
                'public_shell' => 'docs',
                'is_active' => true,
            ])->id,
            'sort_order' => 2,
        ]);

        $response = $this->actingAs($user)
            ->from(route('admin.pages.move-site.create', $page))
            ->post(route('admin.pages.move-site.store', $page), ['target_site_id' => $targetSite->id]);

        $response->assertRedirect(route('admin.pages.move-site.create', $page));
        $response->assertSessionHasErrors('target_site_id');
        $this->assertSame($sourceSite->id, $page->fresh()->site_id);
        $this->assertSame($sourceSite->id, $slot->fresh()->sharedSlot->site_id);
    }

    #[Test]
    public function successful_move_does_not_trigger_query_exception(): void
    {
        $this->seedFoundation();

        $page = $this->pageWithContent($this->defaultSite());
        $targetSite = $this->createSite('secondary', 'secondary.example.test');

        try {
            app(PageSiteMover::class)->move($page, $targetSite, User::factory()->superAdmin()->create());
            $this->addToAssertionCount(1);
        } catch (QueryException $exception) {
            $this->fail('Expected page move to avoid QueryException, got: '.$exception->getMessage());
        }
    }
}
