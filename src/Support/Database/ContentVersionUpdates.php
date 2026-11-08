<?php

namespace WebBlocks\Cms\Support\Database;

use Closure;
use Illuminate\Database\Connection;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

final class ContentVersionUpdates
{
  /** @var WeakMap<Connection, array<string, true>> */
  private WeakMap $pending;

  public function __construct()
  {
    $this->pending = new WeakMap;
  }

  /** Run inside the transaction that owns the content writes. */
  public function during(Connection $connection, Closure $write): mixed
  {
    $outer = $this->pending[$connection] ?? null;
    $this->pending[$connection] = $outer ?? [];

    try {
      $result = $write();
      if ($outer === null) {
        foreach (array_keys($this->pending[$connection]) as $table) {
          $this->flush($connection, $table);
        }
      }

      return $result;
    } catch (Throwable $exception) {
      if ($outer !== null) {
        $this->pending[$connection] = $outer;
      }

      throw $exception;
    } finally {
      if ($outer === null) {
        unset($this->pending[$connection]);
      }
    }
  }

  public function isPending(Connection $connection, string $table): bool
  {
    return isset($this->pending[$connection][$table]);
  }

  public function invalidate(Connection $connection, string $table): void
  {
    if (isset($this->pending[$connection])) {
      $this->pending[$connection] = $this->pending[$connection] + [$table => true];
    } else {
      $this->update($connection, $table);
    }
  }

  public function flush(Connection $connection, string $table): void
  {
    if ($this->isPending($connection, $table)) {
      $this->update($connection, $table);
      $tables = $this->pending[$connection];
      unset($tables[$table]);
      $this->pending[$connection] = $tables;
    }
  }

  private function update(Connection $connection, string $table): void
  {
    $connection->table($table)->where('id', 1)->update(['generation' => (string) Str::uuid()]);
  }
}
