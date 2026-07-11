<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\WebBlocks;

class CanonicalVersionTest extends TestCase
{
  #[Test]
  public function canonical_version_remains_the_existing_source_value(): void
  {
    $this->assertSame('1.36.0', WebBlocks::VERSION);
    $this->assertSame(WebBlocks::VERSION, WebBlocks::version());
  }
}
