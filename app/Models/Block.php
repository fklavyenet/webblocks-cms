<?php

namespace App\Models;

use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Blocks\BlockTranslationResolver;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Stringable;
use Illuminate\Support\Str;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
        'layout_type_slot_id',
        'parent_id',
        'type',
        'block_type_id',
        'source_type',
        'slot',
        'slot_type_id',
        'sort_order',
        'title',
        'subtitle',
        'content',
        'url',
        'asset_id',
        'variant',
        'meta',
        'settings',
        'status',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
        ];
    }

    protected function settings(): Attribute
    {
        return Attribute::make(
            get: fn (mixed $value): ?array => $this->hasSettingsValue($value) ? $this->decodeJsonArray($value) : null,
            set: fn (mixed $value): ?string => ($settings = $this->decodeJsonArray($value)) === []
                ? null
                : json_encode($settings, JSON_UNESCAPED_SLASHES),
        );
    }

    protected static function booted(): void
    {
        static::saving(function (self $block): void {
            if ($block->block_type_id) {
                $resolvedBlockType = BlockType::query()->find($block->block_type_id);

                $block->type = $resolvedBlockType?->slug ?? $block->type;
                $block->source_type = $resolvedBlockType?->source_type ?? $block->source_type ?? 'static';
            }

            if ($block->slot_type_id) {
                $block->slot = SlotType::query()
                    ->whereKey($block->slot_type_id)
                    ->value('slug') ?? $block->slot;
            }
        });
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class)->with('translations');
    }

    public function layoutTypeSlot(): BelongsTo
    {
        return $this->belongsTo(LayoutTypeSlot::class);
    }

    public function blockType(): BelongsTo
    {
        return $this->belongsTo(BlockType::class);
    }

    public function slotType(): BelongsTo
    {
        return $this->belongsTo(SlotType::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function publishedChildren(): HasMany
    {
        return $this->children()->where('status', 'published');
    }

    public function blockAssets(): HasMany
    {
        return $this->hasMany(BlockAsset::class)->orderBy('position');
    }

    public function textTranslations(): HasMany
    {
        return $this->hasMany(BlockTextTranslation::class);
    }

    public function buttonTranslations(): HasMany
    {
        return $this->hasMany(BlockButtonTranslation::class);
    }

    public function imageTranslations(): HasMany
    {
        return $this->hasMany(BlockImageTranslation::class);
    }

    public function contactFormTranslations(): HasMany
    {
        return $this->hasMany(BlockContactFormTranslation::class);
    }

    public function typeSlug(): ?string
    {
        return $this->blockType?->slug ?? $this->type;
    }

    public function translationFamily(): ?string
    {
        return app(BlockTranslationRegistry::class)->familyFor($this);
    }

    public function supportsTranslations(): bool
    {
        return $this->translationFamily() !== null;
    }

    public function translationStatus(?Locale $locale = null): array
    {
        return app(BlockTranslationResolver::class)->statusFor($this, $locale);
    }

    public function typeName(): string
    {
        return $this->blockType?->name ?? str($this->typeSlug() ?: 'block')->replace('-', ' ')->title()->toString();
    }

    public function slotName(): string
    {
        return $this->slotType?->name ?? str($this->slot ?: 'slot')->replace('-', ' ')->title()->toString();
    }

    public function editorLabel(): string
    {
        $title = $this->stringValueOrNull($this->title) ?? $this->translatedTextFieldValue('title');
        $content = $this->stringValueOrNull($this->content) ?? $this->translatedTextFieldValue('content');

        if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            $layoutName = $this->layoutAdminName();

            return $layoutName !== null
                ? $this->typeName().($this->typeSlug() === 'cluster' ? ' — ' : ' -- ').$layoutName
                : $this->typeName();
        }

        if ($this->typeSlug() === 'plain_text') {
            return $content ?? $this->typeName();
        }

        return $title ?? $this->typeName();
    }

    public function editorSummary(): ?string
    {
        if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            $childCount = $this->children->count();

            return $childCount > 0
                ? $childCount.' '.Str::plural('child block', $childCount)
                : 'Layout wrapper';
        }

        if ($this->typeSlug() === 'card' && $this->children->isNotEmpty()) {
            $childCount = $this->children->count();

            return trim($this->cardVariant().' '.$childCount.' '.Str::plural('child block', $childCount));
        }

        if ($this->typeSlug() === 'navigation-auto' || $this->typeSlug() === 'menu') {
            return 'Location: '.str($this->navigationLocation())->headline();
        }

        if ($this->typeSlug() === 'stat-card') {
            return $this->stringValueOrNull($this->title)
                ?? $this->translatedTextFieldValue('title')
                ?? $this->stringValueOrNull($this->subtitle)
                ?? $this->translatedTextFieldValue('subtitle')
                ?? $this->stringValueOrNull($this->content, true)
                ?? $this->translatedTextFieldValue('content', true);
        }

        if (in_array($this->typeSlug(), ['columns', 'feature-grid', 'link-list', 'cta'], true)) {
            $childCount = $this->children->count();

            if ($childCount > 0) {
                $itemLabel = match ($this->typeSlug()) {
                    'link-list' => 'link list item',
                    'cta' => 'action',
                    'feature-grid' => 'feature item',
                    default => 'column item',
                };

                return $childCount.' '.$itemLabel.($childCount === 1 ? '' : 's');
            }
        }

        $summary = collect([
            $this->typeSlug() === 'header' ? $this->stringValueOrNull($this->variant) : null,
            $this->stringValueOrNull($this->subtitle) ?? $this->translatedTextFieldValue('subtitle'),
            $this->stringValueOrNull($this->content, true) ?? $this->translatedTextFieldValue('content', true),
            $this->stringValueOrNull($this->url),
            $this->stringValueOrNull($this->variant),
        ])->first(fn ($value) => $value !== null);

        return $summary !== null ? (string) $summary : null;
    }

    public function parentCandidateLabel(): string
    {
        $detail = match ($this->typeSlug()) {
            'card' => $this->parentCandidateDetail($this->title),
            'section', 'container', 'cluster', 'grid' => $this->parentCandidateDetail($this->layoutAdminName()),
            default => $this->parentCandidateDetail($this->editorLabel()),
        };

        return $detail !== null
            ? $this->typeName().': '.$detail
            : $this->typeName();
    }

    public function layoutAdminName(): ?string
    {
        if (! in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            return null;
        }

        $name = trim((string) $this->setting('layout_name', ''));

        return $name !== '' ? $name : null;
    }

    private function parentCandidateDetail(?string $value): ?string
    {
        $resolved = str((string) ($value ?? ''))
            ->squish()
            ->trim();

        if ($resolved->isEmpty()) {
            return null;
        }

        return $this->truncateParentCandidateDetail($resolved)->toString();
    }

    private function truncateParentCandidateDetail(Stringable $value): Stringable
    {
        return $value->length() > 80
            ? $value->limit(80)
            : $value;
    }

    public function stringValueOrNull(mixed $value, bool $stripTags = false): ?string
    {
        if ($value === null) {
            return null;
        }

        $resolved = $stripTags ? strip_tags((string) $value) : (string) $value;
        $resolved = str($resolved)->squish()->toString();

        return $resolved !== '' ? $resolved : null;
    }

    public function translatedTextFieldValue(string $field, bool $stripTags = false): ?string
    {
        if ($this->translationFamily() !== 'text') {
            return null;
        }

        $translations = $this->relationLoaded('textTranslations')
            ? $this->getRelation('textTranslations')
            : $this->textTranslations()->get();
        $defaultLocaleId = app(\App\Support\Locales\LocaleResolver::class)->default()->id;
        $translation = $translations->firstWhere('locale_id', $defaultLocaleId) ?? $translations->first();

        return $this->stringValueOrNull($translation?->{$field}, $stripTags);
    }

    public function metaItems(): Collection
    {
        $raw = $this->meta;

        if (is_array($raw)) {
            return collect($raw)
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values();
        }

        if (! is_string($raw) || trim($raw) === '') {
            return collect();
        }

        $decoded = json_decode($raw, true);

        if (! is_array($decoded)) {
            return collect();
        }

        return collect($decoded)
            ->map(fn ($item) => trim((string) $item))
            ->filter()
            ->values();
    }

    public function canAcceptChildren(): bool
    {
        if ((bool) ($this->blockType?->is_container ?? false)) {
            return true;
        }

        return in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid', 'card', 'sidebar-navigation', 'sidebar-nav-group'], true);
    }

    public function allowedChildTypeSlugs(): ?array
    {
        return match ($this->typeSlug()) {
            'card' => ['cluster', 'button_link'],
            'link-list' => ['link-list-item'],
            'sidebar-navigation' => ['sidebar-nav-item', 'sidebar-nav-group'],
            'sidebar-nav-group' => ['sidebar-nav-item'],
            default => null,
        };
    }

    public function sidebarLinkUrl(): ?string
    {
        $url = trim((string) $this->setting('url', $this->url ?? ''));

        return $url !== '' ? $url : null;
    }

    public function sidebarLinkTarget(): string
    {
        return $this->setting('target') === '_blank' ? '_blank' : '_self';
    }

    public function sidebarNavItemIcon(): ?string
    {
        $icon = $this->setting('icon');

        return in_array($icon, NavigationItem::sidebarIconKeys(), true)
            ? $icon
            : null;
    }

    public function sidebarNavigationMenuKey(): ?string
    {
        $configured = trim((string) ($this->setting('menu_key', $this->setting('menu_handle', '')) ?? ''));

        return in_array($configured, NavigationItem::menuKeys(), true)
            ? $configured
            : null;
    }

    public function sidebarNavigationShowIcons(): bool
    {
        return (bool) $this->setting('show_icons', true);
    }

    public function sidebarNavigationActiveMatching(): string
    {
        return match ($this->setting('active_matching')) {
            'exact', 'current-page' => $this->setting('active_matching'),
            default => 'path',
        };
    }

    public function sidebarNavItemActiveMode(): string
    {
        return match ($this->setting('active_mode')) {
            'exact', 'path', 'current-page', 'manual' => $this->setting('active_mode'),
            default => 'path',
        };
    }

    public function sidebarNavItemManualActive(): bool
    {
        return (bool) $this->setting('manual_active', false);
    }

    public function sidebarNavGroupInitiallyOpen(): bool
    {
        return (bool) $this->setting('initially_open', false);
    }

    public function sidebarFooterVariant(): string
    {
        return match ($this->setting('variant')) {
            'success', 'warning', 'danger' => $this->setting('variant'),
            default => 'info',
        };
    }

    public function sidebarFooterVariantClass(): string
    {
        return 'wb-callout-'. $this->sidebarFooterVariant();
    }

    public function isSidebarNavigation(): bool
    {
        return $this->typeSlug() === 'sidebar-navigation';
    }

    public function isSidebarNavItem(): bool
    {
        return $this->typeSlug() === 'sidebar-nav-item';
    }

    public function isSidebarNavGroup(): bool
    {
        return $this->typeSlug() === 'sidebar-nav-group';
    }

    public function linkListItemUrl(): ?string
    {
        $url = trim((string) $this->url);

        return $url !== '' ? $url : null;
    }

    public function canAcceptChildType(?string $childTypeSlug): bool
    {
        if (! $this->canAcceptChildren() || ! is_string($childTypeSlug) || $childTypeSlug === '') {
            return false;
        }

        $allowedChildTypeSlugs = $this->allowedChildTypeSlugs();

        return $allowedChildTypeSlugs === null
            || in_array($childTypeSlug, $allowedChildTypeSlugs, true);
    }

    public function appearanceSetting(string $key): ?string
    {
        $value = trim((string) $this->setting($key, ''));

        return $value !== '' ? $value : null;
    }

    public function headerAlignmentClass(): ?string
    {
        return match ($this->appearanceSetting('alignment')) {
            'left' => 'wb-text-left',
            'center' => 'wb-text-center',
            'right' => 'wb-text-right',
            default => null,
        };
    }

    public function contentHeaderAlignmentClass(): ?string
    {
        return match ($this->appearanceSetting('alignment')) {
            'left' => 'wb-text-left',
            'center' => 'wb-text-center',
            'right' => 'wb-text-right',
            default => null,
        };
    }

    public function buttonLinkVariantClass(): string
    {
        return match ($this->variant) {
            'secondary' => 'wb-btn wb-btn-secondary',
            default => 'wb-btn wb-btn-primary',
        };
    }

    public function buttonLinkUrl(): ?string
    {
        $url = trim((string) $this->setting('url', ''));

        return $url !== '' ? $url : null;
    }

    public function buttonLinkTarget(): string
    {
        return $this->setting('target') === '_blank' ? '_blank' : '_self';
    }

    public function plainTextAlignmentClass(): ?string
    {
        return match ($this->appearanceSetting('alignment')) {
            'left' => 'wb-text-left',
            'center' => 'wb-text-center',
            'right' => 'wb-text-right',
            default => null,
        };
    }

    public function sectionSpacingClass(): ?string
    {
        return match ($this->appearanceSetting('spacing')) {
            'sm' => 'wb-section-sm',
            'lg' => 'wb-section-lg',
            default => null,
        };
    }

    public function containerWidthClass(): ?string
    {
        return match ($this->appearanceSetting('width')) {
            'sm' => 'wb-container-sm',
            'md' => 'wb-container-md',
            'lg' => 'wb-container-lg',
            'xl' => 'wb-container-xl',
            'full' => 'wb-container-full',
            default => null,
        };
    }

    public function clusterGapClass(): ?string
    {
        return match ($this->appearanceSetting('gap')) {
            '2' => 'wb-cluster-2',
            '4' => 'wb-cluster-4',
            '6' => 'wb-cluster-6',
            default => null,
        };
    }

    public function clusterAlignmentClass(): ?string
    {
        return match ($this->appearanceSetting('alignment')) {
            'center' => 'wb-cluster-center',
            'end' => 'wb-cluster-end',
            default => null,
        };
    }

    public function gridColumnsClass(): string
    {
        return match ($this->appearanceSetting('columns')) {
            '2' => 'wb-grid-2',
            '4' => 'wb-grid-4',
            default => 'wb-grid-3',
        };
    }

    public function gridGapClass(): ?string
    {
        return match ($this->appearanceSetting('gap')) {
            '3' => 'wb-gap-3',
            '4' => 'wb-gap-4',
            '6' => 'wb-gap-6',
            default => null,
        };
    }

    public function cardUrl(): ?string
    {
        $url = trim((string) $this->setting('url', ''));

        return $url !== '' ? $url : null;
    }

    public function cardTarget(): string
    {
        return $this->setting('target') === '_blank' ? '_blank' : '_self';
    }

    public function cardVariant(): string
    {
        return match ($this->setting('variant')) {
            'promo' => 'promo',
            default => 'default',
        };
    }

    public function isPromoCard(): bool
    {
        return $this->cardVariant() === 'promo';
    }

    public function alertVariant(): string
    {
        return match ($this->setting('variant')) {
            'success' => 'success',
            'warning' => 'warning',
            'danger' => 'danger',
            default => 'info',
        };
    }

    public function alertVariantClass(): string
    {
        return 'wb-alert-'. $this->alertVariant();
    }

    public function slotPreviewLabel(): string
    {
        $label = $this->typeName();

        if ($this->typeSlug() === 'navigation-auto' || $this->typeSlug() === 'menu') {
            return $label.' ('.str($this->navigationLocation())->headline().')';
        }

        $childCount = $this->children->count();

        if ($childCount > 0) {
            return $label.' ('.$childCount.' '.Str::plural('item', $childCount).')';
        }

        return $label;
    }

    public function isColumnContainer(): bool
    {
        return $this->typeSlug() === 'columns';
    }

    public function isColumnItem(): bool
    {
        return $this->typeSlug() === 'column_item';
    }

    public function isLinkList(): bool
    {
        return $this->typeSlug() === 'link-list';
    }

    public function isLinkListItem(): bool
    {
        return $this->typeSlug() === 'link-list-item';
    }

    public function isFeatureGrid(): bool
    {
        return $this->typeSlug() === 'feature-grid';
    }

    public function isFeatureItem(): bool
    {
        return $this->typeSlug() === 'feature-item';
    }

    public function isBuilderManagedChild(): bool
    {
        return $this->isColumnItem() || $this->isLinkListItem() || $this->isFeatureItem();
    }

    public function adminFormView(): string
    {
        $view = 'admin.blocks.types.'.$this->typeSlug();

        return View::exists($view) ? $view : 'admin.blocks.types.fallback';
    }

    public function publicRenderView(): string
    {
        $view = 'pages.partials.blocks.'.$this->typeSlug();

        if (View::exists($view)) {
            return $view;
        }

        return App::environment('production')
            ? 'pages.partials.blocks.fallback'
            : 'pages.partials.blocks.missing-renderer';
    }

    public function adminFormSupported(): bool
    {
        return self::supportsAdminForm($this->typeSlug());
    }

    public function publicRenderSupported(): bool
    {
        return self::supportsPublicRender($this->typeSlug());
    }

    public function settingsText(): ?string
    {
        $settings = $this->decodedSettings();

        if ($settings !== []) {
            return json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return null;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->decodedSettings(), $key, $default);
    }

    public function navigationMenuKey(): string
    {
        $configured = (string) ($this->setting('menu_key') ?? $this->setting('location') ?? '');

        if (in_array($configured, NavigationItem::menuKeys(), true)) {
            return $configured;
        }

        return ($this->subtitle === 'footer' || $this->slot === 'footer')
            ? NavigationItem::MENU_FOOTER
            : NavigationItem::MENU_PRIMARY;
    }

    public function navigationLocation(): string
    {
        return $this->navigationMenuKey();
    }

    public function galleryAssetIds(): array
    {
        return $this->blockAssets
            ->where('role', 'gallery_item')
            ->sortBy('position')
            ->pluck('asset_id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    private function decodedSettings(): array
    {
        return $this->decodeJsonArray($this->getRawOriginal('settings'));
    }

    private function hasSettingsValue(mixed $value): bool
    {
        return is_array($value) || (is_string($value) && trim($value) !== '');
    }

    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = $value;

        for ($depth = 0; $depth < 3; $depth++) {
            if (! is_string($decoded) || trim($decoded) === '') {
                return [];
            }

            $decoded = json_decode($decoded, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }

    public function galleryAssets(): Collection
    {
        $assetIds = $this->galleryAssetIds();

        if ($assetIds === []) {
            return collect();
        }

        return Asset::query()
            ->whereIn('id', $assetIds)
            ->get()
            ->sortBy(fn (Asset $asset) => array_search($asset->id, $assetIds, true))
            ->values();
    }

    public function attachmentAsset(): ?Asset
    {
        $structured = $this->blockAssets
            ->where('role', 'attachment')
            ->sortBy('position')
            ->first()?->asset;

        return $structured ?: $this->asset;
    }

    public function downloadAsset(): ?Asset
    {
        if ($this->typeSlug() === 'download') {
            return $this->asset;
        }

        return null;
    }

    public static function supportsAdminForm(?string $slug): bool
    {
        return $slug !== null && (
            View::exists('admin.blocks.types.'.$slug)
            || View::exists('admin.blocks.types.fallback')
        );
    }

    public static function supportsPublicRender(?string $slug): bool
    {
        return $slug !== null && (
            View::exists('pages.partials.blocks.'.$slug)
            || View::exists('pages.partials.blocks.fallback')
        );
    }
}
