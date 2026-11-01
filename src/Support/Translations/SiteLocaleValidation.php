<?php

namespace WebBlocks\Cms\Support\Translations;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Database\CmsTable;

/** Only explicit transactional bulk writers reuse locked locale membership. */
final class SiteLocaleValidation
{
  private int $depth = 0;

  private array $enabled = [];

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
        $this->enabled = [];
      }
    }
  }

  public function allows(int $siteId, mixed $localeId): bool
  {
    $normalizedLocale = is_int($localeId) || is_string($localeId) ? filter_var($localeId, FILTER_VALIDATE_INT) : false;
    if ($this->depth === 0 || DB::connection()->transactionLevel() === 0 || $normalizedLocale === false
      || (new Site)->getGlobalScopes() !== [] || (new Locale)->getGlobalScopes() !== []) {
      return Site::query()->whereKey($siteId)
        ->whereHas('enabledLocales', fn ($query) => $query->where((new Locale)->qualifyColumn('id'), $localeId))->exists();
    }
    $connection = DB::connection();
    $key = $connection->getName().':'.$connection->getDatabaseName().':'.$connection->getTablePrefix().':'.CmsTable::name('sites').':'.$siteId;
    if (! array_key_exists($key, $this->enabled)) {
      $sites = CmsTable::name('sites');
      $locales = CmsTable::name('locales');
      $pivot = CmsTable::name('site_locales');
      // Lock the same site/pivot/locale rows for the enclosing transaction.
      // Relevant writes and rollback discard this operation-local read set.
      $this->enabled[$key] = Site::query()
        ->join($pivot, $pivot.'.site_id', '=', $sites.'.id')
        ->join($locales, $locales.'.id', '=', $pivot.'.locale_id')
        ->where($sites.'.id', $siteId)->toBase()->sharedLock()
        ->get([$locales.'.id', $pivot.'.is_enabled as site_enabled', $locales.'.is_enabled as locale_enabled'])
        ->filter(fn ($row) => $row->site_enabled && $row->locale_enabled)
        ->map(fn ($row) => (int) $row->id)->all();
    }

    return in_array($normalizedLocale, $this->enabled[$key], true);
  }

  public function invalidate(?string $sql = null): void
  {
    if ($this->depth === 0 || ($sql !== null && preg_match('/^\s*(select|pragma|explain|show|describe)\b/i', $sql))) {
      return;
    }
    if ($sql === null || stripos($sql, CmsTable::name('site_locales')) !== false
      || stripos($sql, CmsTable::name('locales')) !== false || stripos($sql, CmsTable::name('sites')) !== false) {
      $this->enabled = [];
    }
  }
}
