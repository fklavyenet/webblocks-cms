<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SlotType;

class SearchFormTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function search_form_block_type_is_seeded(): void
  {
    $this->seed(BlockTypeSeeder::class);

    $this->assertDatabaseHas('wbcms_block_types', ['slug' => 'search-form', 'status' => 'published']);
  }

  #[Test]
  public function public_renderer_outputs_semantic_get_form(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);

    $page = Page::query()->create([
      'site_id' => 1,
      'title' => 'Home',
      'slug' => 'home',
      'status' => Page::STATUS_PUBLISHED,
      'settings' => ['public_shell' => 'default'],
    ]);
    SlotType::query()->updateOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
    $page->slots()->create(['slot_type_id' => SlotType::query()->where('slug', 'main')->value('id'), 'source_type' => PageSlot::SOURCE_TYPE_PAGE, 'sort_order' => 0]);
    $searchFormType = BlockType::query()->where('slug', 'search-form')->firstOrFail();

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'search-form',
      'block_type_id' => $searchFormType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => SlotType::query()->where('slug', 'main')->value('id'),
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
      'variant' => 'primary',
      'settings' => json_encode(['show_button' => true], JSON_UNESCAPED_SLASHES),
    ])->textTranslations()->create([
      'locale_id' => Page::defaultLocaleId(),
      'title' => 'Search',
      'subtitle' => 'Go',
      'content' => 'Search this site',
    ]);

    $response = $this->get('/');

    $response->assertOk();
    $response->assertSee('method="GET"', false);
    $response->assertSee('role="search"', false);
    $response->assertSee('name="q"', false);
    $response->assertSee('action="/search"', false);
    $response->assertDontSee('data-wb-public-search-open', false);
  }

  #[Test]
  public function admin_store_persists_translatable_search_form_fields(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);

    $user = User::factory()->superAdmin()->create();
    $page = Page::query()->create([
      'site_id' => 1,
      'title' => 'Home',
      'slug' => 'home',
      'status' => Page::STATUS_DRAFT,
      'settings' => ['public_shell' => 'default'],
    ]);
    $slotType = SlotType::query()->updateOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
    $page->slots()->create(['slot_type_id' => $slotType->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE, 'sort_order' => 0]);
    $searchFormType = BlockType::query()->where('slug', 'search-form')->firstOrFail();

    $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $searchFormType->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Site Search',
      'subtitle' => 'Find',
      'content' => 'Search docs',
      'variant' => 'secondary',
      'show_button' => '0',
      'status' => 'published',
    ])->assertRedirect();

    $block = Block::query()->where('type', 'search-form')->firstOrFail();
    $translation = $block->textTranslations()->where('locale_id', Page::defaultLocaleId())->firstOrFail();

    $this->assertSame('Site Search', $translation->title);
    $this->assertSame('Find', $translation->subtitle);
    $this->assertSame('Search docs', $translation->content);
    $this->assertSame('secondary', $block->variant);
    $this->assertFalse((bool) $block->setting('show_button', true));
  }
}
