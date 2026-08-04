<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The icon field is a picker modal rather than a select: a name in a dropdown
 * says nothing about what the icon looks like, and the tones it pairs with were
 * two more blind dropdowns beside it.
 *
 * The JS lives in public/cms/js/admin/icon-picker.js and is not exercised here;
 * what these cover is the markup contract it binds to.
 */
class IconPickerFieldTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_field_submits_hidden_inputs_so_the_request_shape_is_unchanged(): void
  {
    $this->icon('rocket');

    $html = $this->renderField('icon_slug', 'icon_tone', 'badge_tone', 'rocket', 'brand', 'success');

    $this->assertStringContainsString('name="icon_slug" value="rocket"', $html);
    $this->assertStringContainsString('name="icon_tone" value="brand"', $html);
    $this->assertStringContainsString('name="badge_tone" value="success"', $html);
    $this->assertStringContainsString('data-wb-icon-picker-open', $html);

    // The dropdown this replaced submitted the same names; only the tone
    // selects inside the modal remain, and they carry no name at all.
    $this->assertStringNotContainsString('<select id="icon_slug"', $html);
    $this->assertStringNotContainsString('<select class="wb-select" name=', $html);
  }

  #[Test]
  public function the_modal_offers_every_active_icon_with_its_glyph_and_name(): void
  {
    $this->icon('rocket', ['navigation'], 'Rocket');
    $this->icon('file-text', ['content'], 'File Text');
    $this->icon('ghost', ['content'], 'Ghost', active: false);

    $html = $this->renderField();

    $this->assertStringContainsString('data-slug="file-text"', $html);
    $this->assertStringContainsString('data-slug="rocket"', $html);
    $this->assertStringNotContainsString('data-slug="ghost"', $html);

    // Glyph and label together: the point of the modal over a name-only list.
    $this->assertStringContainsString('<i class="wb-icon wb-icon-rocket" aria-hidden="true"></i>', $html);
    $this->assertStringContainsString('>Rocket</span>', $html);

    // Search matches the label as well as the slug's words.
    $this->assertStringContainsString('data-search="file text file text"', $html);
  }

  #[Test]
  public function tone_controls_live_in_the_modal_beside_the_preview(): void
  {
    $this->icon('rocket');

    $html = $this->renderField();

    $this->assertStringContainsString('data-wb-icon-picker-preview-icon', $html);
    $this->assertStringContainsString('data-wb-icon-picker-preview-badge', $html);
    $this->assertStringContainsString('data-wb-icon-picker-tone', $html);
    $this->assertStringContainsString('data-wb-icon-picker-badge-tone', $html);

    foreach (['default', 'soft', 'brand', 'accent', 'highlight', 'bold', 'quiet'] as $tone) {
      $this->assertStringContainsString('value="'.$tone.'"', $html);
    }

    foreach (['neutral', 'info', 'success', 'warning', 'danger'] as $badgeTone) {
      $this->assertStringContainsString('value="'.$badgeTone.'"', $html);
    }
  }

  #[Test]
  public function repeated_rows_share_one_modal_but_keep_their_own_inputs(): void
  {
    $this->icon('rocket');

    $html = Blade::render(<<<'BLADE'
      @include('webblocks-cms::admin.blocks.partials.icon-picker-field', ['slugName' => 'items[0][icon_slug]', 'toneName' => 'items[0][icon_tone]', 'badgeToneName' => 'items[0][badge_tone]', 'slug' => '', 'tone' => 'default', 'badgeTone' => 'neutral'])
      @include('webblocks-cms::admin.blocks.partials.icon-picker-field', ['slugName' => 'items[1][icon_slug]', 'toneName' => 'items[1][icon_tone]', 'badgeToneName' => 'items[1][badge_tone]', 'slug' => '', 'tone' => 'default', 'badgeTone' => 'neutral'])
      @stack('overlays')
      BLADE);

    $this->assertSame(2, substr_count($html, 'data-wb-icon-field>'));
    $this->assertStringContainsString('name="items[0][icon_slug]"', $html);
    $this->assertStringContainsString('name="items[1][icon_slug]"', $html);

    // One shared modal: item rows can be added after load and still open it.
    $this->assertSame(1, substr_count($html, 'data-wb-icon-picker-modal'));
  }

  #[Test]
  public function an_empty_catalog_explains_itself_instead_of_offering_an_empty_grid(): void
  {
    $html = $this->renderField();

    $this->assertStringContainsString('wb-empty', $html);
    $this->assertStringNotContainsString('data-wb-icon-picker-search', $html);
    $this->assertStringNotContainsString('data-wb-icon-picker-option', $html);
  }

  private function renderField(
    string $slugName = 'icon_slug',
    string $toneName = 'icon_tone',
    string $badgeToneName = 'badge_tone',
    string $slug = '',
    string $tone = 'default',
    string $badgeTone = 'neutral',
  ): string {
    return Blade::render(
      "@include('webblocks-cms::admin.blocks.partials.icon-picker-field', \$data)\n@stack('overlays')",
      ['data' => [
        'slugName' => $slugName,
        'toneName' => $toneName,
        'badgeToneName' => $badgeToneName,
        'slug' => $slug,
        'tone' => $tone,
        'badgeTone' => $badgeTone,
        'label' => 'Icon',
      ]],
    );
  }

  /**
   * @param  array<int, string>  $contexts
   */
  private function icon(string $slug, array $contexts = ['content'], ?string $label = null, bool $active = true): void
  {
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    IconCatalogItem::query()->create([
      'source' => 'webblocks-ui',
      'slug' => $slug,
      'label' => $label ?? ucfirst($slug),
      'css_class' => 'wb-icon-'.$slug,
      'categories' => [],
      'contexts' => $contexts,
      'keywords' => IconCatalogItem::normalizeKeywords([$slug]),
      'is_active' => $active,
      'sort_order' => 1,
    ]);
  }
}
