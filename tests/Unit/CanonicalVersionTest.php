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
    $this->assertSame('1.40.23', WebBlocks::VERSION);
    $this->assertSame(WebBlocks::VERSION, WebBlocks::version());
    $this->assertSame('v2.13.0', WebBlocks::UI_VERSION);
    $this->assertSame(WebBlocks::UI_VERSION, WebBlocks::uiVersion());
  }
}
