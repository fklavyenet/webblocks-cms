<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;

class LegacyTransitionalBlockContractsTest extends TestCase
{
  use RefreshDatabase;

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  #[Test]
  public function legacy_transitional_target_slugs_remain_outside_the_published_core_contract_set(): void
  {
    $this->seedFoundation();

    $publishedSlugs = collect(app(BlockTypeContractRegistry::class)->publishedCoreContracts())
      ->pluck('slug')
      ->all();

    $this->assertNotContains('tabs', $publishedSlugs);
    $this->assertNotContains('slider', $publishedSlugs);
    $this->assertNotContains('menu', $publishedSlugs);
    $this->assertNotContains('faq-list', $publishedSlugs);
    $this->assertNotContains('showcase-list', $publishedSlugs);
    $this->assertNotContains('contact-info', $publishedSlugs);
  }

  #[Test]
  public function legacy_draft_catalog_rows_fail_safely_in_the_contract_registry(): void
  {
    $this->seedFoundation();

    $registry = app(BlockTypeContractRegistry::class);

    foreach (['tabs', 'slider', 'menu', 'faq-list'] as $slug) {
      $contract = $registry->resolve(BlockType::query()->where('slug', $slug)->firstOrFail());

      $this->assertFalse($contract->documented, $slug);
      $this->assertSame('draft', $contract->status, $slug);
      $this->assertSame('No shipped contract is documented for this block type yet.', $contract->undocumentedMessage, $slug);
    }
  }

  #[Test]
  public function public_only_legacy_renderers_fail_safely_in_the_contract_registry(): void
  {
    $registry = app(BlockTypeContractRegistry::class);

    foreach (['showcase-list', 'contact-info'] as $slug) {
      $contract = $registry->resolve($slug);

      $this->assertFalse($contract->documented, $slug);
      $this->assertNull($contract->publicRendererSource, $slug);
      $this->assertNull($contract->adminFormSource, $slug);
      $this->assertSame('No shipped contract is documented for this block type yet.', $contract->undocumentedMessage, $slug);
    }
  }

  #[Test]
  public function contact_info_renderer_ignores_unsafe_settings_urls(): void
  {
    $page = $this->pageWithMainSlot();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'contact-info',
      'block_type_id' => $this->blockType('contact-info', 'Contact Info', 99)->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $this->mainSlotType()->id,
      'sort_order' => 0,
      'title' => 'Contact us',
      'settings' => json_encode([
        'items' => [
          [
            'label' => 'Unsafe',
            'value' => 'javascript link',
            'url' => 'javascript:alert(1)',
            'target' => '_blank',
          ],
          [
            'label' => 'Email',
            'value' => 'team@example.test',
            'url' => 'mailto:team@example.test',
            'target' => '_blank',
          ],
        ],
      ], JSON_UNESCAPED_SLASHES),
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->get(route('pages.show', 'about'));

    $response->assertOk();
    $response->assertDontSee('javascript:alert(1)', false);
    $response->assertSee('<span>javascript link</span>', false);
    $response->assertSee('href="mailto:team@example.test"', false);
  }

  private function pageWithMainSlot(): Page
  {
    $this->seedFoundation();
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
