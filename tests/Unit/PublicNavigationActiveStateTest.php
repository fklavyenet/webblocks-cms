<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\Navigation\PublicNavigationActiveState;

class PublicNavigationActiveStateTest extends TestCase
{
  #[Test]
  #[DataProvider('pathCases')]
  public function it_compares_public_navigation_paths_without_query_or_trailing_slash_noise(
    string $requestUri,
    string $href,
    bool $expected,
  ): void {
    $matcher = new PublicNavigationActiveState(Request::create($requestUri));

    $this->assertSame($expected, $matcher->matches($href));
  }

  public static function pathCases(): array
  {
    return [
      'default locale' => ['/guides/sign-in', '/guides/sign-in', true],
      'German locale' => ['/de/guides/sign-in', '/de/guides/sign-in', true],
      'Turkish locale' => ['/tr/guides/sign-in', '/tr/guides/sign-in', true],
      'nested route' => ['/de/docs/guides/sign-in', '/de/docs/guides/sign-in', true],
      'sibling route' => ['/de/guides/register', '/de/guides/sign-in', false],
      'query and trailing slash' => ['/de/guides/sign-in/?source=docs', '/de/guides/sign-in?menu=sidebar', true],
      'root' => ['/?source=nav', '/', true],
      'localized root' => ['/de/', '/de', true],
    ];
  }

  #[Test]
  public function section_matching_does_not_activate_a_sibling_or_treat_root_as_every_section(): void
  {
    $matcher = new PublicNavigationActiveState(Request::create('/de/guides/sign-in'));

    $this->assertTrue($matcher->matches('/de/guides', 'section'));
    $this->assertFalse($matcher->matches('/de/reference', 'section'));
    $this->assertFalse($matcher->matches('/', 'section'));
  }

  #[Test]
  public function external_urls_do_not_match_by_path_alone(): void
  {
    $matcher = new PublicNavigationActiveState(Request::create('https://cms.example.test/de/guides/sign-in'));

    $this->assertFalse($matcher->matches('https://other.example.test/de/guides/sign-in'));
  }
}
