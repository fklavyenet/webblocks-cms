<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Database\Connection;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;

/** Disposable listing data; SQL owns the generation, including during cache outages. */
class PageListCache
{
  public const TABLE = 'wbcms_page_list_cache_versions';

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
    if (str_contains($sql, self::TABLE)) {
      if (preg_match('/\b(create|drop|alter)\b/i', $sql)) {
        unset($this->schemas[$event->connection]);
      }

      return;
    }
    // Bulk, pivot and raw Laravel writes bypass Eloquent observers. Match only
    // dependencies, never cache-table writes or unrelated visitor/runtime writes.
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    if (! preg_match('/\b'.$prefix.'(pages|page_translations|page_access_rules|page_types|sites|site_locales|locales|system_settings|media)\b/i', $sql)) {
      return;
    }
    if ($this->available($event->connection)) {
      // Runs on the writer connection, inside its transaction if present. A
      // rollback restores the old generation. Unique tokens prevent ABA reuse.
      $event->connection->table(self::TABLE)->where('id', 1)->update(['generation' => (string) Str::uuid()]);
    }
  }

  /** @return Collection<int, PageListItem> */
  public function remember(Connection $connection, array $identity, callable $load): Collection
  {
    // Never publish uncommitted data or reuse an external snapshot in a transaction.
    if ($connection->transactionLevel() > 0) {
      return $load();
    }
    $seconds = max(0, (int) config('webblocks-cms.page_list.cache_seconds', 300));
    $database = [$connection->getName(), $connection->getDriverName(), $connection->getConfig('host'), $connection->getConfig('port'), $connection->getDatabaseName(), $connection->getTablePrefix()];

    $generation = null;
    if ($seconds > 0 && $this->available($connection)) {
      $generation = app(RequestMemo::class)->remember('page-list.generation', $database,
        fn () => $connection->table(self::TABLE)->useWritePdo()->where('id', 1)->value('generation'));
    }

    return app(RequestMemo::class)->remember('page-list.items', [$database, $generation, $identity, $seconds], function () use ($connection, $database, $generation, $identity, $seconds, $load): Collection {
      if (! is_string($generation) || $generation === '') {
        return $load();
      }
      $key = 'webblocks-cms:page-list:v1:'.hash('sha256', serialize([app()->basePath(), $database, $generation, $identity, $seconds]));
      try {
        $rows = Cache::get($key);
        if (is_array($rows) && array_is_list($rows) && count($rows) <= PageListSettings::LIMIT_MAX) {
          return collect($rows)->map(fn (array $row) => PageListItem::fromCacheData($row));
        }
      } catch (Throwable) {
        // Cache is optional. SQL exceptions from the resolver must still surface.
      }
      // A miss reads one primary-connection snapshot, including eager-loaded
      // translations/media. Replica lag must not populate a current generation.
      $items = $connection->transaction($load);
      try {
        Cache::put($key, $items->map(fn (PageListItem $item) => $item->cacheData())->all(), $seconds);
      } catch (Throwable) {
        // The generation lives in SQL, so a recovered cache cannot revive rows
        // invalidated while the cache service was unavailable.
      }

      return $items;
    });
  }

  private function available(Connection $connection): bool
  {
    return $this->schemas[$connection] ??= $connection->getSchemaBuilder()->hasTable(self::TABLE);
  }
}
