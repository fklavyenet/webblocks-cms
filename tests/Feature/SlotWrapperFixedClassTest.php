<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageLayoutSlot;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\PublicRendering\SlotWrapperResolver;
use WebBlocks\Cms\Tests\TestCase;

/**
 * wb-slot-{name} is a fixed, code-owned class -- never stored in the DB, so
 * catalog-repair's force-sync of css_classes can never erase it. It must
 * always be present, and must never replace whatever css_classes carries.
 */
class SlotWrapperFixedClassTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function resolver(): SlotWrapperResolver
  {
    return new SlotWrapperResolver(new PageLayoutManager);
  }

  private function makePage(string $suffix, string $publicShell): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test-'.$suffix, 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    return Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'page-'.$suffix,
      'status' => Page::STATUS_DRAFT,
      'settings' => ['public_shell' => $publicShell],
    ]);
  }

  private function makeSlot(Page $page, string $slug): PageSlot
  {
    $slotType = SlotType::query()->firstOrCreate(
      ['slug' => $slug],
      ['name' => ucfirst($slug), 'status' => 'published', 'sort_order' => 0],
    );

    return PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);
  }

  #[Test]
  public function the_fixed_class_is_present_alongside_the_catalog_default(): void
  {
    $page = $this->makePage('a', 'default');
    $slot = $this->makeSlot($page, 'main');

    $attributes = $this->resolver()->resolve($page, $slot)['attributes'];

    $this->assertSame('wb-slot-main wb-public-main', $attributes['class']);
  }

  #[Test]
  public function the_fixed_class_is_present_even_when_the_catalog_has_no_default(): void
  {
    $page = $this->makePage('b', 'default');
    $slot = $this->makeSlot($page, 'footer');

    $attributes = $this->resolver()->resolve($page, $slot)['attributes'];

    $this->assertSame('wb-slot-footer', $attributes['class']);
  }

  #[Test]
  public function header_relies_solely_on_the_fixed_class_now_that_public_css_targets_it(): void
  {
    $page = $this->makePage('h', 'default');
    $slot = $this->makeSlot($page, 'header');

    $attributes = $this->resolver()->resolve($page, $slot)['attributes'];

    $this->assertSame('wb-slot-header', $attributes['class']);
  }

  #[Test]
  public function an_operators_custom_css_classes_value_is_appended_not_erased(): void
  {
    $page = $this->makePage('c', 'default');
    $slotType = SlotType::query()->firstOrCreate(
      ['slug' => 'footer'],
      ['name' => 'Footer', 'status' => 'published', 'sort_order' => 0],
    );
    $pageLayout = PageLayout::query()->create([
      'handle' => 'default',
      'name' => 'Default Layout',
      'is_system' => true,
      'is_active' => true,
      'sort_order' => 0,
    ]);
    PageLayoutSlot::query()->create([
      'page_layout_id' => $pageLayout->id,
      'slot_type_id' => $slotType->id,
      'slot_name' => 'footer',
      'html_element' => 'footer',
      'css_classes' => 'wb-public-footer',
      'is_active' => true,
    ]);
    $slot = $this->makeSlot($page, 'footer');

    $attributes = $this->resolver()->resolve($page, $slot)['attributes'];

    $this->assertSame('wb-slot-footer wb-public-footer', $attributes['class'], 'The fixed class must lead; the operator value must survive alongside it.');
  }

  #[Test]
  public function docs_shell_slots_keep_their_own_class_alongside_the_fixed_one(): void
  {
    $page = $this->makePage('d', 'docs');
    $slot = $this->makeSlot($page, 'sidebar');

    $attributes = $this->resolver()->resolve($page, $slot)['attributes'];

    $this->assertSame('wb-slot-sidebar wb-sidebar', $attributes['class']);
  }
}
