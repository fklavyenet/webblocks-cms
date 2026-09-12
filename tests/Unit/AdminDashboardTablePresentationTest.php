<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminDashboardTablePresentationTest extends TestCase
{
  public function test_page_views_leads_the_dashboard_and_overview_keeps_its_hierarchical_table(): void
  {
    $dashboard = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    $this->assertSame(1, substr_count($dashboard, '<table class="wb-table wb-table-striped">'));
    $this->assertGreaterThanOrEqual(5, substr_count($dashboard, '<th scope="row" class="wb-table-key">'));
    $this->assertStringContainsString('dashboard.pages', $dashboard);
    $this->assertStringContainsString('dashboard.page_views', $dashboard);
    $this->assertStringNotContainsString('dashboard.visitor_summary', $dashboard);
    $this->assertStringNotContainsString('dashboard.unique_visitors', $dashboard);
    $this->assertStringNotContainsString('dashboard.top_page', $dashboard);
    $this->assertLessThan(strpos($dashboard, 'dashboard.actions_title'), strpos($dashboard, '<div class="wb-stat">'));
  }
}
