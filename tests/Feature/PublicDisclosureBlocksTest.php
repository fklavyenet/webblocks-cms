<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicDisclosureBlocksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function accordion_renders_details_structure(): void
    {
        $page = $this->pageWithMainSlot();
        $accordion = $this->block($page, 'accordion', 'Accordion', 1, [
            'title' => 'Common questions',
            'content' => 'Answers to recurring editorial questions.',
        ]);

        $this->block($page, 'faq', 'FAQ', 2, [
            'parent_id' => $accordion->id,
            'title' => 'How does publishing work?',
            'content' => 'Editors draft and admins publish.',
        ]);
        $this->block($page, 'text', 'Text', 3, [
            'parent_id' => $accordion->id,
            'title' => 'Can I localize content?',
            'content' => 'Yes, translated block text is supported.',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Common questions');
        $response->assertSee('<details>', false);
        $response->assertSee('<summary>How does publishing work?</summary>', false);
        $response->assertSee('Editors draft and admins publish.');
        $response->assertSee('<summary>Can I localize content?</summary>', false);
    }

    #[Test]
    public function accordion_skips_empty_items(): void
    {
        $page = $this->pageWithMainSlot();
        $accordion = $this->block($page, 'accordion', 'Accordion', 1, [
            'title' => 'Support',
        ]);

        $this->block($page, 'faq', 'FAQ', 2, [
            'parent_id' => $accordion->id,
            'title' => 'Useful item',
            'content' => 'Visible answer.',
        ]);
        $this->block($page, 'faq', 'FAQ', 3, [
            'parent_id' => $accordion->id,
            'title' => '',
            'content' => 'Missing title should skip.',
        ]);
        $this->block($page, 'faq', 'FAQ', 4, [
            'parent_id' => $accordion->id,
            'title' => 'Missing body should skip',
            'content' => '',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<summary>Useful item</summary>', false);
        $response->assertDontSee('Missing title should skip.');
        $response->assertDontSee('Missing body should skip');
    }

    #[Test]
    public function faq_list_renders_as_accordion(): void
    {
        $page = $this->pageWithMainSlot();
        $faqList = $this->block($page, 'faq-list', 'FAQ List', 1, [
            'title' => 'FAQ list',
        ]);

        $this->block($page, 'faq', 'FAQ', 2, [
            'parent_id' => $faqList->id,
            'title' => 'What is this?',
            'content' => 'A transitional accordion alias.',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('<details>', false);
        $response->assertSee('<summary>What is this?</summary>', false);
    }

    #[Test]
    public function faq_still_renders_as_card(): void
    {
        $page = $this->pageWithMainSlot();

        $this->block($page, 'faq', 'FAQ', 1, [
            'title' => 'What does this do?',
            'content' => 'It stays a simple card.',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('wb-card wb-card-muted', false);
        $response->assertSee('wb-card-body wb-stack wb-gap-2', false);
        $response->assertSee('What does this do?');
        $response->assertDontSee('<details>', false);
    }

    #[Test]
    public function tabs_not_promoted(): void
    {
        $page = $this->pageWithMainSlot();

        $this->block($page, 'tabs', 'Tabs', 1, [
            'title' => 'Legacy tabs',
            'subtitle' => 'Still fallback-style',
            'content' => 'No shipped tabs UI exists yet.',
        ]);

        $response = $this->get(route('pages.show', 'about'));

        $response->assertOk();
        $response->assertSee('Legacy tabs');
        $response->assertSee('wb-card-header', false);
        $response->assertDontSee('<details>', false);
        $response->assertDontSee('role="tablist"', false);
    }

    private function pageWithMainSlot(): Page
    {
        $this->seed(FoundationSiteLocaleSeeder::class);
        $site = Site::query()->firstOrFail();

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => 0,
        ]);

        return $page;
    }

    private function block(Page $page, string $slug, string $name, int $sortOrder, array $attributes = []): Block
    {
        $parentId = $attributes['parent_id'] ?? null;
        unset($attributes['parent_id']);

        return Block::query()->create(array_merge([
            'page_id' => $page->id,
            'parent_id' => $parentId,
            'type' => $slug,
            'block_type_id' => $this->blockType($slug, $name, $sortOrder)->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $this->mainSlotType()->id,
            'sort_order' => $parentId
                ? Block::query()->where('parent_id', $parentId)->count()
                : 0,
            'status' => 'published',
            'is_system' => false,
        ], $attributes));
    }

    private function mainSlotType(): SlotType
    {
        return SlotType::query()->updateOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
    }

    private function blockType(string $slug, string $name, int $sortOrder): BlockType
    {
        return BlockType::query()->updateOrCreate(
            ['slug' => $slug],
            ['name' => $name, 'source_type' => 'static', 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => false],
        );
    }
}
