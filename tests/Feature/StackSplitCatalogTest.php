<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeContractRegistry;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

class StackSplitCatalogTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function catalog_publishes_stack_and_split_as_layout_containers(): void
  {
    $definitions = collect(app(CoreBlockTypeCatalogSyncer::class)->definitions())->keyBy('slug');

    foreach (['stack', 'split'] as $slug) {
      $this->assertSame('layout', $definitions[$slug]['category']);
      $this->assertTrue($definitions[$slug]['is_container']);
      $this->assertSame('published', $definitions[$slug]['status']);
      $this->assertTrue(app(BlockTypeContractRegistry::class)->resolve($slug)->documented);
    }
  }

  #[Test]
  public function renderers_compose_only_native_webblocks_layout_classes(): void
  {
    $childType = new BlockType(['name' => 'Container', 'slug' => 'container', 'is_container' => true]);
    $children = collect([1, 2])->map(function () use ($childType) {
      $child = new Block(['type' => 'container']);
      $child->setRelation('blockType', $childType);
      $child->setRelation('children', collect());

      return $child;
    });

    $stack = $this->layoutBlock('stack', ['spacing' => '3'], $children);
    $split = $this->layoutBlock('split', ['gap' => '4', 'items_alignment' => 'start', 'width' => 'full', 'responsive' => 'stack'], $children);

    $stackHtml = view('webblocks-cms::pages.partials.blocks.stack', ['block' => $stack])->render();
    $splitHtml = view('webblocks-cms::pages.partials.blocks.split', ['block' => $split])->render();

    $this->assertStringContainsString('class="wb-stack wb-stack-3"', $stackHtml);
    $this->assertStringContainsString('class="wb-split wb-gap-4 wb-items-start wb-w-full wb-public-split--stack-mobile"', $splitHtml);
    $this->assertSame(2, substr_count($splitHtml, 'data-wb-public-block-type="container"'));
  }

  #[Test]
  public function system_update_promotes_both_catalog_rows(): void
  {
    BlockType::query()->whereIn('slug', ['stack', 'split'])->delete();

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_08_14_120000_promote_stack_and_split_block_types.php';
    $migration->up();

    $this->assertSame(
      ['split', 'stack'],
      BlockType::query()->whereIn('slug', ['stack', 'split'])->orderBy('slug')->pluck('slug')->all(),
    );
  }

  #[Test]
  public function content_api_requires_exactly_two_split_children(): void
  {
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $operations = app(InternalContentApiOperations::class);
    $errors = [];
    $warnings = [];

    $normalized = $operations->normalizeBlock([
      'type' => 'split',
      'children' => [
        ['type' => 'header'],
      ],
    ], 'block', null, $errors, $warnings);

    $this->assertContains(
      'Split must contain exactly two direct child blocks: a growing first side and a content-sized second side. Put a Stack inside either side when it needs multiple blocks.',
      array_column($errors, 'message'),
    );

    $errors = [];
    $normalized = $operations->normalizeBlock([
      'type' => 'split',
      'children' => [
        ['type' => 'header'],
        ['type' => 'plain_text'],
      ],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('stack', $normalized['settings']['responsive']);
  }

  #[Test]
  public function new_admin_splits_stack_on_small_screens_while_legacy_rows_preserve_their_layout(): void
  {
    $type = new BlockType(['slug' => 'split', 'is_container' => true]);
    $newBlock = new Block(['type' => 'split']);
    $newBlock->setRelation('blockType', $type);
    $legacyBlock = clone $newBlock;
    $legacyBlock->exists = true;

    $newHtml = view('webblocks-cms::admin.blocks.settings.split', ['block' => $newBlock])->render();
    $legacyHtml = view('webblocks-cms::admin.blocks.settings.split', ['block' => $legacyBlock])->render();

    $this->assertMatchesRegularExpression('/<option value="stack" selected(?:="selected")?>Stack vertically<\/option>/', $newHtml);
    $this->assertMatchesRegularExpression('/<option value="preserve" selected(?:="selected")?>Preserve horizontal split<\/option>/', $legacyHtml);
  }

  #[Test]
  public function public_css_defines_the_mobile_split_stack_without_changing_the_native_primitive(): void
  {
    $css = file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertStringContainsString('@media (max-width: 48rem)', $css);
    $this->assertStringContainsString('.wb-public-split--stack-mobile', $css);
    $this->assertStringContainsString('flex-direction: column', $css);
  }

  #[Test]
  public function section_flow_renders_bounded_desktop_offsets_and_resets_them_on_mobile(): void
  {
    $section = $this->layoutBlock('section', ['flow' => 'overlap-previous'], collect());
    $html = view('webblocks-cms::pages.partials.blocks.section', ['block' => $section])->render();
    $css = file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertStringContainsString('wb-public-section--overlap-previous', $html);
    $this->assertStringContainsString('.wb-public-section--offset-up', $css);
    $this->assertStringContainsString('margin-block-start: clamp(-7rem, -9vw, -4rem)', $css);
    $this->assertMatchesRegularExpression('/@media \(max-width: 48rem\).*?\.wb-public-section--overlap-previous.*?margin-block: 0/s', $css);
  }

  #[Test]
  public function grid_renderer_and_public_css_support_a_responsive_leading_column(): void
  {
    $children = collect();
    $grid = $this->layoutBlock('grid', ['columns' => '2', 'ratio' => 'lead-left'], $children);

    $html = view('webblocks-cms::pages.partials.blocks.grid', ['block' => $grid])->render();
    $css = file_get_contents(dirname(__DIR__, 2).'/public/cms/css/public.css');

    $this->assertStringContainsString('wb-grid-2', $html);
    $this->assertStringContainsString('wb-public-grid--lead-left', $html);
    $this->assertStringContainsString('grid-template-columns: minmax(0, 2fr) minmax(0, 1fr)', $css);
  }

  #[Test]
  public function content_api_keeps_asymmetric_ratios_scoped_to_two_column_grids(): void
  {
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $operations = app(InternalContentApiOperations::class);
    $errors = [];
    $warnings = [];

    $two = $operations->normalizeBlock([
      'type' => 'grid',
      'settings' => ['columns' => '2', 'ratio' => 'lead-right'],
      'children' => [['type' => 'plain_text']],
    ], 'block', null, $errors, $warnings);
    $three = $operations->normalizeBlock([
      'type' => 'grid',
      'settings' => ['columns' => '3', 'ratio' => 'lead-right'],
      'children' => [['type' => 'plain_text']],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('lead-right', $two['settings']['ratio']);
    $this->assertArrayNotHasKey('ratio', $three['settings']);
  }

  #[Test]
  public function content_api_keeps_columns_plain_unless_cards_are_explicitly_requested(): void
  {
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $operations = app(InternalContentApiOperations::class);
    $errors = [];
    $warnings = [];

    $plain = $operations->normalizeBlock([
      'type' => 'columns',
      'children' => [['type' => 'column_item']],
    ], 'block', null, $errors, $warnings);
    $cards = $operations->normalizeBlock([
      'type' => 'columns',
      'settings' => ['variant' => 'cards'],
      'children' => [['type' => 'column_item']],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('plain', $plain['settings']['variant']);
    $this->assertSame('cards', $cards['settings']['variant']);
  }

  #[Test]
  public function content_api_accepts_only_renderable_hero_layouts(): void
  {
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $operations = app(InternalContentApiOperations::class);
    $errors = [];
    $warnings = [];

    $fullBleed = $operations->normalizeBlock([
      'type' => 'hero',
      'settings' => ['layout' => 'full-bleed'],
    ], 'block', null, $errors, $warnings);
    $unknown = $operations->normalizeBlock([
      'type' => 'hero',
      'settings' => ['layout' => 'diagonal'],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('full-bleed', $fullBleed['settings']['layout']);
    $this->assertArrayNotHasKey('layout', $unknown['settings']);
  }

  #[Test]
  public function content_api_accepts_only_bounded_section_flows(): void
  {
    app(CoreBlockTypeCatalogSyncer::class)->sync();
    $operations = app(InternalContentApiOperations::class);
    $errors = [];
    $warnings = [];

    $overlap = $operations->normalizeBlock([
      'type' => 'section',
      'settings' => ['flow' => 'overlap-previous'],
      'children' => [['type' => 'plain_text']],
    ], 'block', null, $errors, $warnings);
    $unknown = $operations->normalizeBlock([
      'type' => 'section',
      'settings' => ['flow' => 'absolute'],
      'children' => [['type' => 'plain_text']],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
    $this->assertSame('overlap-previous', $overlap['settings']['flow']);
    $this->assertArrayNotHasKey('flow', $unknown['settings']);
  }

  private function layoutBlock(string $slug, array $settings, $children): Block
  {
    $type = new BlockType(['name' => ucfirst($slug), 'slug' => $slug, 'is_container' => true]);
    $block = new Block(['type' => $slug, 'settings' => json_encode($settings)]);
    $block->setRelation('blockType', $type);
    $block->setRelation('children', $children);

    return $block;
  }
}
