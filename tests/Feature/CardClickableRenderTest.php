<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Tests\TestCase;

class CardClickableRenderTest extends TestCase
{
  #[Test]
  public function a_composable_card_with_a_url_renders_as_one_semantic_link(): void
  {
    $card = new Block([
      'type' => 'card',
      'settings' => json_encode(['url' => '/games/example', 'target' => '_blank']),
    ]);
    $body = new Block(['type' => 'card_body']);
    $body->setRelation('children', new Collection);
    $card->setRelation('children', collect([$body]));

    $html = view('webblocks-cms::pages.partials.blocks.card', ['block' => $card])->render();

    $this->assertStringContainsString('<a href="/games/example" class="wb-card wb-no-decoration"', $html);
    $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $html);
    $this->assertStringContainsString('<div class="wb-card-body"', $html);
    $this->assertStringNotContainsString('<article', $html);
    $this->assertSame(1, substr_count($html, '<a '));
  }

  #[Test]
  public function a_composable_card_without_a_url_remains_an_article(): void
  {
    $card = new Block(['type' => 'card', 'settings' => json_encode([])]);
    $body = new Block(['type' => 'card_body']);
    $body->setRelation('children', new Collection);
    $card->setRelation('children', collect([$body]));

    $html = view('webblocks-cms::pages.partials.blocks.card', ['block' => $card])->render();

    $this->assertStringContainsString('<article class="wb-card"', $html);
    $this->assertStringNotContainsString('<a ', $html);
  }
}
