<?php

namespace WebBlocks\Cms\Support\PublicRendering;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/** Disposable query data; SQL owns the generation, including during cache outages. */
abstract class VersionedQueryCache
{
  abstract protected function table(): string;

  abstract protected function namespace(): string;

  abstract protected function dependencies(): array;

  abstract protected function seconds(): int;

  abstract protected function encode(Collection $items): array;

  abstract protected function decode(array $rows): Collection;

  abstract protected function cacheable(array $rows): bool;

  private WeakMap $schemas;

  public function __construct()
  {
    $this->schemas = new WeakMap;
  }

  public function onQuery(QueryExecuted $event): void
  {
    $sql = $event->sql;
    if (preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $sql)) {
      return;
    }
    if (str_contains($sql, $this->table())) {
      if (preg_match('/\b(create|drop|alter)\b/i', $sql)) {
        unset($this->schemas[$event->connection]);
      }

      return;
    }
    // Bulk, pivot and raw Laravel writes bypass Eloquent observers. Match only
    // dependencies, never cache-table writes or unrelated visitor/runtime writes.
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    $tables = implode('|', array_map(fn (string $name) => preg_quote($name, '/'), $this->dependencies()));
    if (! preg_match('/\b'.$prefix.'('.$tables.')\b/i', $sql)) {
      return;
    }
    if ($this->available($event->connection)) {
      // Runs on the writer connection, inside its transaction if present. A
      // rollback restores the old generation. Unique tokens prevent ABA reuse.
      $event->connection->table($this->table())->where('id', 1)->update(['generation' => (string) Str::uuid()]);
    }
  }

  /** Resolve data only; render/request context stays outside the shared cache. */
  public function remember(Connection $connection, array $identity, callable $load): Collection
  {
    // Never publish uncommitted data or reuse an external snapshot in a transaction.
    if ($connection->transactionLevel() > 0) {
      return $load();
    }
    $seconds = max(0, $this->seconds());
    $namespace = $this->namespace();
    $database = [$connection->getName(), $connection->getDriverName(), $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(), $connection->getTablePrefix()];

    $generation = null;
    if ($seconds > 0 && $this->available($connection)) {
      $generation = app(RequestMemo::class)->remember($namespace.'.generation', $database,
        fn () => $connection->table($this->table())->useWritePdo()->where('id', 1)->value('generation'));
    }

    return app(RequestMemo::class)->remember($namespace.'.items', [$database, $generation, $identity, $seconds], function () use ($connection, $database, $generation, $identity, $seconds, $load, $namespace): Collection {
      if (! is_string($generation) || $generation === '') {
        return $load();
      }
      $key = 'webblocks-cms:'.$namespace.':v1:'.hash('sha256', serialize([app()->basePath(), $database, $generation, $identity, $seconds]));
      try {
        $rows = Cache::get($key);
        if (is_array($rows) && $this->cacheable($rows)) {
          return $this->decode($rows);
        }
      } catch (Throwable) {
        // Cache is optional. SQL exceptions from the resolver must still surface.
      }
      // A miss reads one primary-connection snapshot, including eager-loaded
      // translations/media. Replica lag must not populate a current generation.
      $items = $connection->transaction($load);
      try {
        $rows = $this->encode($items);
        if ($this->cacheable($rows)) {
          Cache::put($key, $rows, $seconds);
        }
      } catch (Throwable) {
        // The generation lives in SQL, so a recovered cache cannot revive rows
        // invalidated while the cache service was unavailable.
      }

      return $items;
    });
  }

  private function available(Connection $connection): bool
  {
    return $this->schemas[$connection] ??= $connection->getSchemaBuilder()->hasTable($this->table());
  }
}
