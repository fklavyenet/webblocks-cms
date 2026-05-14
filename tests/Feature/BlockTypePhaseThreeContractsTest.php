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
use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\BlockTypes\BlockTypeContractRegistry;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BlockTypePhaseThreeContractsTest extends TestCase
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

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        return [$page, $slotType];
    }

    #[Test]
    public function code_and_table_are_registered_as_text_translatable_block_types(): void
    {
        $this->seedFoundation();

        $registry = app(BlockTranslationRegistry::class);

        $this->assertSame('text', $registry->familyFor('code'));
        $this->assertSame('text', $registry->familyFor('table'));
    }

    #[Test]
    public function public_code_block_uses_translated_fields_with_shared_language_setting(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'code',
            'block_type_id' => BlockType::query()->where('slug', 'code')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'settings' => json_encode(['language' => 'php'], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Config bootstrap',
            'subtitle' => 'bootstrap.php',
            'content' => '<?php echo $safe; ?>',
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('data-language="php"', false)
            ->assertSee('&lt;?php echo $safe; ?&gt;', false);
    }

    #[Test]
    public function public_table_block_uses_translated_title_and_rows(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'table',
            'block_type_id' => BlockType::query()->where('slug', 'table')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'variant' => 'header-row',
            'status' => 'published',
            'is_system' => false,
        ]);
        $block->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Plans',
            'content' => "Plan | Seats\nStarter | 3",
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('<h3>Plans</h3>', false)
            ->assertSee('<th>Plan</th>', false)
            ->assertSee('<td>Starter</td>', false);
    }

    #[Test]
    public function public_link_list_renders_translated_intro_copy_and_items(): void
    {
        $this->seedFoundation();

        [$page, $slotType] = $this->pageWithSlot();
        $list = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'link-list',
            'block_type_id' => BlockType::query()->where('slug', 'link-list')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $list->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Getting Started',
            'subtitle' => 'Docs',
            'content' => 'Use this section first.',
        ]);
        $item = Block::query()->create([
            'page_id' => $page->id,
            'parent_id' => $list->id,
            'type' => 'link-list-item',
            'block_type_id' => BlockType::query()->where('slug', 'link-list-item')->value('id'),
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'url' => '/getting-started',
            'status' => 'published',
            'is_system' => false,
        ]);
        $item->textTranslations()->create([
            'locale_id' => $this->defaultLocale()->id,
            'title' => 'Overview',
            'subtitle' => 'Setup',
            'content' => 'Shortest correct setup path.',
        ]);

        $this->get(route('pages.show', 'about'))
            ->assertOk()
            ->assertSee('>Docs<', false)
            ->assertSee('<h2>Getting Started</h2>', false)
            ->assertSee('Use this section first.')
            ->assertSee('href="/getting-started"', false)
            ->assertSee('Overview');
    }

    #[Test]
    public function phase_three_contracts_report_the_resolved_block_gaps(): void
    {
        $this->seedFoundation();

        $contracts = app(BlockTypeContractRegistry::class);

        $code = $contracts->resolve('code');
        $table = $contracts->resolve('table');
        $breadcrumb = $contracts->resolve('breadcrumb');
        $statCard = $contracts->resolve('stat-card');
        $linkList = $contracts->resolve('link-list');

        $this->assertSame(['title', 'subtitle', 'content'], $code->translatableFields);
        $this->assertSame('mostly clear', $code->currentContractStatus);
        $this->assertSame(['title', 'content'], $table->translatableFields);
        $this->assertSame('clear', $breadcrumb->currentContractStatus);
        $this->assertSame([], $breadcrumb->knownGaps);
        $this->assertSame('clear', $statCard->currentContractStatus);
        $this->assertSame([], $statCard->knownGaps);
        $this->assertSame('clear', $linkList->currentContractStatus);
        $this->assertSame([], $linkList->knownGaps);
    }
}
