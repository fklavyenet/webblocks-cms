<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Tests\TestCase;

class ColumnsAdminDesignGuidanceTest extends TestCase
{
  #[Test]
  public function new_columns_blocks_start_plain_and_explain_when_cards_are_appropriate(): void
  {
    $block = $this->columnsBlock(exists: false);

    $html = view('webblocks-cms::admin.blocks.types.columns', ['block' => $block])->render();

    $this->assertMatchesRegularExpression('/<option value="plain" selected(?:="selected")?>Plain<\/option>/', $html);
    $this->assertStringContainsString('Having three items is not by itself a reason to use cards.', $html);
  }

  #[Test]
  public function an_existing_empty_variant_still_presents_the_legacy_cards_choice(): void
  {
    $block = $this->columnsBlock(exists: true);

    $html = view('webblocks-cms::admin.blocks.types.columns', ['block' => $block])->render();

    $this->assertMatchesRegularExpression('/<option value="cards" selected(?:="selected")?>Cards<\/option>/', $html);
  }

  private function columnsBlock(bool $exists): Block
  {
    $type = new BlockType(['slug' => 'columns', 'is_container' => true]);
    $block = new Block(['type' => 'columns']);
    $block->exists = $exists;
    $block->setRelation('blockType', $type);

    return $block;
  }
}
