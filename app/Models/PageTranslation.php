<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'locale_id',
        'name',
        'slug',
        'path',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $translation): void {
            $translation->path = self::pathFromSlug((string) $translation->slug);
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

    public function locale(): BelongsTo
    {
        return $this->belongsTo(Locale::class);
    }
}
