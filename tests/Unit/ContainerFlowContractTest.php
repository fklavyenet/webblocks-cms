<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;

class ContainerFlowContractTest extends TestCase
{
  #[Test]
  public function container_is_layout_neutral_by_default(): void
  {
    $block = new Block;

    $this->assertSame('none', $block->containerFlow());
    $this->assertNull($block->containerFlowClass());
  }

  #[Test]
  public function only_explicit_stack_flow_adds_the_stack_class(): void
  {
    $block = new Block;
    $block->settings = json_encode(['flow' => 'stack']);

    $this->assertSame('stack', $block->containerFlow());
    $this->assertSame('wb-stack', $block->containerFlowClass());
  }

  #[Test]
  public function unknown_and_explicit_none_values_remain_layout_neutral(): void
  {
    foreach (['none', 'legacy', ''] as $flow) {
      $block = new Block;
      $block->settings = json_encode(['flow' => $flow]);

      $this->assertSame('none', $block->containerFlow());
      $this->assertNull($block->containerFlowClass());
    }
  }
}
