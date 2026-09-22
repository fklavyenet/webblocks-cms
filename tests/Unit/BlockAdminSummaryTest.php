<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\BlockAdminSummary;

class BlockAdminSummaryTest extends TestCase
{
  #[DataProvider('contentPreviewProvider')]
  public function test_primary_summary_previews_meaningful_block_content(array $attributes, string $expected): void
  {
    $block = new Block($attributes);
    $block->setAttribute('resolved_locale_code', 'en');
    $block->setRelation('blockType', null);
    $block->setRelation('children', new Collection);

    $this->assertSame($expected, (new BlockAdminSummary)->primary($block));
  }

  public static function contentPreviewProvider(): array
  {
    return [
      'rich text' => [
        ['type' => 'rich-text', 'content' => '<p>Create pages from reusable, structured blocks.</p>'],
        'Create pages from reusable, structured blocks.',
      ],
      'plain text' => [
        ['type' => 'plain_text', 'content' => 'Tokens use short-lived credentials.'],
        'Tokens use short-lived credentials.',
      ],
      'code' => [
        ['type' => 'code', 'content' => "curl --request POST https://example.test/tokens\n--header 'Accept: application/json'", 'settings' => json_encode(['language' => 'bash'])],
        'BASH | curl --request POST https://example.test/tokens',
      ],
      'card' => [
        ['type' => 'card', 'content' => 'A concise description of the card content.'],
        'A concise description of the card content.',
      ],
    ];
  }
}
