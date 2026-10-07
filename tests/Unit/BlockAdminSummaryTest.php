<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Media;
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
    $block->setRelation('media', null);

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

  #[DataProvider('structureOnlyProvider')]
  public function test_primary_summary_is_empty_without_meaningful_content(array $attributes): void
  {
    $block = new Block($attributes);
    $block->setAttribute('resolved_locale_code', 'en');
    $block->setRelation('blockType', null);
    $block->setRelation('children', new Collection);
    $block->setRelation('media', null);

    $this->assertNull((new BlockAdminSummary)->primary($block));
  }

  public static function structureOnlyProvider(): array
  {
    return [
      'section' => [['type' => 'section']],
      'grid' => [['type' => 'grid']],
      'card' => [['type' => 'card']],
      'image' => [['type' => 'image']],
      'empty rich text' => [['type' => 'rich-text']],
      'code language without code' => [['type' => 'code', 'settings' => json_encode(['language' => 'bash'])]],
    ];
  }

  #[DataProvider('imageSummaryProvider')]
  public function test_image_summary_uses_localized_copy_then_media_metadata(array $attributes, array $mediaAttributes, ?string $expected): void
  {
    $block = new Block(['type' => 'image'] + $attributes);
    $block->setAttribute('resolved_locale_code', 'de');
    $block->setRelation('blockType', null);
    $block->setRelation('media', $mediaAttributes === [] ? null : new Media($mediaAttributes));

    $this->assertSame($expected, (new BlockAdminSummary)->primary($block));
  }

  public static function imageSummaryProvider(): array
  {
    return [
      'caption takes precedence' => [['title' => 'Localized caption', 'subtitle' => 'Localized alt'], ['title' => 'Media title'], 'Localized caption'],
      'alt without caption' => [['subtitle' => 'Localized alt'], ['title' => 'Media title'], 'Localized alt'],
      'media title' => [[], ['title' => 'Gallery cover', 'alt_text' => 'Shared alt', 'filename' => 'cover.jpg'], 'Gallery cover'],
      'media alt' => [[], ['title' => ' ', 'alt_text' => 'Shared alt', 'filename' => 'cover.jpg'], 'Shared alt'],
      'media caption' => [[], ['caption' => 'Shared caption', 'filename' => 'cover.jpg'], 'Shared caption'],
      'filename' => [[], ['filename' => 'cover.jpg'], 'cover.jpg'],
      'missing media' => [[], [], null],
      'empty fields' => [['title' => ' ', 'subtitle' => ' '], ['title' => ' ', 'filename' => 'cover.jpg'], 'cover.jpg'],
      'safe plain text' => [[], ['title' => '<b>Cover</b> &amp; detail'], 'Cover & detail'],
    ];
  }
}
