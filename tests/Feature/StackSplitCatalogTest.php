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
    $split = $this->layoutBlock('split', ['gap' => '4', 'items_alignment' => 'start', 'width' => 'full'], $children);

    $stackHtml = view('webblocks-cms::pages.partials.blocks.stack', ['block' => $stack])->render();
    $splitHtml = view('webblocks-cms::pages.partials.blocks.split', ['block' => $split])->render();

    $this->assertStringContainsString('class="wb-stack wb-stack-3"', $stackHtml);
    $this->assertStringContainsString('class="wb-split wb-gap-4 wb-items-start wb-w-full"', $splitHtml);
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

    $operations->normalizeBlock([
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
    $operations->normalizeBlock([
      'type' => 'split',
      'children' => [
        ['type' => 'header'],
        ['type' => 'plain_text'],
      ],
    ], 'block', null, $errors, $warnings);

    $this->assertSame([], $errors);
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
