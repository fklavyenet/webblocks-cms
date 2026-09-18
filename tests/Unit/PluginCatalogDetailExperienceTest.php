<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PluginCatalogDetailExperienceTest extends TestCase
{
  public function test_catalog_detail_focuses_on_selection_and_installation(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/plugins/catalog/show.blade.php');

    $this->assertStringContainsString('$hasDownloadActivity', $view);
    $this->assertStringContainsString('$canInstallFromCatalog', $view);
    $this->assertStringContainsString("route('admin.plugins.catalog.install'", $view);
    $this->assertStringContainsString("route('admin.system.plugins.show'", $view);

    foreach (['checksumSha256', 'artifactFilename', 'artifactSize', 'scanStatus', 'declaredPermissions', 'declaredRoutes', 'declaredProviders', 'declaredCommands'] as $technicalField) {
      $this->assertStringNotContainsString($technicalField, $view);
    }
  }
}
