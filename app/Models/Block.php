<?php

namespace App\Models;

use App\Support\Blocks\BlockTranslationRegistry;
use App\Support\Blocks\BlockTranslationResolver;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class Block extends Model
{
    use HasFactory;

    protected $fillable = [
        'page_id',
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
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
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
        if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            $layoutName = $this->layoutAdminName();

            return $layoutName !== null
                ? $this->typeName().($this->typeSlug() === 'cluster' ? ' — ' : ' -- ').$layoutName
                : $this->typeName();
        }

        if ($this->typeSlug() === 'plain_text') {
            return $this->content ?: $this->typeName();
        }

        return $this->title ?: $this->typeName();
    }

    public function editorSummary(): ?string
    {
        if (in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            $childCount = $this->children->count();

            return $childCount > 0
                ? $childCount.' '.Str::plural('child block', $childCount)
                : 'Layout wrapper';
        }

        if ($this->typeSlug() === 'navigation-auto' || $this->typeSlug() === 'menu') {
            return 'Location: '.str($this->navigationLocation())->headline();
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
            $this->typeSlug() === 'header' ? $this->variant : null,
            $this->subtitle,
            filled($this->content) ? str(strip_tags((string) $this->content))->squish()->limit(88)->toString() : null,
            $this->url,
            $this->variant,
        ])->first(fn ($value) => filled($value));

        return $summary ? (string) $summary : null;
    }

    public function layoutAdminName(): ?string
    {
        if (! in_array($this->typeSlug(), ['section', 'container', 'cluster', 'grid'], true)) {
            return null;
        }

        $name = trim((string) $this->setting('layout_name', ''));

        return $name !== '' ? $name : null;
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
        return (bool) ($this->blockType?->is_container ?? false);
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
        if (is_array($this->decodedSettings())) {
            return json_encode($this->decodedSettings(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }

        return $this->settings;
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
        if (is_array($this->settings)) {
            return $this->settings;
        }

        if (! is_string($this->settings) || trim($this->settings) === '') {
            return [];
        }

        $decoded = json_decode($this->settings, true);

        return is_array($decoded) ? $decoded : [];
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
