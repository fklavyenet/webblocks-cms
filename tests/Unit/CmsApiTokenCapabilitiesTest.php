<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

class CmsApiTokenCapabilitiesTest extends TestCase
{
  #[Test]
  public function content_blocks_delete_is_a_grantable_destructive_capability_outside_the_default_set(): void
  {
    $capability = CmsApiTokenCapabilities::CONTENT_BLOCKS_DELETE;

    $this->assertSame('content.blocks.delete', $capability);
    $this->assertContains($capability, CmsApiTokenCapabilities::ALL);
    $this->assertContains($capability, CmsApiTokenCapabilities::ADVANCED);
    $this->assertContains($capability, CmsApiTokenCapabilities::DESTRUCTIVE);
    $this->assertNotContains($capability, CmsApiTokenCapabilities::DEFAULT);
    $this->assertArrayHasKey($capability, CmsApiTokenCapabilities::LABELS);

    $capabilities = app(CmsApiTokenCapabilities::class);
    $this->assertContains($capability, $capabilities->grantable());
    $this->assertContains($capability, $capabilities->advancedGrantable());
  }
}
