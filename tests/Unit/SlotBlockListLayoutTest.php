<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SlotBlockListLayoutTest extends TestCase
{
  #[Test]
  public function slot_block_screens_use_the_standard_filter_card(): void
  {
    foreach (['admin/pages/slot-blocks.blade.php', 'admin/shared-slots/slot-blocks.blade.php'] as $view) {
      $contents = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/'.$view);

      $this->assertStringContainsString('wb-card wb-card-muted wb-admin-slot-block-search-card', $contents);
      $this->assertStringContainsString("@include('webblocks-cms::admin.partials.listing-filters'", $contents);
      $this->assertStringContainsString("'liveSearch' => [", $contents);
      $this->assertStringNotContainsString('wb-admin-slot-block-search-row', $contents);
    }
  }

  #[Test]
  public function status_text_is_accessible_without_consuming_table_width(): void
  {
    $row = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/pages/partials/slot-block-row.blade.php');

    $this->assertStringContainsString('class="wb-sr-only" data-wb-block-status-label', $row);
    $this->assertStringNotContainsString('wb-status-pill {{ $block->status', $row);
  }

  #[Test]
  public function summary_is_the_only_flexible_slot_block_column(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/admin.css');
    $views = [
      (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/pages/slot-blocks.blade.php'),
      (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/admin/shared-slots/slot-blocks.blade.php'),
    ];

    $this->assertStringContainsString('.wb-admin-slot-block-summary-cell {', $css);
    $this->assertMatchesRegularExpression('/\.wb-admin-slot-block-summary-cell\s*\{[^}]*width:\s*auto;/s', $css);
    $this->assertMatchesRegularExpression('/\.wb-admin-slot-blocks-table-wrap\s*\{[^}]*overflow-x:\s*auto;[^}]*overflow-y:\s*hidden;/s', $css);
    $this->assertDoesNotMatchRegularExpression(
      '/\.wb-admin-slot-blocks-table \.wb-admin-slot-block-actions-cell\s*\{[^}]*min-width:/s',
      $css,
    );

    foreach ($views as $view) {
      foreach (['id', 'type', 'summary', 'status', 'actions'] as $column) {
        $this->assertStringContainsString('<th class="wb-admin-slot-block-'.$column.'-cell">', $view);
      }

      $this->assertStringContainsString('<th class="wb-cms-block-children-cell">', $view);
    }
  }

  #[Test]
  public function desktop_sidebar_scrolls_only_its_navigation_region(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/admin.css');
    $layout = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/admin.blade.php');

    $this->assertSame(1, substr_count($layout, 'class="wb-admin-dashboard-page"'));
    $this->assertMatchesRegularExpression('/body\.wb-admin-dashboard-page\s*\{[^}]*height:\s*100dvh;[^}]*overflow:\s*hidden;/s', $css);
    $this->assertStringNotContainsString('body.wb-admin-dashboard-page > .wb-dashboard-shell', $css);
    $this->assertMatchesRegularExpression('/\.wb-dashboard-shell > \.wb-sidebar\s*\{[^}]*height:\s*100%;[^}]*overflow:\s*hidden;/s', $css);
    $this->assertMatchesRegularExpression('/\.wb-dashboard-shell > \.wb-sidebar > \.wb-sidebar-nav\s*\{[^}]*min-height:\s*0;[^}]*overflow-y:\s*auto;[^}]*overscroll-behavior-y:\s*contain;/s', $css);
  }
}
