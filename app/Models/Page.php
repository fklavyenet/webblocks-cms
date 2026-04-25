<?php

namespace App\Models;

use App\Support\Pages\PageRouteResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if (! $page->page_type) {
                $page->page_type = 'default';
            }

            if (! $page->status) {
                $page->status = self::STATUS_DRAFT;
            }

            if (! $page->site_id) {
                $page->site_id = Site::primary()?->id;
            }

            if ($page->status === self::STATUS_PUBLISHED && ! $page->published_at) {
                $page->published_at = now();
            }
        });

        static::created(function (self $page): void {
            $defaultLocaleId = self::defaultLocaleId();
            $canonicalTitle = $page->attributes['title'] ?? null;
            $canonicalSlug = $page->attributes['slug'] ?? null;

            if (($canonicalSlug === null || $canonicalSlug === '') && is_string($canonicalTitle) && $canonicalTitle !== '') {
                $canonicalSlug = Str::slug($canonicalTitle);
            }

            if ($defaultLocaleId && $canonicalTitle !== null && $canonicalTitle !== '' && $canonicalSlug !== null && $canonicalSlug !== '') {
                $page->translations()->firstOrCreate(
                    ['locale_id' => $defaultLocaleId],
                    [
                        'name' => $canonicalTitle,
                        'slug' => $canonicalSlug,
                        'path' => PageTranslation::pathFromSlug($canonicalSlug),
                    ],
                );
            }

            if (app()->runningUnitTests()) {
                return;
            }

            $route = request()?->route();

            Log::info('Page created', [
                'page_id' => $page->id,
                'title' => $page->name,
                'slug' => $page->slug,
                'status' => $page->status,
                'page_type' => $page->page_type,
                'user_id' => Auth::id(),
                'route' => $route?->getName(),
                'method' => request()?->method(),
                'url' => request()?->fullUrl(),
                'referrer' => request()?->headers->get('referer'),
                'ip' => request()?->ip(),
                'console' => app()->runningInConsole(),
                'command' => $_SERVER['argv'][1] ?? null,
            ]);
        });
    }

    protected $fillable = [
        'site_id',
        'title',
        'slug',
        'page_type',
        'page_type_id',
        'layout_id',
        'status',
        'published_at',
        'review_requested_at',
    ];

    protected $appends = [
        'name',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
            'review_requested_at' => 'datetime',
        ];
    }

    public static function workflowStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_IN_REVIEW,
            self::STATUS_PUBLISHED,
            self::STATUS_ARCHIVED,
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->resolvedTitle();
    }

    public function getTitleAttribute($value): ?string
    {
        return $this->resolvedTitle($value);
    }

    public function getSlugAttribute($value): ?string
    {
        return $this->defaultTranslation()?->slug
            ?? $this->currentTranslation?->slug
            ?? $value;
    }

    public function scopeOrderByDefaultTranslation(Builder $query, string $column, string $direction = 'asc'): Builder
    {
        $defaultLocaleId = self::defaultLocaleId();

        if (! $defaultLocaleId || ! in_array($column, ['name', 'slug'], true)) {
            return $query;
        }

        return $query->orderBy(
            PageTranslation::query()
                ->select($column)
                ->whereColumn('page_translations.page_id', 'pages.id')
                ->where('locale_id', $defaultLocaleId)
                ->limit(1),
            $direction,
        );
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function pageType(): BelongsTo
    {
        return $this->belongsTo(PageType::class);
    }

    public function layout(): BelongsTo
    {
        return $this->belongsTo(Layout::class);
    }

    public function translations(): HasMany
    {
        return $this->hasMany(PageTranslation::class);
    }

    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('sort_order');
    }

    public function slots(): HasMany
    {
        return $this->hasMany(PageSlot::class)->orderBy('sort_order');
    }

    public function navigationItems(): HasMany
    {
        return $this->hasMany(NavigationItem::class);
    }

    public function visitorEvents(): HasMany
    {
        return $this->hasMany(VisitorEvent::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(PageRevision::class)->latest('created_at');
    }

    public function defaultTranslation(): ?PageTranslation
    {
        $defaultLocaleId = self::defaultLocaleId();

        if (! $defaultLocaleId) {
            return null;
        }

        if ($this->relationLoaded('currentTranslation')) {
            $currentTranslation = $this->getRelation('currentTranslation');

            if ($currentTranslation?->locale_id === $defaultLocaleId) {
                return $currentTranslation;
            }
        }

        return $this->translations->firstWhere('locale_id', $defaultLocaleId)
            ?? $this->translations()->where('locale_id', $defaultLocaleId)->first();
    }

    public function translationForLocale(Locale|int|string|null $locale): ?PageTranslation
    {
        $localeId = match (true) {
            $locale instanceof Locale => $locale->id,
            is_numeric($locale) => (int) $locale,
            is_string($locale) && $locale !== '' => Locale::query()->where('code', Locale::normalizeCode($locale))->value('id'),
            default => null,
        };

        if (! $localeId) {
            return null;
        }

        return $this->translations->firstWhere('locale_id', $localeId)
            ?? $this->translations()->where('locale_id', $localeId)->first();
    }

    public function availableSiteLocales(): Collection
    {
        $site = $this->relationLoaded('site') ? $this->site : $this->site()->first();

        if (! $site) {
            return collect();
        }

        return $site->locales()
            ->where('locales.is_enabled', true)
            ->wherePivot('is_enabled', true)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();
    }

    public function translationStatusForSite(): Collection
    {
        $translations = $this->relationLoaded('translations') ? $this->translations : $this->translations()->with('locale')->get();

        return $this->availableSiteLocales()->map(function (Locale $locale) use ($translations) {
            $translation = $translations->firstWhere('locale_id', $locale->id);

            return [
                'locale' => $locale,
                'translation' => $translation,
                'is_missing' => ! $translation,
                'is_default' => $locale->is_default,
                'public_path' => $translation ? $this->publicPath($locale->code) : null,
                'public_url' => $translation ? $this->publicUrl($locale->code) : null,
            ];
        });
    }

    public function publicUrl(?string $localeCode = null): ?string
    {
        return app(PageRouteResolver::class)->urlFor($this, $localeCode, $this->site);
    }

    public function publicPath(?string $localeCode = null): ?string
    {
        return app(PageRouteResolver::class)->pathFor($this, $localeCode, $this->site);
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    public function workflowLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_REVIEW => 'In Review',
            self::STATUS_PUBLISHED => 'Published',
            self::STATUS_ARCHIVED => 'Archived',
            default => 'Draft',
        };
    }

    public function workflowBadgeClass(): string
    {
        return match ($this->status) {
            self::STATUS_IN_REVIEW => 'wb-status-info',
            self::STATUS_PUBLISHED => 'wb-status-active',
            self::STATUS_ARCHIVED => 'wb-status-danger',
            default => 'wb-status-pending',
        };
    }

    public static function defaultLocaleId(): ?int
    {
        return Locale::query()->where('is_default', true)->value('id');
    }

    private function resolvedTitle(mixed $fallback = null): ?string
    {
        return $this->currentTranslation?->name
            ?? $this->defaultTranslation()?->name
            ?? $fallback
            ?? $this->attributes['title']
            ?? null;
    }
}
