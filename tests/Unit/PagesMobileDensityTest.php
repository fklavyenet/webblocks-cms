<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class PagesMobileDensityTest extends TestCase
{
  public function test_pages_index_keeps_mobile_rows_compact(): void
  {
    $root = dirname(__DIR__, 2);
    $view = (string) file_get_contents($root.'/resources/views/admin/pages/index.blade.php');
    $css = (string) file_get_contents($root.'/public/cms/css/admin.css');

    $this->assertStringContainsString("'title' => \$adminText('pages.title').' · '.\$siteContext", $view);
    $this->assertStringContainsString('wb-admin-locale-pill', $view);
    $this->assertStringNotContainsString("updatedByUser?->name ?? \$adminText('common.not_recorded')", $view);
    $this->assertStringContainsString('@if ($page->updatedByUser?->name)', $view);
    $this->assertStringContainsString('.wb-admin-locale-pill::before', $css);
    $this->assertStringContainsString('.wb-admin-pages-path-row {', $css);
    $this->assertStringContainsString('.wb-navbar-identity {', $css);
  }
}
