<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Locale extends Model
{
    use HasFactory;

    public const CODE_PATTERN = '[a-z]{2}(?:-[a-z]{2})?';

    public const CODE_VALIDATION_PATTERN = '/^[a-z]{2}(?:-[a-z]{2})?$/';

    protected $fillable = [
        'code',
        'name',
        'is_default',
        'is_enabled',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $locale): void {
            $locale->code = self::normalizeCode($locale->code);
        });

        static::saved(function (self $locale): void {
            self::enforceDefaultInvariant($locale);
        });
    }

    public static function normalizeCode(?string $code): ?string
    {
        if (! is_string($code)) {
            return null;
        }

        $normalized = strtolower(trim($code));

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace('_', '-', $normalized);

        return $normalized;
    }

    public static function routePattern(): string
    {
        return self::CODE_PATTERN;
    }

    public function sites(): BelongsToMany
    {
        return $this->belongsToMany(Site::class, 'site_locales')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function siteLocales(): HasMany
    {
        return $this->hasMany(SiteLocale::class);
    }

    public function pageTranslations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public static function enforceDefaultInvariant(self $locale): void
    {
        if ($locale->is_default) {
            static::query()->whereKeyNot($locale->id)->update(['is_default' => false]);
            $locale->forceFill(['is_enabled' => true])->saveQuietly();
        }

        if (static::query()->where('is_default', true)->doesntExist()) {
            $locale->forceFill(['is_default' => true, 'is_enabled' => true])->saveQuietly();
        }

        $locale->refresh();

        if (! $locale->is_default && ! $locale->is_enabled && static::query()->whereKeyNot($locale->id)->where('is_enabled', true)->doesntExist()) {
            $locale->forceFill(['is_enabled' => true])->saveQuietly();
            $locale->refresh();
        }

        if ($locale->is_default) {
            Site::query()->get()->each(function (Site $site) use ($locale): void {
                $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
            });
        }
    }
}
