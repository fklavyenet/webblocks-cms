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
    $this->assertSame('1.77.1', WebBlocks::VERSION);
    $this->assertSame(WebBlocks::VERSION, WebBlocks::version());
    $this->assertSame('v2.25.2', WebBlocks::UI_VERSION);
    $this->assertSame(WebBlocks::UI_VERSION, WebBlocks::uiVersion());
  }
}
