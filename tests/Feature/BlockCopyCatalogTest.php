<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockCopyCatalog;
use WebBlocks\Cms\Tests\TestCase;

class BlockCopyCatalogTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function repeated_copy_lookups_are_bounded_and_return_independent_models(): void
  {
    $type = BlockType::create(['name' => 'Copy type', 'slug' => 'copy-type']);
    $slot = SlotType::create(['name' => 'Copy slot', 'slug' => 'copy-slot']);
    $catalog = app(BlockCopyCatalog::class);
    DB::transaction(function () use ($type, $slot, $catalog): void {
      DB::enableQueryLog();
      DB::flushQueryLog();
      try {
        $catalog->during(function () use ($type, $slot, $catalog): void {
          $first = $catalog->find(BlockType::class, $type->id);
          $first->slug = 'changed-in-memory';
          for ($i = 0; $i < 10; $i++) {
            $this->assertSame('copy-type', $catalog->find(BlockType::class, $type->id)->slug);
            $this->assertSame('copy-slot', $catalog->find(SlotType::class, $slot->id)->slug);
          }
        });
        $this->assertCount(2, DB::getQueryLog());
        $catalog->find(BlockType::class, $type->id);
        $this->assertCount(3, DB::getQueryLog(), 'The copy read set must end with its scope.');
      } finally {
        DB::disableQueryLog();
      }
    });
  }

  #[Test]
  public function raw_writes_negative_lookups_and_savepoint_rollbacks_refresh_the_read_set(): void
  {
    $type = BlockType::create(['name' => 'Copy type', 'slug' => 'copy-type']);
    $catalog = app(BlockCopyCatalog::class);
    DB::transaction(fn () => $catalog->during(function () use ($type, $catalog): void {
      $this->assertSame('copy-type', $catalog->find(BlockType::class, $type->id)->slug);
      DB::table($type->getTable())->where('id', $type->id)->update(['slug' => 'raw-change']);
      $this->assertSame('raw-change', $catalog->find(BlockType::class, $type->id)->slug);
      $this->assertNull($catalog->find(SlotType::class, 8000));
      DB::table((new SlotType)->getTable())->insert(['id' => 8000, 'name' => 'New slot', 'slug' => 'new-slot']);
      $this->assertSame('new-slot', $catalog->find(SlotType::class, 8000)->slug);
      try {
        DB::transaction(function () use ($type, $catalog): void {
          DB::table($type->getTable())->where('id', $type->id)->update(['slug' => 'rolled-back']);
          $this->assertSame('rolled-back', $catalog->find(BlockType::class, $type->id)->slug);
          throw new RuntimeException('Synthetic rollback');
        });
      } catch (RuntimeException) {
      }
      $this->assertSame('raw-change', $catalog->find(BlockType::class, $type->id)->slug);
    }));
  }

  #[Test]
  public function a_catalog_write_during_hydration_cannot_repopulate_the_read_set_with_stale_values(): void
  {
    $type = BlockType::create(['name' => 'Copy type', 'slug' => 'copy-type']);
    $changed = false;
    BlockType::retrieved(function (BlockType $row) use ($type, &$changed): void {
      if ($row->id === $type->id && ! $changed) {
        $changed = true;
        DB::table($row->getTable())->where('id', $row->id)->update(['slug' => 'observer-change']);
      }
    });
    $catalog = app(BlockCopyCatalog::class);
    DB::transaction(fn () => $catalog->during(function () use ($type, $catalog): void {
      $this->assertSame('copy-type', $catalog->find(BlockType::class, $type->id)->slug);
      $this->assertSame('observer-change', $catalog->find(BlockType::class, $type->id)->slug);
    }));
  }

  #[Test]
  public function global_scopes_and_nontransactional_calls_do_not_reuse_catalog_results(): void
  {
    $type = BlockType::create(['name' => 'Copy type', 'slug' => 'copy-type']);
    $catalog = app(BlockCopyCatalog::class);
    DB::enableQueryLog();
    DB::flushQueryLog();
    try {
      $catalog->during(function () use ($type, $catalog): void {
        $catalog->find(BlockType::class, $type->id);
        $catalog->find(BlockType::class, $type->id);
      });
      $this->assertCount(2, DB::getQueryLog());
    } finally {
      DB::disableQueryLog();
    }
    $scopes = BlockType::getAllGlobalScopes();
    $visible = false;
    BlockType::addGlobalScope('copy-visibility', function (Builder $query) use (&$visible): void {
      if (! $visible) {
        $query->whereRaw('1 = 0');
      }
    });
    try {
      DB::transaction(function () use ($type, $catalog, &$visible): void {
        $catalog->during(function () use ($type, $catalog, &$visible): void {
          $this->assertNull($catalog->find(BlockType::class, $type->id));
          $visible = true;
          $this->assertSame('copy-type', $catalog->find(BlockType::class, $type->id)->slug);
        });
      });
    } finally {
      BlockType::setAllGlobalScopes($scopes);
    }
  }
}
