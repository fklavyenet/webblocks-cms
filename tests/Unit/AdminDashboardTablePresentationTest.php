<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminDashboardTablePresentationTest extends TestCase
{
  public function test_overview_and_visitor_summary_use_hierarchical_webblocks_tables(): void
  {
    $dashboard = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    $this->assertSame(2, substr_count($dashboard, '<table class="wb-table wb-table-striped">'));
    $this->assertGreaterThanOrEqual(8, substr_count($dashboard, '<th scope="row" class="wb-table-key">'));
    $this->assertStringContainsString('dashboard.pages', $dashboard);
    $this->assertStringContainsString('dashboard.page_views', $dashboard);
    $this->assertStringContainsString('dashboard.top_page', $dashboard);
  }
}
