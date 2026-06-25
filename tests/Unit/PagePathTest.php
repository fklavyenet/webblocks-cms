<?php

namespace Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\Pages\PagePath;

class PagePathTest extends TestCase
{
  #[Test]
  #[DataProvider('canonicalPaths')]
  public function canonical_paths_preserve_segments(string $input, string $expected): void
  {
    $this->assertSame($expected, PagePath::canonicalize($input));
  }

  public static function canonicalPaths(): array
  {
    return [
      ['/docs/internal-content-api', '/docs/internal-content-api'],
      ['docs/internal-content-api', '/docs/internal-content-api'],
      ['/docs/internal-content-api/', '/docs/internal-content-api'],
      ['/', '/'],
    ];
  }

  #[Test]
  #[DataProvider('unsafePaths')]
  public function unsafe_paths_are_rejected(string $input): void
  {
    $this->expectException(InvalidArgumentException::class);

    PagePath::canonicalize($input);
  }

  public static function unsafePaths(): array
  {
    return [
      ['/docs//internal-content-api'],
      ['/docs/../x'],
      ['/docs/internal-content-api?x=1'],
      ['/docs/internal-content-api#intro'],
      ['/docs%2Finternal-content-api'],
      ["/docs/\ninternal-content-api"],
    ];
  }
}
