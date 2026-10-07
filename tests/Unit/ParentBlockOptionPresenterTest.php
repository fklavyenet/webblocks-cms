<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\BlockAdminSummary;
use WebBlocks\Cms\Support\Blocks\ParentBlockOptionPresenter;

class ParentBlockOptionPresenterTest extends TestCase
{
  public function test_repeated_wrappers_have_localized_context_ids_paths_and_tree_order(): void
  {
    $root = $this->block(10, 'section');
    $firstCard = $this->block(20, 'card', 10);
    $firstBody = $this->block(21, 'card_body', 20);
    $firstStack = $this->block(22, 'stack', 21);
    $firstText = $this->block(23, 'plain_text', 22, ['content' => 'Shared product name']);
    $firstHeading = $this->block(24, 'header', 22, ['title' => 'Beige · verstellbar', 'sort_order' => 1]);
    $secondCard = $this->block(30, 'card', 10, ['sort_order' => 1]);
    $secondBody = $this->block(31, 'card_body', 30);
    $secondStack = $this->block(32, 'stack', 31);
    $secondHeading = $this->block(33, 'header', 32, ['title' => 'Schwarz · verstellbar']);
    // The controller's flat query sorts sort_order across unrelated branches.
    $blocks = new Collection([$secondStack, $firstHeading, $root, $secondBody, $firstStack, $secondHeading, $firstCard, $firstBody, $secondCard, $firstText]);
    $candidates = new Collection([$secondBody, $firstBody, $firstStack, $secondStack, $root]);

    $options = $this->presenter()->present($blocks, $candidates);

    $this->assertSame([10, 21, 22, 31, 32], $options->pluck('id')->all());
    $firstLabel = $options->firstWhere('id', 22)['label'];
    $this->assertStringContainsString('#22 Stack — Beige · verstellbar', $firstLabel);
    $this->assertStringContainsString('Section #10 › Card #20 › Card_Body #21', $firstLabel);
    $this->assertStringNotContainsString('Stack: Stack', $firstLabel);
    $this->assertStringNotContainsString('Shared product name', $firstLabel);
    $this->assertStringContainsString('Schwarz · verstellbar', $options->firstWhere('id', 32)['label']);
    $this->assertStringContainsString('Card #30', $options->firstWhere('id', 32)['label']);
  }

  #[DataProvider('namedWrappers')]
  public function test_saved_admin_names_identify_wrappers_and_remain_available_to_the_form(string $type): void
  {
    $wrapper = $this->block(10, $type, null, ['settings' => json_encode(['layout_name' => 'Product details'])]);
    $heading = $this->block(11, 'header', 10, ['title' => 'Public heading']);

    $this->assertSame('Product details', $wrapper->layoutAdminName());
    $options = $this->presenter()->present(new Collection([$wrapper, $heading]), new Collection([$wrapper]));
    $this->assertStringContainsString('— Product details', $options->first()['label']);
    $this->assertStringNotContainsString('Public heading', $options->first()['label']);
  }

  public static function namedWrappers(): array
  {
    return [['stack'], ['split'], ['card_body'], ['card_header'], ['card_footer']];
  }

  public function test_empty_wrappers_are_distinguishable_without_repeated_type_labels(): void
  {
    $first = $this->block(10, 'stack');
    $second = $this->block(20, 'stack');
    $blocks = new Collection([$first, $second]);

    $this->assertSame(['#10 Stack', '#20 Stack'], $this->presenter()->present($blocks, $blocks)->pluck('label')->all());
  }

  public function test_orphans_and_cycles_do_not_hide_destinations_or_loop(): void
  {
    $orphan = $this->block(10, 'stack', 999);
    $first = $this->block(20, 'stack', 21);
    $second = $this->block(21, 'stack', 20);
    $blocks = new Collection([$orphan, $first, $second]);

    $this->assertSame([10, 20, 21], $this->presenter()->present($blocks, $blocks)->pluck('id')->all());
  }

  private function presenter(): ParentBlockOptionPresenter
  {
    return new ParentBlockOptionPresenter(new BlockAdminSummary);
  }

  private function block(int $id, string $type, ?int $parentId = null, array $attributes = []): Block
  {
    $block = new Block($attributes + ['type' => $type, 'parent_id' => $parentId, 'sort_order' => 0]);
    $block->id = $id;
    $block->setAttribute('resolved_locale_code', 'de');
    $block->setRelation('blockType', null);
    $block->setRelation('children', new Collection);
    $block->setRelation('media', null);

    return $block;
  }
}
