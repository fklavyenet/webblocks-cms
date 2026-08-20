<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class EmbeddedApplicationAssetManagerStructureTest extends TestCase
{
  public function test_application_form_links_to_site_scoped_file_manager(): void
  {
    $form = file_get_contents(__DIR__.'/../../resources/views/admin/embedded-applications/form.blade.php');
    $manager = file_get_contents(__DIR__.'/../../resources/views/admin/embedded-applications/assets.blade.php');

    $this->assertStringContainsString('embedded-applications.assets.index', $form);
    $this->assertStringContainsString('type="file" name="asset"', $manager);
    $this->assertStringContainsString('accept=".css,.js', $manager);
    $this->assertStringContainsString('wb-action-btn-edit', $manager);
    $this->assertStringContainsString('wb-action-btn-delete', $manager);
    $this->assertStringContainsString('expected_checksum', $manager);
    $this->assertStringNotContainsString('onchange=', $manager);
  }
}
