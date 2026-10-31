<?php

namespace WebBlocks\Cms\Support\Navigation;

use Illuminate\Support\Collection;
use WebBlocks\Cms\Support\PublicRendering\VersionedQueryCache;

class NavigationCache extends VersionedQueryCache
{
  public const TABLE = 'wbcms_navigation_cache_versions';

  protected function table(): string
  {
    return self::TABLE;
  }

  protected function namespace(): string
  {
    return 'public-navigation';
  }

  protected function dependencies(): array
  {
    return ['navigation_items', 'navigation_item_translations', 'pages', 'page_translations', 'page_access_rules', 'sites', 'site_locales', 'locales', 'system_settings'];
  }

  protected function seconds(): int
  {
    return (int) config('webblocks-cms.navigation.cache_seconds', 300);
  }

  protected function encode(Collection $items): array
  {
    return app(NavigationSnapshot::class)->encode($items);
  }

  protected function decode(array $rows): Collection
  {
    return app(NavigationSnapshot::class)->decode($rows);
  }

  protected function cacheable(array $rows): bool
  {
    // Oversized menus still render normally; only shared storage is skipped.
    return array_is_list($rows) && strlen(serialize($rows)) <= 1048576;
  }
}
