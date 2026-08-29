<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class CoreSupportBoundaryTest extends TestCase
{
  public function test_provider_support_feature_is_not_shipped_in_core(): void
  {
    $root = dirname(__DIR__, 2);

    $this->assertDirectoryDoesNotExist($root.'/src/Support/Tickets');
    $this->assertDirectoryDoesNotExist($root.'/resources/views/admin/support');
    $this->assertFileDoesNotExist($root.'/src/Http/Controllers/Admin/SupportController.php');
    $this->assertFileDoesNotExist($root.'/src/Models/SupportConnection.php');

    $routes = (string) file_get_contents($root.'/routes/admin.php');
    $layout = (string) file_get_contents($root.'/resources/views/layouts/admin.blade.php');
    $tables = (string) file_get_contents($root.'/src/Support/Database/CmsTable.php');

    $this->assertStringNotContainsString('SupportController', $routes);
    $this->assertStringNotContainsString('admin.support.', $layout);
    $this->assertStringNotContainsString("'support_connections'", $tables);
  }
}
