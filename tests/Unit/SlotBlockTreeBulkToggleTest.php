<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;

class SlotBlockTreeBulkToggleTest extends TestCase
{
  public function test_slot_editors_expose_one_localized_expand_all_control(): void
  {
    $root = dirname(__DIR__, 2);

    foreach (['admin/pages/slot-blocks.blade.php', 'admin/shared-slots/slot-blocks.blade.php'] as $view) {
      $source = (string) file_get_contents($root.'/resources/views/'.$view);

      $this->assertStringContainsString('data-wb-slot-block-expand-all', $source);
      $this->assertStringContainsString('wb-icon-maximize2', $source);
      $this->assertStringContainsString('expand_all_blocks', $source);
      $this->assertStringContainsString('collapse_all_blocks', $source);
      $this->assertStringContainsString("'liveSearch' => [", $source);
      $this->assertStringContainsString('wb-table-sm', $source);
      $this->assertStringNotContainsString('wb-admin-slot-block-search-card', $source);
    }

    $filters = (string) file_get_contents($root.'/resources/views/admin/partials/listing-filters.blade.php');
    $this->assertStringContainsString('data-wb-slot-block-search', $filters);
    $this->assertStringContainsString('data-wb-slot-block-search-clear', $filters);
  }

  public function test_tree_runtime_toggles_every_parent_and_persists_the_result(): void
  {
    $script = (string) file_get_contents(dirname(__DIR__, 2).'/public/cms/js/admin/slot-block-tree.js');

    $this->assertStringContainsString("closest('[data-wb-slot-block-expand-all]')", $script);
    $this->assertStringContainsString('rootToggles(root).forEach(function (button)', $script);
    $this->assertStringContainsString("icon.classList.toggle('wb-icon-minimize2', allExpanded)", $script);
    $this->assertStringContainsString('syncSlotBlockExpandedState(root);', $script);
    $this->assertStringContainsString('writeStoredExpanded(root, expanded);', $script);
    $this->assertStringContainsString('function searchSlotBlocks(root)', $script);
    $this->assertStringContainsString("row.getAttribute('data-wb-slot-block-search-text')", $script);
    $this->assertStringContainsString('visibleIds.push(rowBlockId(current));', $script);
    $this->assertStringContainsString('setExpandedState(root, uniqueIds(root._wbSlotBlockSearchExpanded.concat(visibleIds)))', $script);
  }
}
