<?php

namespace Tests\Feature;

use App\Models\Block;
use App\Models\BlockType;
use App\Models\ContactMessage;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Sites\SiteDeleteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SiteDeleteServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_a_normal_non_primary_site_and_its_site_scoped_content(): void
    {
        [$site, $otherSite, $page, $block] = $this->seedDeletableSite();

        $report = app(SiteDeleteService::class)->delete($site);

        $this->assertTrue($report->deleted);
        $this->assertDatabaseMissing('sites', ['id' => $site->id]);
        $this->assertDatabaseMissing('pages', ['id' => $page->id]);
        $this->assertDatabaseMissing('blocks', ['id' => $block->id]);
        $this->assertDatabaseMissing('navigation_items', ['site_id' => $site->id]);
        $this->assertDatabaseHas('sites', ['id' => $otherSite->id]);
        $this->assertSame(2, Site::query()->count());
    }

    #[Test]
    public function it_blocks_deletion_for_the_primary_site(): void
    {
        $primary = Site::primary();
        Site::query()->create([
            'name' => 'Secondary',
            'handle' => 'secondary',
            'domain' => 'secondary.example.test',
            'is_primary' => false,
        ]);

        $report = app(SiteDeleteService::class)->delete($primary);

        $this->assertFalse($report->deleted);
        $this->assertContains('Primary site cannot be deleted.', $report->blockers);
        $this->assertDatabaseHas('sites', ['id' => $primary->id]);
    }

    #[Test]
    public function it_blocks_deletion_for_the_last_remaining_site(): void
    {
        $primary = Site::primary();

        $report = app(SiteDeleteService::class)->delete($primary);

        $this->assertFalse($report->deleted);
        $this->assertContains('The last remaining site cannot be deleted.', $report->blockers);
    }

    #[Test]
    public function it_does_not_affect_other_sites_when_deleting_one_site(): void
    {
        [$site, $otherSite] = $this->seedDeletableSite();

        app(SiteDeleteService::class)->delete($site);

        $this->assertDatabaseHas('sites', ['id' => $otherSite->id]);
        $this->assertSame(1, Page::query()->where('site_id', $otherSite->id)->count());
        $this->assertSame(1, NavigationItem::query()->where('site_id', $otherSite->id)->count());
    }

    #[Test]
    public function it_blocks_deletion_when_contact_messages_are_linked_to_the_site_content(): void
    {
        [$site, $otherSite, $page, $block] = $this->seedDeletableSite();

        ContactMessage::query()->create([
            'page_id' => $page->id,
            'block_id' => $block->id,
            'name' => 'Editor',
            'email' => 'editor@example.test',
            'subject' => 'Question',
            'message' => 'Please call me back.',
        ]);

        $report = app(SiteDeleteService::class)->delete($site);

        $this->assertFalse($report->deleted);
        $this->assertSame(1, $report->count('contact_messages'));
        $this->assertDatabaseHas('sites', ['id' => $site->id]);
        $this->assertDatabaseHas('sites', ['id' => $otherSite->id]);
    }

    private function seedDeletableSite(): array
    {
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $slotType = SlotType::query()->firstOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $blockType = BlockType::query()->firstOrCreate(
            ['slug' => 'text'],
            ['name' => 'Text', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'is_system' => false],
        );

        $site = Site::query()->create([
            'name' => 'Campaign',
            'handle' => 'campaign',
            'domain' => 'campaign.example.test',
            'is_primary' => false,
        ]);
        $site->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Campaign Page',
            'slug' => 'campaign-page',
            'status' => 'published',
        ]);

        PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
        ]);

        $block = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'text',
            'block_type_id' => $blockType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $slotType->id,
            'sort_order' => 0,
            'title' => 'Intro',
            'content' => 'Campaign content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $block->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Intro',
            'content' => 'Campaign content',
        ]);

        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Campaign Page',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $page->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        $otherSite = Site::query()->create([
            'name' => 'Secondary',
            'handle' => 'secondary',
            'domain' => 'secondary.example.test',
            'is_primary' => false,
        ]);
        $otherSite->locales()->sync([$defaultLocale->id => ['is_enabled' => true]]);

        $otherPage = Page::query()->create([
            'site_id' => $otherSite->id,
            'title' => 'Secondary Page',
            'slug' => 'secondary-page',
            'status' => 'published',
        ]);

        NavigationItem::query()->create([
            'site_id' => $otherSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'Secondary Page',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $otherPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return [$site, $otherSite, $page, $block];
    }
}
