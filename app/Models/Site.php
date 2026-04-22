<?php

namespace App\Models;

use App\Support\Sites\SiteDomainNormalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'domain',
        'is_primary',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $site): void {
            $site->handle = str((string) $site->handle)->slug()->toString();
            $site->domain = app(SiteDomainNormalizer::class)->normalize($site->domain);
        });

        static::saved(function (self $site): void {
            self::enforcePrimaryInvariant($site);
        });
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function scopePrimaryFirst(Builder $query): Builder
    {
        return $query->orderByDesc('is_primary')->orderBy('id');
    }

    public function locales(): BelongsToMany
    {
        return $this->belongsToMany(Locale::class, 'site_locales')
            ->withPivot('is_enabled')
            ->withTimestamps();
    }

    public function siteLocales(): HasMany
    {
        return $this->hasMany(SiteLocale::class);
    }

    public function enabledLocales(): BelongsToMany
    {
        return $this->locales()->wherePivot('is_enabled', true);
    }

    public function hasEnabledLocale(Locale|int|string|null $locale): bool
    {
        $localeId = match (true) {
            $locale instanceof Locale => $locale->id,
            is_numeric($locale) => (int) $locale,
            is_string($locale) && $locale !== '' => Locale::query()->where('code', Locale::normalizeCode($locale))->value('id'),
            default => null,
        };

        if (! $localeId) {
            return false;
        }

        return $this->enabledLocales()
            ->where('locales.id', $localeId)
            ->exists();
    }

    public static function primary(): ?self
    {
        return static::query()->primaryFirst()->first();
    }

    public static function enforcePrimaryInvariant(self $site): void
    {
        if ($site->is_primary) {
            static::query()->whereKeyNot($site->id)->update(['is_primary' => false]);

            return;
        }

        if (static::query()->where('is_primary', true)->whereKeyNot($site->id)->doesntExist()) {
            $site->forceFill(['is_primary' => true])->saveQuietly();
        }
    }
}
