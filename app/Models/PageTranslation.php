<?php

namespace App\Models;

use App\Support\Search\ReindexesPublicSearch;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;
    use ReindexesPublicSearch;

    protected $fillable = [
        'page_id',
        'site_id',
        'locale_id',
        'name',
        'slug',
        'path',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'og_title',
        'og_description',
        'og_image_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'og_image_asset_id' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $translation): void {
            $siteId = $translation->page?->site_id
                ?? ($translation->page_id ? Page::query()->whereKey($translation->page_id)->value('site_id') : null);

            if (! $siteId) {
                throw new \RuntimeException('Page translations must belong to an existing page site.');
            }

            $localeIsEnabled = Site::query()
                ->whereKey($siteId)
                ->whereHas('enabledLocales', fn ($query) => $query->where('locales.id', $translation->locale_id))
                ->exists();

            if (! $localeIsEnabled) {
                throw new \RuntimeException('Page translation locale must be enabled for the page site.');
            }

            $translation->site_id = $siteId;
            $translation->path = self::pathFromSlug((string) $translation->slug);
        });

        static::saved(function (self $translation): void {
            static::refreshSearchForTranslation($translation->fresh(['page', 'locale']));
        });

        static::deleted(function (self $translation): void {
            $page = $translation->page ?? $translation->page()->first();

            if ($page instanceof Page) {
                app(\App\Support\Search\PublicSearchIndexer::class)->refreshPage($page);
            }
        });
    }

    public static function pathFromSlug(string $slug): string
    {
        return $slug === 'home' ? '/' : '/p/'.$slug;
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'og_image_asset_id');
    }
}
