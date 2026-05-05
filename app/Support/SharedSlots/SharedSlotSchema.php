<?php

namespace App\Support\SharedSlots;

use Illuminate\Support\Facades\Schema;
use Throwable;

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
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            return Schema::hasColumn($table, $column);
        } catch (Throwable) {
            return false;
        }
    }
}
