<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class AdminDashboardTablePresentationTest extends TestCase
{
  public function test_compact_dashboard_keeps_overview_and_accessible_chart_data_in_separate_three_column_rows(): void
  {
    $dashboard = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/dashboard.blade.php');

    $this->assertSame(2, substr_count($dashboard, 'wb-grid-3 wb-cms-dashboard-grid'));
    $this->assertGreaterThanOrEqual(5, substr_count($dashboard, '<th scope="row" class="wb-table-key">'));
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
    $this->assertLessThan(strpos($dashboard, 'data-wb-dashboard-activity'), strpos($dashboard, 'data-wb-dashboard-summary'));
    $this->assertLessThan(strpos($dashboard, 'dashboard.actions_title'), strpos($dashboard, 'data-wb-chart="line"'));
  }

  public function test_recent_dashboard_collections_are_limited_to_three_items(): void
  {
    $controller = (string) file_get_contents(dirname(__DIR__, 2).'/src/Http/Controllers/Admin/DashboardController.php');

    $this->assertSame(2, substr_count($controller, '->limit(3)'));
    $this->assertStringNotContainsString('->limit(5)', $controller);
  }
}
