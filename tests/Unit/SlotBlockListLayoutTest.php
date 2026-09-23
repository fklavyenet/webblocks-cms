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
      $this->assertStringContainsString('wb-filter-bar wb-filter-bar--fields', $contents);
      $this->assertStringContainsString('class="wb-input" data-wb-slot-block-search', $contents);
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
  public function actions_column_reserves_room_for_every_block_action(): void
  {
    $css = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/css/admin.css');

    $this->assertMatchesRegularExpression(
      '/\.wb-admin-slot-blocks-table \.wb-admin-slot-block-actions-cell\s*\{[^}]*min-width:\s*11rem;/s',
      $css,
    );
  }
}
