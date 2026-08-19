<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Tests\TestCase;

class EmbeddedApplicationBoundaryTest extends TestCase
{
  #[Test]
  public function core_contains_no_site_specific_application_root_or_manifest_scan(): void
  {
    $config = file_get_contents(__DIR__.'/../../config/cms.php');
    $registry = file_get_contents(__DIR__.'/../../src/Support/Applications/ApplicationRegistry.php');

    $this->assertStringNotContainsString('play-assets', $config);
    $this->assertStringNotContainsString('embedded_applications.roots', $config);
    $this->assertStringNotContainsString('application.json', $registry);
    $this->assertStringNotContainsString('File::allFiles', $registry);
    $this->assertStringContainsString('EmbeddedApplication::query()', $registry);
  }
}
