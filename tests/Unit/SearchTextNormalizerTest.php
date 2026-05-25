<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Search\SearchTextNormalizer;

class SearchTextNormalizerTest extends TestCase
{
  #[Test]
  public function it_normalizes_and_joins_search_text_without_changing_behavior(): void
  {
    $normalizer = new SearchTextNormalizer;

    $this->assertSame('Hello World', $normalizer->normalize("<p>Hello\n\tWorld</p>"));
    $this->assertSame('alpha beta gamma', $normalizer->join([' alpha ', null, '<b>beta</b>', ['gamma']]));
    $this->assertSame('search phrase', $normalizer->query('  search   phrase  '));
  }

  #[Test]
  public function it_builds_excerpt_around_matching_query(): void
  {
    $normalizer = new SearchTextNormalizer;
    $text = 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. Search target appears here inside a longer sentence for excerpt building.';

    $excerpt = $normalizer->excerpt($text, 'target', 80);

    $this->assertNotNull($excerpt);
    $this->assertStringContainsString('target appears here', $excerpt);
  }
}
