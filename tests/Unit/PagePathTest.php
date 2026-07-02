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

  #[Test]
  #[DataProvider('routePatternMatches')]
  public function route_pattern_excludes_reserved_first_segments(string $path, bool $expected): void
  {
    $matches = preg_match('#^'.PagePath::routePattern().'$#', $path) === 1;

    $this->assertSame($expected, $matches);
  }

  public static function routePatternMatches(): array
  {
    return [
      ['docs/internal-content-api', true],
      ['portfolio/original-paintings', true],
      ['webadmin/plugins/webblocks-commerce/products', false],
      ['cms/app.css', false],
      ['commerce/products/original-painting/buy', false],
      ['admin-api/sites', false],
      ['search', false],
      ['search.json', false],
      ['login', false],
    ];
  }
}
