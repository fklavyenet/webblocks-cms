<?php

namespace WebBlocks\Cms\Support\Pages;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Support\PublicRendering\VersionedQueryCache;

class PageListCache extends VersionedQueryCache
{
  public const TABLE = 'wbcms_page_list_cache_versions';

  protected function table(): string
  {
    return self::TABLE;
  }

  protected function namespace(): string
  {
    return 'page-list';
  }

  protected function dependencies(): array
  {
    return ['pages', 'page_translations', 'page_access_rules', 'page_types', 'sites', 'site_locales', 'locales', 'system_settings', 'media'];
  }

  protected function seconds(): int
  {
    return (int) config('webblocks-cms.page_list.cache_seconds', 300);
  }

  protected function encode(Collection $items): array
  {
    return $items->map(fn (PageListItem $item) => $item->cacheData())->all();
  }

  protected function decode(array $rows): Collection
  {
    return collect($rows)->map(fn (array $row) => PageListItem::fromCacheData($row));
  }

  protected function cacheable(array $rows): bool
  {
    return array_is_list($rows) && count($rows) <= PageListSettings::LIMIT_MAX;
  }
}
