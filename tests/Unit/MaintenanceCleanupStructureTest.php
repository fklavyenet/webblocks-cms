<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MaintenanceCleanupStructureTest extends TestCase
{
  #[Test]
  public function cleanup_screen_keeps_destructive_categories_separate(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/cleanup.blade.php');

    foreach (['backups', 'asset-revisions', 'media-variants', 'temporary-workspaces'] as $category) {
      $this->assertStringContainsString("'{$category}'", $view);
      $this->assertStringContainsString('#cleanup-{{ $category }}-modal', $view);
    }

    $this->assertStringContainsString("route('admin.site-transfers.exports.index')", $view);
    $this->assertStringContainsString('class="wb-table wb-table-striped"', $view);
    $this->assertStringNotContainsString('wb-card-body wb-grid wb-grid-3', $view);
    $this->assertStringNotContainsString('Clean Everything', $view);
  }

  #[Test]
  public function settings_no_longer_owns_backup_cleanup(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/settings.blade.php');
    $cleanup = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/system/cleanup.blade.php');

    $this->assertStringNotContainsString("'backup-cleanup' =>", $view);
    $this->assertStringContainsString('backup_cleanup_pre_update_days', $cleanup);
    $this->assertStringContainsString('keep_latest_asset_revisions', $cleanup);
  }
}
