<?php

namespace WebBlocks\Cms\Support\Revisions;

use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\Blocks\BlockCopyCatalog;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\Translations\SiteLocaleValidation;

/** Reference reads belong to one restoration transaction, never the request. */
final class RestoreLookups
{
  private int $depth = 0;

  private int $generation = 0;

  private array $defaults = [];

  public function during(Closure $write): mixed
  {
    $this->depth++;
    try {
      return app(SiteLocaleValidation::class)->during(fn () => app(BlockCopyCatalog::class)->during($write));
    } finally {
      if (--$this->depth === 0) {
        $this->defaults = [];
      }
    }
  }

  public function defaultLocale(Closure $load): Locale
  {
    $connection = DB::connection();
    if ($this->depth === 0 || $connection->transactionLevel() === 0 || $connection->getEventDispatcher() !== app('events')
      || Locale::getEventDispatcher() !== app('events') || (Locale::getAllGlobalScopes()[Locale::class] ?? []) !== []
      || (SystemSetting::getAllGlobalScopes()[SystemSetting::class] ?? []) !== [] || app('events')->hasListeners('eloquent.retrieved: '.Locale::class)) {
      return $load();
    }
    $key = serialize([spl_object_id($connection), spl_object_id($connection->getPdo()), $connection->getDatabaseName(), $connection->getTablePrefix(), config('app.locale')]);
    if (! isset($this->defaults[$key])) {
      $generation = $this->generation;
      $locale = $load();
      if ($generation !== $this->generation) {
        app(RequestMemo::class)->clear();

        return $locale;
      }
      $this->defaults[$key] = clone $locale;
    }

    return clone $this->defaults[$key];
  }

  public function onQuery(QueryExecuted $event): void
  {
    if ($this->depth === 0 || preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $event->sql)) {
      return;
    }
    $prefix = preg_quote($event->connection->getTablePrefix().'wbcms_', '/');
    if (str_contains($event->sql, ';') || ! preg_match('/^\s*(?:insert\s+into|update|delete\s+from)\s+["`]?'.$prefix.'[a-z_]+["`]?\s/i', $event->sql)
      || preg_match('/\b'.$prefix.'(?:locales|system_settings)\b/i', $event->sql)) {
      $this->invalidate();
    }
  }

  public function invalidate(): void
  {
    $this->generation++;
    $this->defaults = [];
  }
}
