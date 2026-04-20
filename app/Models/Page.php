<?php

namespace App\Models;

use App\Support\Pages\PageRouteResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class Page extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $page): void {
            if (! $page->page_type) {
                $page->page_type = 'default';
            }

            if (! $page->site_id) {
                $page->site_id = Site::query()->orderByDesc('is_primary')->value('id');
            }
        });

        static::created(function (self $page): void {
            $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

            if ($defaultLocaleId) {
                $page->translations()->firstOrCreate(
                    ['locale_id' => $defaultLocaleId],
                    [
                        'site_id' => $page->site_id,
                        'name' => $page->title,
                        'slug' => $page->slug,
                        'path' => PageTranslation::pathFromSlug($page->slug),
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
        'status',
    ];

    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->currentTranslation?->name ?? $this->attributes['title'] ?? null;
    }

    public function getTitleAttribute($value): ?string
    {
        return $this->currentTranslation?->name ?? $value;
    }

    public function getSlugAttribute($value): ?string
    {
        return $this->currentTranslation?->slug ?? $value;
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
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

    public function defaultTranslation(): ?PageTranslation
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return null;
        }

        return $this->translations->firstWhere('locale_id', $defaultLocaleId)
            ?? $this->translations()->where('locale_id', $defaultLocaleId)->first();
    }

    public function publicUrl(?string $localeCode = null): string
    {
        return app(PageRouteResolver::class)->urlFor($this, $localeCode, $this->site);
    }

    public function publicPath(?string $localeCode = null): string
    {
        return app(PageRouteResolver::class)->pathFor($this, $localeCode, $this->site);
    }
}
