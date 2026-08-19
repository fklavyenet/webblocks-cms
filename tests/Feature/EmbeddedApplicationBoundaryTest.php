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

  #[Test]
  public function fresh_and_system_update_schemas_both_create_the_application_registry(): void
  {
    $fresh = file_get_contents(__DIR__.'/../../database/migrations/fresh/2026_05_20_120000_create_webblocks_cms_fresh_install_schema.php');
    $update = file_get_contents(__DIR__.'/../../database/migrations/updates/2026_08_19_170000_ensure_embedded_applications_table.php');

    $this->assertStringContainsString('wbcms_embedded_applications', $fresh);
    $this->assertStringContainsString('wbcms_embedded_applications', $update);
  }
}
