<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminDashboardTablePresentationTest extends TestCase
{
  public function test_dashboard_keeps_overview_and_accessible_chart_data_in_separate_sections(): void
  {
    $dashboard = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    $this->assertStringContainsString('wb-cms-dashboard-grid', $dashboard);
    $this->assertStringContainsString('<table class="wb-table wb-table-sm wb-cms-dashboard-overview" aria-labelledby="dashboard-overview-title">', $dashboard);
    $this->assertStringNotContainsString('<dl', $dashboard);
    $this->assertStringContainsString('dashboard.pages', $dashboard);
    $this->assertStringContainsString('dashboard.page_views', $dashboard);
    $this->assertStringNotContainsString('dashboard.visitor_summary', $dashboard);
    $this->assertStringNotContainsString('dashboard.unique_visitors', $dashboard);
    $this->assertStringNotContainsString('dashboard.top_page', $dashboard);
    $this->assertStringContainsString('data-wb-chart="line"', $dashboard);
    $this->assertStringContainsString('data-wb-chart-table="dashboard-page-views-chart-data"', $dashboard);
    $this->assertMatchesRegularExpression('/<table id="dashboard-page-views-chart-data" hidden>\s*<thead>/', $dashboard);
    $this->assertStringContainsString("<th scope=\"col\">{{ \$insightText('views') }}</th>", $dashboard);
    $this->assertStringNotContainsString("insightText('information')", $dashboard);
    $this->assertStringNotContainsString("insightText('table')", $dashboard);
    $this->assertLessThan(strpos($dashboard, 'admin.partials.dashboard-health'), strpos($dashboard, 'data-wb-chart="line"'));
  }

  public function test_recent_dashboard_collections_are_limited_to_three_items(): void
  {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Admin/DashboardController.php');

    $this->assertSame(2, substr_count($controller, '->limit(3)'));
    $this->assertStringNotContainsString('->limit(5)', $controller);
  }
}
