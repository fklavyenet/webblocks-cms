<?php

namespace Tests\Feature;

use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;

class PublicPageCanonicalRoutingTest extends TestCase
{
  use RefreshDatabase;

  protected function setUp(): void
  {
    parent::setUp();

    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  #[Test]
  public function published_canonical_nested_path_renders_publicly(): void
  {
    $this->createPage('/docs/internal-content-api', Page::STATUS_PUBLISHED);

    $this->get('/docs/internal-content-api')
      ->assertOk()
      ->assertSee('Canonical docs content');
  }

  #[Test]
  public function draft_canonical_nested_path_does_not_render_publicly(): void
  {
    $this->createPage('/docs/internal-content-api', Page::STATUS_DRAFT);

    $this->get('/docs/internal-content-api')->assertNotFound();
    $this->get('/p/docs/internal-content-api')->assertNotFound();
  }

  #[Test]
  public function reserved_public_namespaces_are_not_captured_as_pages(): void
  {
    $this->createPage('/webadmin', Page::STATUS_PUBLISHED);
    $this->createPage('/search', Page::STATUS_PUBLISHED, 'Search Collision');
    $this->createPage('/contact-messages', Page::STATUS_PUBLISHED, 'Contact Collision');

    $this->get('/webadmin')->assertRedirect();
    $this->getJson('/webadmin/api')->assertOk();
    $this->get('/cms/css/missing.css')->assertNotFound();
    $this->get('/search')->assertOk()->assertDontSee('Search Collision');
    $this->getJson('/search.json')->assertOk();
    $this->get('/contact-messages')->assertNotFound();
  }

  #[Test]
  public function legacy_p_path_redirects_to_canonical_path(): void
  {
    $this->createPage('/docs/internal-content-api', Page::STATUS_PUBLISHED);

    $this->get('/p/docs/internal-content-api')
      ->assertRedirect('/docs/internal-content-api')
      ->assertStatus(301);
  }

  #[Test]
  public function legacy_stored_p_path_is_served_at_canonical_path(): void
  {
    $this->createPage('/p/contact', Page::STATUS_PUBLISHED);

    $this->get('/contact')
      ->assertOk()
      ->assertSee('Canonical docs content');

    $this->get('/p/contact')
      ->assertRedirect('/contact')
      ->assertStatus(301);
  }

  private function createPage(string $path, string $status, string $title = 'Canonical Docs'): Page
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $locale = Locale::query()->where('is_default', true)->firstOrFail();
    $slotType = SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );
    $plainTextType = BlockType::query()->where('slug', 'plain_text')->firstOrFail();

    $page = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => $status,
      'settings' => ['public_shell' => 'default'],
    ]);

    PageTranslation::query()->create([
      'page_id' => $page->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => $title,
      'slug' => trim(basename($path), '/') ?: 'home',
      'path' => $path,
    ]);

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'source_type' => PageSlot::SOURCE_TYPE_PAGE,
      'sort_order' => 0,
    ]);

    $block = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'block_type_id' => $plainTextType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);
    $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Canonical docs content']);
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh(['textTranslations']));

    return $page;
  }
}
