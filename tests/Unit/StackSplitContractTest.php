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
      'responsive' => 'stack',
    ])]);

    $this->assertSame('wb-gap-4', $block->splitGapClass());
    $this->assertSame('wb-items-start', $block->splitAlignClass());
    $this->assertSame('wb-w-full', $block->splitWidthClass());
    $this->assertSame('wb-public-split--stack-mobile', $block->splitResponsiveClass());
  }

  #[Test]
  public function split_defaults_to_the_native_centered_auto_width_layout(): void
  {
    $block = new Block;

    $this->assertNull($block->splitGapClass());
    $this->assertNull($block->splitAlignClass());
    $this->assertNull($block->splitWidthClass());
    $this->assertNull($block->splitResponsiveClass());
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

  #[Test]
  public function grid_exposes_asymmetric_ratios_only_for_two_columns(): void
  {
    $left = new Block(['settings' => json_encode(['columns' => '2', 'ratio' => 'lead-left'])]);
    $right = new Block(['settings' => json_encode(['columns' => '2', 'ratio' => 'lead-right'])]);
    $three = new Block(['settings' => json_encode(['columns' => '3', 'ratio' => 'lead-left'])]);

    $this->assertSame('wb-public-grid--lead-left', $left->gridRatioClass());
    $this->assertSame('wb-public-grid--lead-right', $right->gridRatioClass());
    $this->assertNull($three->gridRatioClass());
  }

  #[Test]
  public function section_exposes_only_the_bounded_flow_modifiers(): void
  {
    $offset = new Block(['settings' => json_encode(['flow' => 'offset-up'])]);
    $overlap = new Block(['settings' => json_encode(['flow' => 'overlap-previous'])]);
    $unknown = new Block(['settings' => json_encode(['flow' => 'float-anywhere'])]);

    $this->assertSame('wb-public-section--offset-up', $offset->sectionFlowClass());
    $this->assertSame('wb-public-section--overlap-previous', $overlap->sectionFlowClass());
    $this->assertNull($unknown->sectionFlowClass());
  }
}
