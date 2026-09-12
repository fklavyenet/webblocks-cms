<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use WebBlocks\Cms\Support\Sitemap\SitemapCache;

class SiteLocale extends CmsModel
{
  use HasFactory;

  protected static function booted(): void
  {
    static::saved(fn (self $siteLocale) => app(SitemapCache::class)->invalidateSite((int) $siteLocale->site_id));
    static::deleted(fn (self $siteLocale) => app(SitemapCache::class)->invalidateSite((int) $siteLocale->site_id));
  }

  protected $table = 'site_locales';

  protected $fillable = [
    'site_id',
    'locale_id',
    'is_enabled',
  ];

  protected function casts(): array
  {
    return [
      'is_enabled' => 'boolean',
    ];
  }

  public function site(): BelongsTo
  {
    return $this->belongsTo(Site::class);
  }

  public function locale(): BelongsTo
  {
    return $this->belongsTo(Locale::class);
  }
}
