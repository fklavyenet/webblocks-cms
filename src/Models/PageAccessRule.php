<?php

namespace WebBlocks\Cms\Models;

use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\Sitemap\SitemapCache;

class PageAccessRule extends CmsModel
{
  protected $table = 'wbcms_page_access_rules';

  protected $fillable = ['page_id', 'package_id'];

  protected static function booted(): void
  {
    $refresh = static function (self $rule): void {
      $page = Page::query()->find($rule->page_id);
      if ($page) {
        app(SitemapCache::class)->invalidateSite((int) $page->site_id);
        app(PublicSearchIndexer::class)->refreshPage($page);
      }
    };
    static::saved($refresh);
    static::deleted($refresh);
  }

  protected function casts(): array
  {
    return ['package_id' => 'integer'];
  }
}
