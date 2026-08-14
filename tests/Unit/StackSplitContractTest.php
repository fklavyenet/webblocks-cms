<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;

class StackSplitContractTest extends TestCase
{
  #[Test]
  public function stack_uses_only_shipped_spacing_modifiers(): void
  {
    foreach (['1', '2', '3', '4', '6', '8'] as $spacing) {
      $block = new Block(['settings' => json_encode(['spacing' => $spacing])]);

      $this->assertSame('wb-stack-'.$spacing, $block->stackSpacingClass());
    }

    $this->assertNull((new Block(['settings' => json_encode(['spacing' => '5'])]))->stackSpacingClass());
  }

  #[Test]
  public function split_uses_only_shipped_layout_utilities(): void
  {
    $block = new Block(['settings' => json_encode([
      'gap' => '4',
      'items_alignment' => 'start',
      'width' => 'full',
    ])]);

    $this->assertSame('wb-gap-4', $block->splitGapClass());
    $this->assertSame('wb-items-start', $block->splitAlignClass());
    $this->assertSame('wb-w-full', $block->splitWidthClass());
  }

  #[Test]
  public function split_defaults_to_the_native_centered_auto_width_layout(): void
  {
    $block = new Block;

    $this->assertNull($block->splitGapClass());
    $this->assertNull($block->splitAlignClass());
    $this->assertNull($block->splitWidthClass());
  }

  #[Test]
  public function split_stops_accepting_direct_children_after_two(): void
  {
    $type = new BlockType(['slug' => 'split', 'is_container' => true]);
    $block = new Block(['type' => 'split']);
    $block->exists = true;
    $block->setRelation('blockType', $type);
    $block->setRelation('children', collect([new Block, new Block]));

    $this->assertFalse($block->canAcceptMoreChildren());

    $block->setRelation('children', collect([new Block]));

    $this->assertTrue($block->canAcceptMoreChildren());
  }
}
