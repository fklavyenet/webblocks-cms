<?php

namespace WebBlocks\Cms\Support\Sitemap;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SitemapCache
{
  public function version(int $siteId): string
  {
    $key = $this->versionKey($siteId);

    return (string) Cache::rememberForever($key, fn (): string => (string) Str::uuid());
  }

  public function invalidateSite(int $siteId): void
  {
    Cache::forever($this->versionKey($siteId), (string) Str::uuid());
  }

  private function versionKey(int $siteId): string
  {
    return 'webblocks-cms:sitemap:site:'.$siteId.':version';
  }
}
