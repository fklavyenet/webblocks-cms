<?php

namespace WebBlocks\Cms\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NavigationItemTranslation extends CmsModel
{
  use HasFactory;

  protected $fillable = [
    'navigation_item_id',
    'locale_id',
    'title',
  ];

  protected static function booted(): void
  {
    static::saving(function (self $translation): void {
      $siteId = $translation->navigationItem?->site_id
        ?? ($translation->navigation_item_id
          ? NavigationItem::query()->whereKey($translation->navigation_item_id)->value('site_id')
          : null);

      if (! $siteId) {
        return;
      }

      $localeIsEnabled = Site::query()
        ->whereKey($siteId)
        ->whereHas('enabledLocales', fn ($query) => $query->where((new Locale)->qualifyColumn('id'), $translation->locale_id))
        ->exists();

      if (! $localeIsEnabled) {
        throw new \RuntimeException('Navigation item translation locale must be enabled for the item site.');
      }
    });
  }

  public function navigationItem(): BelongsTo
  {
    return $this->belongsTo(NavigationItem::class);
  }

  public function locale(): BelongsTo
  {
    return $this->belongsTo(Locale::class);
  }
}
