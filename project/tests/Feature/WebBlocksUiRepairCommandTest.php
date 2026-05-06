<?php

namespace Project\Tests\Feature;

use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\SharedSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WebBlocksUiRepairCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function repair_command_deletes_only_proven_debug_artifacts_and_restores_docs_shared_slots(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);

        $site = Site::query()->where('handle', 'default')->firstOrFail();
        $headerType = SlotType::query()->where('slug', 'header')->firstOrFail();
        $sidebarType = SlotType::query()->where('slug', 'sidebar')->firstOrFail();
        $mainType = SlotType::query()->where('slug', 'main')->firstOrFail();

        $headerSharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Header',
            'handle' => 'docs-header',
            'slot_name' => 'header',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);
        $sidebarSharedSlot = SharedSlot::query()->create([
            'site_id' => $site->id,
            'name' => 'Docs Sidebar',
            'handle' => 'docs-sidebar',
            'slot_name' => 'sidebar',
            'public_shell' => 'docs',
            'is_active' => true,
        ]);

        $gettingStarted = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))
            ->firstOrFail();

        PageSlot::query()->where('page_id', $gettingStarted->id)->where('slot_type_id', $headerType->id)->update([
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
        ]);
        PageSlot::query()->where('page_id', $gettingStarted->id)->where('slot_type_id', $sidebarType->id)->update([
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'shared_slot_id' => null,
        ]);

        $artifact = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
        ]);
        PageTranslation::query()->create([
            'page_id' => $artifact->id,
            'site_id' => $site->id,
            'locale_id' => Page::defaultLocaleId(),
            'name' => 'About Nested TOC Debug',
            'slug' => 'about-nested-toc-debug',
            'path' => '/p/about-nested-toc-debug',
        ]);
        PageSlot::query()->create([
            'page_id' => $artifact->id,
            'slot_type_id' => $mainType->id,
            'source_type' => PageSlot::SOURCE_TYPE_PAGE,
            'sort_order' => 0,
        ]);

        $empty = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
        ]);

        $this->artisan('project:webblocksui-repair')->assertExitCode(0);

        $repairedSlots = $gettingStarted->fresh(['slots.slotType'])->slots->keyBy(fn ($slot) => $slot->slotType?->slug);

        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $repairedSlots['header']->source_type);
        $this->assertSame($headerSharedSlot->id, $repairedSlots['header']->shared_slot_id);
        $this->assertSame(PageSlot::SOURCE_TYPE_SHARED_SLOT, $repairedSlots['sidebar']->source_type);
        $this->assertSame($sidebarSharedSlot->id, $repairedSlots['sidebar']->shared_slot_id);
        $this->assertDatabaseMissing('pages', ['id' => $artifact->id]);
        $this->assertDatabaseMissing('pages', ['id' => $empty->id]);
    }

    #[Test]
    public function repair_command_is_idempotent_and_keeps_real_docs_pages(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);

        $this->artisan('project:webblocksui-setup-site')->assertExitCode(0);
        $this->artisan('project:webblocksui-repair')->assertExitCode(0);
        $this->artisan('project:webblocksui-repair')->assertExitCode(0);

        $site = Site::query()->where('handle', 'default')->firstOrFail();

        $this->assertSame(1, Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'home'))->count());
        $this->assertSame(1, Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))->count());
    }
}
