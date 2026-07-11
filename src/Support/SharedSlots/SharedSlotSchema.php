<?php

namespace WebBlocks\Cms\Support\SharedSlots;

use Illuminate\Support\Facades\Schema;
use Throwable;
use WebBlocks\Cms\Support\Database\CmsTable;

class SharedSlotSchema
{
  public function sharedSlotsTableExists(): bool
  {
    return $this->hasTable('shared_slots');
  }

  public function sharedSlotBlocksTableExists(): bool
  {
    return $this->hasTable('shared_slot_blocks');
  }

  public function revisionsTableExists(): bool
  {
    return $this->hasTable('shared_slot_revisions');
  }

  public function pageSlotSourceColumnsExist(): bool
  {
    return $this->hasTable('page_slots')
      && $this->hasColumn('page_slots', 'source_type')
      && $this->hasColumn('page_slots', 'shared_slot_id');
  }

  private function hasTable(string $table): bool
  {
    try {
      return Schema::hasTable(CmsTable::name($table));
    } catch (Throwable) {
      return false;
    }
  }

  private function hasColumn(string $table, string $column): bool
  {
    try {
      return Schema::hasColumn(CmsTable::name($table), $column);
    } catch (Throwable) {
      return false;
    }
  }
}
