<?php

namespace WebBlocks\Cms\Support\Blocks;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Database\CmsTable;

/** Reuse locked catalog rows only within an explicit transactional copy. */
final class BlockCopyCatalog
{
  private int $depth = 0;

  private array $rows = [];

  private int $generation = 0;

  public function during(callable $work): mixed
  {
    if (DB::connection()->transactionLevel() === 0) {
      return $work();
    }
    $this->depth++;
    try {
      return $work();
    } finally {
      if (--$this->depth === 0) {
        $this->rows = [];
      }
    }
  }

  /** @param class-string<BlockType|SlotType> $class */
  public function find(string $class, mixed $id): BlockType|SlotType|null
  {
    $model = new $class;
    $connection = $model->getConnection();
    $reuse = $this->depth > 0 && $connection->transactionLevel() > 0 && $model->getGlobalScopes() === []
      && $connection->getEventDispatcher() === app('events') && $model::getEventDispatcher() === app('events')
      && ! app('events')->hasListeners('eloquent.retrieved: '.$class);
    if (! $reuse) {
      return $model->newQuery()->whereKey($id)->first();
    }
    $key = serialize([spl_object_id($connection), spl_object_id($connection->getPdo()), $connection->getDatabaseName(), $connection->getTablePrefix(), $class, $model->getTable(), $id]);
    if (! array_key_exists($key, $this->rows)) {
      $generation = $this->generation;
      $row = $model->newQuery()->whereKey($id)->sharedLock()->first();
      // A retrieved observer can write the catalog before hydration returns.
      if ($generation !== $this->generation) {
        return $row;
      }
      $this->rows[$key] = $row;
    }

    return $this->rows[$key] === null ? null : clone $this->rows[$key];
  }

  public function invalidate(?string $sql = null): void
  {
    if ($this->depth === 0 || ($sql !== null && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $sql))) {
      return;
    }
    if ($sql === null || stripos($sql, CmsTable::name('block_types')) !== false || stripos($sql, CmsTable::name('slot_types')) !== false) {
      $this->generation++;
      $this->rows = [];
    }
  }
}
