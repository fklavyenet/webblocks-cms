<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class NavigationItem extends Model
{
    use HasFactory;

    public const MENU_PRIMARY = 'primary';

    public const MENU_FOOTER = 'footer';

    public const MENU_MOBILE = 'mobile';

    public const MENU_LEGAL = 'legal';

    public const LINK_PAGE = 'page';

    public const LINK_CUSTOM_URL = 'custom_url';

    public const LINK_GROUP = 'group';

    public const VISIBILITY_VISIBLE = 'visible';

    public const VISIBILITY_HIDDEN = 'hidden';

    protected $fillable = [
        'site_id',
        'menu_key',
        'parent_id',
        'page_id',
        'title',
        'link_type',
        'url',
        'target',
        'position',
        'visibility',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            if (! $item->site_id && Schema::hasColumn($item->getTable(), 'site_id')) {
                $item->site_id = $item->page?->site_id
                    ?? ($item->page_id ? Page::query()->whereKey($item->page_id)->value('site_id') : null)
                    ?? Site::primary()?->id;
            }
        });

        static::saving(function (self $item): void {
            if (! $item->page_id) {
                return;
            }

            $pageSiteId = $item->page?->site_id
                ?? Page::query()->whereKey($item->page_id)->value('site_id');

            if (! $pageSiteId) {
                throw new \RuntimeException('Navigation page links must reference an existing page.');
            }

            if ($item->site_id && (int) $item->site_id !== (int) $pageSiteId) {
                throw new \RuntimeException('Linked page must belong to the same site as the navigation item.');
            }

            $item->site_id = $pageSiteId;
        });
    }

    public static function menuKeys(): array
    {
        return [self::MENU_PRIMARY, self::MENU_FOOTER, self::MENU_MOBILE, self::MENU_LEGAL];
    }

    public static function menuOptions(): array
    {
        return [
            self::MENU_PRIMARY => 'Primary',
            self::MENU_FOOTER => 'Footer',
            self::MENU_MOBILE => 'Mobile',
            self::MENU_LEGAL => 'Legal',
        ];
    }

    public static function linkTypes(): array
    {
        return [self::LINK_PAGE, self::LINK_CUSTOM_URL, self::LINK_GROUP];
    }

    public static function visibilities(): array
    {
        return [self::VISIBILITY_VISIBLE, self::VISIBILITY_HIDDEN];
    }

    public static function locations(): array
    {
        return self::menuKeys();
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position');
    }

    public function scopeVisible($query)
    {
        return $query->where('visibility', self::VISIBILITY_VISIBLE);
    }

    public function scopeActive($query)
    {
        return $this->scopeVisible($query);
    }

    public function scopeForSite($query, Site|int|null $site)
    {
        $siteId = $site instanceof Site ? $site->id : $site;

        if ($siteId) {
            return $query->where('site_id', $siteId);
        }

        return $query;
    }

    public function scopeForMenu($query, string $menuKey)
    {
        return $query->where('menu_key', $menuKey);
    }

    public function scopeForLocation($query, string $location)
    {
        return $this->scopeForMenu($query, $location);
    }

    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('position')->orderBy('id');
    }

    public function resolvedTitle(): string
    {
        return $this->title ?: ($this->page?->name ?: ($this->link_type === self::LINK_GROUP ? 'Untitled Group' : (string) $this->url));
    }

    public function resolvedLabel(): string
    {
        return $this->resolvedTitle();
    }

    public function resolvedUrl(): ?string
    {
        $requestedLocale = request()?->route('locale');

        return match ($this->link_type) {
            self::LINK_PAGE => $this->page?->publicPath(is_string($requestedLocale) && $requestedLocale !== '' ? $requestedLocale : null),
            self::LINK_CUSTOM_URL => $this->url,
            default => null,
        };
    }

    public function typeLabel(): string
    {
        return match ($this->link_type) {
            self::LINK_PAGE => 'Page',
            self::LINK_CUSTOM_URL => 'URL',
            self::LINK_GROUP => 'Group',
            default => 'Item',
        };
    }

    public function visibilityLabel(): string
    {
        return $this->visibility === self::VISIBILITY_HIDDEN ? 'Hidden' : 'Visible';
    }

    public function metaLabel(): string
    {
        $requestedLocale = request()?->route('locale');
        $pagePath = $this->page?->publicPath(is_string($requestedLocale) && $requestedLocale !== '' ? $requestedLocale : null);

        if ($this->link_type === self::LINK_PAGE) {
            if ($this->page?->name && $pagePath) {
                return $this->page->name.' '.$pagePath;
            }

            return $this->page?->name ?: ($pagePath ?: 'Linked page');
        }

        if ($this->link_type === self::LINK_CUSTOM_URL) {
            return $this->url ?: 'Custom URL';
        }

        return 'Dropdown group';
    }

    public function isVisible(): bool
    {
        return $this->visibility !== self::VISIBILITY_HIDDEN;
    }
}
