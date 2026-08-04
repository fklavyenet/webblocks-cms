<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Support\Icons\IconCatalog;
use WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Icon context tags come from the WebBlocks UI manifest and record where an
 * icon is used in the product's own chrome. Content authoring cares about what
 * an icon depicts, so the tag suggests rather than restricts: it orders the
 * picker, and it no longer gates saving or rendering. Navigation keeps its
 * curated rule.
 */
class IconCatalogContextTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function an_active_icon_renders_even_when_its_context_is_not_content(): void
  {
    $this->icon('rocket', ['navigation']);

    // Regression: this returned null, so the block rendered without its icon
    // and nothing anywhere said why.
    $this->assertSame(
      'wb-icon wb-icon-rocket',
      app(PublicIconPresenter::class)->iconClass('rocket'),
    );
  }

  #[Test]
  public function a_content_block_renders_an_icon_from_another_context(): void
  {
    $this->icon('shield-check', ['status', 'security']);

    // The content_header partial resolves a render locale for its meta items.
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    $block = Block::query()->make([
      'type' => 'content_header',
      'source_type' => 'static',
      'slot' => 'main',
      'sort_order' => 0,
      'status' => 'published',
      'title' => 'Security',
      'settings' => json_encode(['icon_slug' => 'shield-check', 'icon_tone' => 'brand']),
    ]);

    $html = view('webblocks-cms::pages.partials.blocks.content_header', ['block' => $block])->render();

    $this->assertStringContainsString('wb-icon wb-icon-shield-check wb-icon-tone-brand', $html);
  }

  #[Test]
  public function an_inactive_icon_still_does_not_render(): void
  {
    $this->icon('ghost', ['content'], active: false);

    $this->assertNull(app(PublicIconPresenter::class)->iconClass('ghost'));
    $this->assertNull(app(PublicIconPresenter::class)->iconClass('never-synced'));
  }

  #[Test]
  public function the_picker_leads_with_the_context_and_offers_the_rest_after_it(): void
  {
    $this->icon('file-text', ['content']);
    $this->icon('rocket', ['navigation']);
    $this->icon('hidden', ['content'], active: false);

    $groups = app(IconCatalog::class)->groupedPickerOptions('content');

    $this->assertSame(['file-text'], $groups['suggested']->pluck('slug')->all());
    $this->assertSame(['rocket'], $groups['all']->pluck('slug')->all());
  }

  #[Test]
  public function content_selection_accepts_any_active_icon_but_not_an_unknown_one(): void
  {
    $this->icon('rocket', ['navigation']);
    $this->icon('ghost', ['content'], active: false);

    $catalog = app(IconCatalog::class);

    $this->assertTrue($catalog->isActiveSelection('rocket'));
    $this->assertTrue($catalog->isActiveSelection(null));
    $this->assertFalse($catalog->isActiveSelection('ghost'));
    $this->assertFalse($catalog->isActiveSelection('never-synced'));
  }

  #[Test]
  public function navigation_selection_stays_bound_to_the_navigation_context(): void
  {
    $this->icon('file-text', ['content']);
    $this->icon('rocket', ['navigation']);

    $catalog = app(IconCatalog::class);

    $this->assertTrue($catalog->isValidNavigationSelection('rocket'));
    $this->assertFalse($catalog->isValidNavigationSelection('file-text'));
  }

  /**
   * @param  array<int, string>  $contexts
   */
  private function icon(string $slug, array $contexts, bool $active = true): IconCatalogItem
  {
    return IconCatalogItem::query()->create([
      'source' => 'webblocks-ui',
      'slug' => $slug,
      'label' => ucfirst($slug),
      'css_class' => 'wb-icon-'.$slug,
      'categories' => [],
      'contexts' => $contexts,
      'keywords' => IconCatalogItem::normalizeKeywords([$slug]),
      'is_active' => $active,
      'sort_order' => 1,
    ]);
  }
}
