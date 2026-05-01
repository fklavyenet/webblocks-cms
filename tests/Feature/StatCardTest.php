<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Models\User;
use App\Support\Blocks\BlockTranslationWriter;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StatCardTest extends TestCase
{
    use RefreshDatabase;

    private function seedFoundation(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $this->seed(BlockTypeSeeder::class);
    }

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

    private function pageWithSlot(): array
    {
        $site = $this->defaultSite();
        $slotType = $this->slotType();
        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $page->id, 'locale_id' => $this->defaultLocale()->id],
            ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
        );

        $pageSlot = PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        return [$page, $pageSlot, $slotType];
    }

    #[Test]
    public function stat_card_is_present_in_block_type_catalog_after_seeding(): void
    {
        $this->seedFoundation();

        $type = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

        $this->assertSame('Stat Card', $type->name);
        $this->assertSame('published', $type->status);
        $this->assertSame('content', $type->category);
    }

    #[Test]
    public function block_type_seeder_adds_stat_card_to_existing_catalog_without_destructive_reseed(): void
    {
        $this->seed(FoundationSiteLocaleSeeder::class);

        BlockType::query()->create([
            'name' => 'Card',
            'slug' => 'card',
            'category' => 'content',
            'source_type' => 'static',
            'is_system' => false,
            'is_container' => true,
            'sort_order' => 8,
            'status' => 'published',
        ]);

        $this->assertNull(BlockType::query()->where('slug', 'stat-card')->first());

        $this->seed(BlockTypeSeeder::class);

        $type = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

        $this->assertSame('Stat Card', $type->name);
        $this->assertSame('published', $type->status);
    }

    #[Test]
    public function admin_form_can_save_stat_card_with_zero_value_and_summary_displays_zero(): void
    {
        $this->seedFoundation();

        $user = User::factory()->superAdmin()->create();
        [$page, $pageSlot, $slotType] = $this->pageWithSlot();
        $statCardType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

        $this->actingAs($user)
            ->get(route('admin.pages.slots.blocks', [$page, $pageSlot, 'picker' => 1, 'block_type_id' => $statCardType->id]))
            ->assertOk()
            ->assertSee('Add Block: Stat Card')
            ->assertSee('This may be 0, 6, 14+, 173');

        $this->actingAs($user)
            ->post(route('admin.blocks.store'), [
                'page_id' => $page->id,
                'slot_type_id' => $slotType->id,
                'block_type_id' => $statCardType->id,
                'sort_order' => 0,
                'subtitle' => 'Dependencies',
                'title' => '0',
                'content' => 'No framework requirement for the package itself',
                'status' => 'published',
                '_slot_block_mode' => 'create',
            ])
            ->assertRedirect(route('admin.pages.slots.blocks', [$page, $pageSlot]));

        $block = Block::query()->where('page_id', $page->id)->where('type', 'stat-card')->firstOrFail();

        $this->assertDatabaseHas('block_text_translations', [
            'block_id' => $block->id,
            'locale_id' => $this->defaultLocale()->id,
            'title' => '0',
            'subtitle' => 'Dependencies',
            'content' => 'No framework requirement for the package itself',
        ]);
        $this->assertSame('0', $block->fresh()->editorLabel());
        $this->assertSame('0', $block->fresh()->editorSummary());

        $this->actingAs($user)
            ->get(route('admin.pages.slots.blocks', [$page, $pageSlot]))
            ->assertOk()
            ->assertSee('>0<', false);
    }

    #[Test]
    public function translation_row_stores_zero_as_real_string(): void
    {
        $this->seedFoundation();

        [$page, , $slotType] = $this->pageWithSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'stat-card',
            'block_type_id' => BlockType::query()->where('slug', 'stat-card')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        app(BlockTranslationWriter::class)->sync($block, [
            'type' => 'stat-card',
            'subtitle' => 'Dependencies',
            'title' => '0',
            'content' => 'No framework requirement for the package itself',
        ], null, true);
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

        $translation = DB::table('block_text_translations')
            ->where('block_id', $block->id)
            ->where('locale_id', $this->defaultLocale()->id)
            ->first();

        $this->assertNotNull($translation);
        $this->assertSame('0', $translation->title);
    }

    #[Test]
    public function public_rendering_includes_dependencies_zero_and_description(): void
    {
        $this->seedFoundation();

        [$page, , $slotType] = $this->pageWithSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'stat-card',
            'block_type_id' => BlockType::query()->where('slug', 'stat-card')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => '0',
            'subtitle' => 'Dependencies',
            'content' => 'No framework requirement for the package itself',
            'status' => 'published',
            'is_system' => false,
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('Dependencies')
            ->assertSee('>0<', false)
            ->assertSee('No framework requirement for the package itself');
    }

    #[Test]
    public function public_rendering_matches_stat_card_structure_contract(): void
    {
        $this->seedFoundation();

        [$page, , $slotType] = $this->pageWithSlot();

        Block::query()->create([
            'page_id' => $page->id,
            'type' => 'stat-card',
            'block_type_id' => BlockType::query()->where('slug', 'stat-card')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => '173',
            'subtitle' => 'Icons',
            'content' => 'Class-based mask-image icon usage',
            'status' => 'published',
            'is_system' => false,
        ]);

        $response = $this->get(route('pages.show', 'about'));
        $html = $response->getContent();

        $response->assertOk();

        $this->assertElementStructure($html, [
            '.wb-stat' => 'div',
            '.wb-stat .wb-stat-label' => 'div',
            '.wb-stat .wb-stat-value' => 'div',
            '.wb-stat .wb-stat-detail' => 'div',
        ]);
    }
}
