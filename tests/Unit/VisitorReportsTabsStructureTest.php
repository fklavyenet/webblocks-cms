<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class VisitorReportsTabsStructureTest extends TestCase
{
  #[Test]
  public function detailed_reports_are_grouped_into_persistent_webblocks_ui_tabs(): void
  {
    $view = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/reports/visitors/index.blade.php');

    $this->assertStringContainsString('class="wb-tabs" data-wb-tabs', $view);
    $this->assertStringContainsString('data-wb-tabs-field="[data-wb-visitor-reports-tab-input]"', $view);
    $this->assertStringContainsString('name="tab" value="{{ $reportTab }}" data-wb-visitor-reports-tab-input', $view);

    preg_match_all('/id="visitor-reports-([a-z]+)-panel"/', $view, $matches);

    $this->assertSame(['acquisition', 'journeys', 'audience', 'traffic', 'content'], $matches[1]);
  }
}
