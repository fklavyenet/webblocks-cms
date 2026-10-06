<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Search\SearchTextNormalizer;

class SearchTextNormalizerTest extends TestCase
{
  #[Test]
  #[DataProvider('separatedSearchTerms')]
  public function an_excerpt_is_returned_when_the_full_search_phrase_is_absent(string $content, string $query): void
  {
    // PublicSearchQuery matches each term independently, so a valid result
    // does not necessarily contain the full phrase used to build its excerpt.
    $excerpt = (new SearchTextNormalizer)->excerpt($content, $query);

    $this->assertIsString($excerpt);
    $this->assertSame($content, $excerpt);
  }

  public static function separatedSearchTerms(): array
  {
    return [
      'separate words' => ['Live help and visitor chat', 'live chat'],
      'punctuation between terms' => ['WebBlocks: Forms for enquiries', 'WebBlocks Forms'],
      'unicode text' => ['Çevrimiçi ziyaretçi desteği ve sohbet', 'ziyaretçi sohbet'],
    ];
  }

  #[Test]
  public function a_long_result_without_the_phrase_is_truncated(): void
  {
    $excerpt = (new SearchTextNormalizer)->excerpt('Live help and visitor chat with the studio', 'live chat', 9);

    $this->assertSame('Live help...', $excerpt);
  }

  #[Test]
  public function an_exact_phrase_keeps_the_matching_context(): void
  {
    $excerpt = (new SearchTextNormalizer)->excerpt('Answer visitors using live chat in your CMS.', 'live chat');

    $this->assertSame('Answer visitors using live chat in your CMS.', $excerpt);
  }
}
