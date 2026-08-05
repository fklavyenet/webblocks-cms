<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutManager;
use WebBlocks\Cms\Support\PublicRendering\SlotWrapperResolver;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The footer slot ships a surface of its own. WebBlocks UI's footer pattern
 * (wb-footer-grid and friends) is layout only, carries no surface, and no
 * renderer emits those classes -- so a footer built from blocks had no way to
 * separate itself from the page. The default hangs off the fixed wb-slot-footer
 * class and public theme tokens, so it follows Light/Dark/Auto on its own.
 */
class PublicFooterSlotDefaultTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function footerRule(): string
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertMatchesRegularExpression('/^\.wb-slot-footer\s*\{[^}]*\}/m', $css);
    preg_match('/^\.wb-slot-footer\s*\{([^}]*)\}/m', $css, $matches);

    return $matches[1];
  }

  #[Test]
  public function the_footer_surface_comes_from_public_theme_tokens(): void
  {
    $rule = $this->footerRule();

    $this->assertStringContainsString('var(--wb-public-surface-strong)', $rule);
    $this->assertStringContainsString('var(--wb-public-border)', $rule);
  }

  #[Test]
  public function the_default_carries_no_literal_colors_so_mode_switching_keeps_working(): void
  {
    $rule = $this->footerRule();

    $this->assertDoesNotMatchRegularExpression(
      '/(?:#[0-9a-fA-F]{3,8}|rgba?\(|hsla?\()/',
      $rule,
      'A literal color here would freeze the footer in one mode.'
    );
  }

  #[Test]
  public function it_sets_background_color_rather_than_the_background_shorthand(): void
  {
    $rule = $this->footerRule();

    // The shorthand resets background-image, so it would erase a footer
    // background image an operator set on the same element.
    $this->assertStringContainsString('background-color:', $rule);
    $this->assertDoesNotMatchRegularExpression('/(?<![\w-])background\s*:/', $rule);
  }

  #[Test]
  public function the_selector_matches_a_footer_slot_that_has_no_css_classes_at_all(): void
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'footer-default', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $page = Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'footer-default',
      'status' => Page::STATUS_DRAFT,
      'settings' => ['public_shell' => 'default'],
    ]);
    $slotType = SlotType::query()->firstOrCreate(
      ['slug' => 'footer'],
      ['name' => 'Footer', 'status' => 'published', 'sort_order' => 0],
    );
    $slot = PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    $attributes = (new SlotWrapperResolver(new PageLayoutManager))->resolve($page, $slot)['attributes'];

    // The catalog ships no css_classes default for footer, so the styling hook
    // has to be the code-owned fixed class -- which catalog-repair cannot erase.
    $this->assertSame('wb-slot-footer', $attributes['class']);
  }
}
