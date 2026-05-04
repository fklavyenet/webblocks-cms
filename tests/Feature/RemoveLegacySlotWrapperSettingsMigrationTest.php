<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RemoveLegacySlotWrapperSettingsMigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function migration_removes_legacy_wrapper_keys_and_preserves_other_slot_settings(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        $site = Site::query()->firstOrFail();
        $slotType = SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => Page::STATUS_DRAFT,
        ]);

        PageSlot::withoutEvents(function () use ($page, $slotType): void {
            PageSlot::query()->insert([
                ['id' => 1, 'page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0, 'settings' => json_encode(['wrapper_element' => 'section', 'wrapper_preset' => 'docs-main', 'custom' => 'keep-me'], JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()],
                ['id' => 2, 'page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 1, 'settings' => json_encode(['wrapper_element' => 'section', 'wrapper_preset' => 'docs-main'], JSON_UNESCAPED_SLASHES), 'created_at' => now(), 'updated_at' => now()],
                ['id' => 3, 'page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 2, 'settings' => null, 'created_at' => now(), 'updated_at' => now()],
            ]);
        });

        $migration = require database_path('migrations/2026_05_04_120000_remove_legacy_slot_wrapper_settings.php');
        $migration->up();

        $slots = PageSlot::query()->orderBy('id')->get()->keyBy('id');

        $this->assertSame(['custom' => 'keep-me'], $slots[1]->settings);
        $this->assertNull($slots[2]->settings);
        $this->assertNull($slots[3]->settings);
    }
}
