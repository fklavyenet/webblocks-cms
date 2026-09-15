<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Tests\TestCase;

class GridAlternatingRenderingTest extends TestCase
{
  #[Test]
  public function alternating_media_text_grid_keeps_and_renders_its_children(): void
  {
    $heading = new Block([
      'id' => 27,
      'type' => 'header',
      'title' => 'Therapie beginnt mit Zuhören',
      'variant' => 'h2',
      'status' => 'published',
    ]);
    $heading->setRelation('children', new Collection);

    $stack = new Block(['id' => 26, 'type' => 'stack', 'status' => 'published']);
    $stack->setRelation('children', collect([$heading]));

    $image = new Block(['id' => 30, 'type' => 'image', 'status' => 'published']);
    $image->setRelation('children', new Collection);
    $image->setRelation('media', null);

    $grid = new Block([
      'id' => 25,
      'parent_id' => 24,
      'type' => 'grid',
      'status' => 'published',
      'settings' => [
        'columns' => 2,
        'alternate_media_text_sections' => true,
        'alternate_start' => 'text_left',
      ],
    ]);
    $grid->setRelation('children', collect([$stack, $image]));

    $container = new Block(['id' => 24, 'type' => 'container', 'status' => 'published']);
    $container->setRelation('children', collect([$grid]));
    $grid->setRelation('parent', $container);

    $html = view('webblocks-cms::pages.partials.blocks.grid', compact('grid'))
      ->with('block', $grid)
      ->render();

    $this->assertStringContainsString('Therapie beginnt mit Zuhören', $html);
    $this->assertStringContainsString('data-wb-public-block-type="stack"', $html);
  }
}
