<?php

namespace App\Models;

use App\Support\Sites\SiteDomainNormalizer;
use App\Support\Sites\SiteHandle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Site extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'handle',
        'domain',
        'is_primary',
        'display_name',
        'tagline',
        'favicon_media_id',
        'favicon_asset_id',
        'seo_title',
        'seo_description',
        'seo_keywords',
        'social_image_media_id',
        'social_image_asset_id',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    protected function faviconMediaId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $value ?? ($attributes['favicon_asset_id'] ?? null),
            set: fn ($value) => ['favicon_media_id' => $value],
        );
    }

    protected function socialImageMediaId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $value ?? ($attributes['social_image_asset_id'] ?? null),
            set: fn ($value) => ['social_image_media_id' => $value],
        );
    }

    protected function faviconAssetId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $value ?? ($attributes['favicon_media_id'] ?? null),
            set: fn ($value) => ['favicon_media_id' => $value],
        );
    }

    protected function socialImageAssetId(): Attribute
    {
        return Attribute::make(
            get: fn ($value, array $attributes) => $value ?? ($attributes['social_image_media_id'] ?? null),
            set: fn ($value) => ['social_image_media_id' => $value],
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $site): void {
            $site->handle = SiteHandle::normalize($site->handle);
            $site->domain = app(SiteDomainNormalizer::class)->normalize($site->domain);
        });

        static::created(function (self $site): void {
            $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

            if ($defaultLocaleId) {
                $site->locales()->syncWithoutDetaching([$defaultLocaleId => ['is_enabled' => true]]);
            }
        });

        static::saved(function (self $site): void {
            self::enforcePrimaryInvariant($site);
            $site->syncPrimaryDomainRecord();
        });
    }

    protected function domain(): Attribute
    {
        return Attribute::make(
            get: function ($value, array $attributes) {
                if (! Schema::hasTable('site_domains')) {
                    return $attributes['domain'] ?? null;
                }

                if ($this->relationLoaded('siteDomains')) {
                    $primaryDomain = $this->siteDomains
                        ->first(fn (SiteDomain $domain) => $domain->is_primary)
                        ?->domain;

                    if ($primaryDomain !== null) {
                        return $primaryDomain;
                    }
                }

                $primaryDomain = $this->siteDomains()
                    ->where('is_primary', true)
                    ->value('domain');

                return $primaryDomain ?? ($attributes['domain'] ?? null);
            },
            set: fn ($value) => ['domain' => app(SiteDomainNormalizer::class)->normalize($value)],
        );
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class);
    }

    public function siteDomains(): HasMany
    {
        return $this->hasMany(SiteDomain::class)->orderByDesc('is_primary')->orderBy('domain');
    }

    public function siteVariables(): HasMany
    {
        return $this->hasMany(SiteVariable::class)->orderBy('sort_order')->orderBy('id');
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function sharedSlots(): HasMany
    {
        return $this->hasMany(SharedSlot::class);
    }

    public function visitorEvents(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
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

    public function faviconMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'favicon_media_id');
    }

    public function socialImageMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'social_image_media_id');
    }

    public function faviconAsset(): BelongsTo
    {
        return $this->faviconMedia();
    }

    public function socialImageAsset(): BelongsTo
    {
        return $this->socialImageMedia();
    }

    public function siteLocales(): HasMany
    {
        return $this->hasMany(SiteLocale::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    public function enabledLocales(): BelongsToMany
    {
        return $this->locales()
            ->where('locales.is_enabled', true)
            ->wherePivot('is_enabled', true);
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

    public function primaryDomain(): ?SiteDomain
    {
        if ($this->relationLoaded('siteDomains')) {
            return $this->siteDomains->first(fn (SiteDomain $domain) => $domain->is_primary);
        }

        return $this->siteDomains()->where('is_primary', true)->first();
    }

    public function activeDomains()
    {
        return $this->siteDomains()->active()->orderByDesc('is_primary')->orderBy('domain');
    }

    public function activeDomainRecords()
    {
        return $this->relationLoaded('siteDomains')
            ? $this->siteDomains->where('status', SiteDomain::STATUS_ACTIVE)->sortByDesc(fn (SiteDomain $domain) => $domain->is_primary)->values()
            : $this->activeDomains()->get();
    }

    public function canonicalDomain(): ?string
    {
        return $this->primaryDomain()?->domain ?? $this->getRawOriginal('domain');
    }

    public function domainRecordFor(?string $domain): ?SiteDomain
    {
        $domain = app(SiteDomainNormalizer::class)->normalize($domain);

        if ($domain === null || ! Schema::hasTable('site_domains')) {
            return null;
        }

        if ($this->relationLoaded('siteDomains')) {
            return $this->siteDomains->first(fn (SiteDomain $siteDomain) => $siteDomain->domain === $domain);
        }

        return $this->siteDomains()->where('domain', $domain)->first();
    }

    public function publicDisplayName(): string
    {
        $displayName = trim((string) $this->display_name);

        return $displayName !== '' ? $displayName : $this->name;
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

    public function syncPrimaryDomainRecord(): void
    {
        if (! $this->exists || ! Schema::hasTable('site_domains')) {
            return;
        }

        $domain = app(SiteDomainNormalizer::class)->normalize($this->attributes['domain'] ?? $this->getRawOriginal('domain'));

        if ($domain === null) {
            $this->siteDomains()->where('is_primary', true)->delete();
            $this->unsetRelation('siteDomains');

            return;
        }

        $existing = SiteDomain::query()->where('domain', $domain)->first();

        if ($existing && (int) $existing->site_id !== (int) $this->id) {
            throw new \RuntimeException('That domain is already assigned to another site.');
        }

        $siteDomain = $existing ?? new SiteDomain;
        $siteDomain->forceFill([
            'site_id' => $this->id,
            'domain' => $domain,
            'is_primary' => true,
            'redirect_to_primary' => $siteDomain->exists ? $siteDomain->redirect_to_primary : false,
            'status' => SiteDomain::STATUS_ACTIVE,
        ])->saveQuietly();

        $this->siteDomains()
            ->whereKeyNot($siteDomain->id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $this->unsetRelation('siteDomains');

        if (($this->attributes['domain'] ?? $this->getRawOriginal('domain')) !== $domain) {
            static::query()->whereKey($this->id)->update(['domain' => $domain]);
            $this->forceFill(['domain' => $domain]);
            $this->syncOriginalAttribute('domain');
        }
    }

    public function markDomainAsPrimary(SiteDomain $siteDomain): void
    {
        abort_unless((int) $siteDomain->site_id === (int) $this->id, 404);

        $this->siteDomains()->update(['is_primary' => false]);

        $siteDomain->forceFill([
            'is_primary' => true,
            'status' => SiteDomain::STATUS_ACTIVE,
        ])->saveQuietly();

        $this->unsetRelation('siteDomains');
        $this->syncLegacyDomainFromPrimaryRecord();
    }

    public function syncLegacyDomainFromPrimaryRecord(): void
    {
        if (! $this->exists || ! Schema::hasTable('site_domains')) {
            return;
        }

        $primaryDomain = $this->siteDomains()->where('is_primary', true)->first();
        $domain = $primaryDomain?->domain;

        if (($this->attributes['domain'] ?? $this->getRawOriginal('domain')) !== $domain) {
            static::query()->whereKey($this->id)->update(['domain' => $domain]);
            $this->forceFill(['domain' => $domain]);
            $this->syncOriginalAttribute('domain');
        }
    }
}
