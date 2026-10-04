<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\WebBlocks;

class CanonicalVersionTest extends TestCase
{
  #[Test]
  public function canonical_version_matches_the_current_package_release(): void
  {
    $changelog = (string) file_get_contents(dirname(__DIR__, 2).'/CHANGELOG.md');
    $this->assertSame(1, preg_match('/^## (\d+\.\d+\.\d+)\s*$/m', $changelog, $release));
    $this->assertSame($release[1], WebBlocks::VERSION);
    $this->assertSame(WebBlocks::VERSION, WebBlocks::version());
    $this->assertMatchesRegularExpression('/^v\d+\.\d+\.\d+$/', WebBlocks::UI_VERSION);
    $this->assertSame(WebBlocks::UI_VERSION, WebBlocks::uiVersion());
  }
}
