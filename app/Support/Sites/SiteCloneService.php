<?php

namespace App\Support\Sites;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockButtonTranslation;
use App\Models\BlockContactFormTranslation;
use App\Models\BlockImageTranslation;
use App\Models\BlockTextTranslation;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Locale;
use App\Models\Site;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SiteCloneService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SiteDomainNormalizer $domainNormalizer,
    ) {}

    public function clone(string|int $source, string|int $target, SiteCloneOptions $options): SiteCloneResult
    {
        $sourceSite = $this->resolveSource($source);
        $targetSite = $this->resolveTarget($target);

        if ($targetSite && $targetSite->is($sourceSite)) {
            throw new RuntimeException('Source and target sites must be different.');
        }

        if ($options->dryRun) {
            return $this->dryRunResult($sourceSite, $targetSite, $target, $options);
        }

        return $this->db->transaction(function () use ($sourceSite, $targetSite, $target, $options): SiteCloneResult {
            $targetCreated = false;

            if (! $targetSite) {
                $targetSite = $this->createTargetSite($sourceSite, $target, $options);
                $targetCreated = true;
            } elseif ($this->targetHasCloneableContent($targetSite) && ! $options->overwriteTarget) {
                throw new RuntimeException('Target site already has content. Use overwrite to replace target content safely.');
            }

            $this->syncTargetSiteMetadata($targetSite, $sourceSite, $target, $options);

            if ($options->overwriteTarget) {
                $this->clearTargetContent($targetSite);
            }

            $counts = [
                'sites_created' => $targetCreated ? 1 : 0,
                'sites_updated' => 1,
                'pages_cloned' => 0,
                'page_translations_cloned' => 0,
                'page_slots_cloned' => 0,
                'blocks_cloned' => 0,
                'block_translation_rows_cloned' => 0,
                'navigation_items_cloned' => 0,
                'assets_linked' => 0,
                'assets_cloned' => 0,
                'block_asset_links_cloned' => 0,
                'files_copied' => 0,
            ];

            $assetMap = [];
            $pageMap = $this->clonePages($sourceSite, $targetSite, $options, $counts);
            $this->cloneBlocks($sourceSite, $targetSite, $pageMap, $assetMap, $options, $counts);

            if ($options->withNavigation) {
                $this->cloneNavigation($sourceSite, $targetSite, $pageMap, $options, $counts);
            }

            return new SiteCloneResult(
                sourceSite: $sourceSite,
                targetSite: $targetSite->fresh(['locales']),
                targetCreated: $targetCreated,
                dryRun: false,
                counts: $counts,
                messages: [],
            );
        });
    }

    public function resolveSiteIdentifier(string|int $identifier): ?Site
    {
        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            return Site::query()->find((int) $identifier);
        }

        $normalized = trim((string) $identifier);

        if ($normalized === '') {
            return null;
        }

        $domain = $this->domainNormalizer->normalize($normalized);

        return Site::query()
            ->where('handle', str($normalized)->slug()->toString())
            ->orWhere('name', $normalized)
            ->when($domain !== null, fn ($query) => $query->orWhere('domain', $domain))
            ->first();
    }

    private function dryRunResult(Site $sourceSite, ?Site $targetSite, string|int $target, SiteCloneOptions $options): SiteCloneResult
    {
        if ($targetSite && $this->targetHasCloneableContent($targetSite) && ! $options->overwriteTarget) {
            throw new RuntimeException('Target site already has content. Use overwrite to replace target content safely.');
        }

        $counts = $this->sourceCounts($sourceSite, $options);

        return new SiteCloneResult(
            sourceSite: $sourceSite,
            targetSite: $targetSite ?: $this->buildDryRunTargetSite($sourceSite, $target, $options),
            targetCreated: $targetSite === null,
            dryRun: true,
            counts: $counts,
            messages: [],
        );
    }

    private function resolveSource(string|int $source): Site
    {
        return $this->resolveSiteIdentifier($source)
            ?? throw new RuntimeException('Source site could not be resolved.');
    }

    private function resolveTarget(string|int $target): ?Site
    {
        return $this->resolveSiteIdentifier($target);
    }

    private function createTargetSite(Site $sourceSite, string|int $target, SiteCloneOptions $options): Site
    {
        $handle = $options->targetHandle
            ?? (is_string($target) ? str($target)->slug()->toString() : null)
            ?? throw new RuntimeException('Target handle is required when creating a new site.');

        if (Site::query()->where('handle', $handle)->exists()) {
            throw new RuntimeException('Target handle already exists.');
        }

        return Site::query()->create([
            'name' => $options->targetName ?? $sourceSite->name.' Clone',
            'handle' => $handle,
            'domain' => $this->normalizedTargetDomain($options),
            'is_primary' => false,
        ]);
    }

    private function syncTargetSiteMetadata(Site $targetSite, Site $sourceSite, string|int $target, SiteCloneOptions $options): void
    {
        $updates = [];

        if ($options->targetName !== null) {
            $updates['name'] = $options->targetName;
        }

        if ($options->targetHandle !== null) {
            $updates['handle'] = $options->targetHandle;
        }

        if ($options->targetDomain !== null) {
            $updates['domain'] = $this->normalizedTargetDomain($options);
        }

        if ($updates !== []) {
            $targetSite->update($updates);
            $targetSite->refresh();
        }

        $targetSite->locales()->sync(
            $sourceSite->siteLocales()
                ->get()
                ->mapWithKeys(fn ($siteLocale) => [$siteLocale->locale_id => ['is_enabled' => (bool) $siteLocale->is_enabled]])
                ->all()
        );
    }

    private function clearTargetContent(Site $targetSite): void
    {
        NavigationItem::query()->where('site_id', $targetSite->id)->delete();
        Page::query()->where('site_id', $targetSite->id)->delete();
    }

    private function targetHasCloneableContent(Site $targetSite): bool
    {
        return Page::query()->where('site_id', $targetSite->id)->exists()
            || NavigationItem::query()->where('site_id', $targetSite->id)->exists();
    }

    private function clonePages(Site $sourceSite, Site $targetSite, SiteCloneOptions $options, array &$counts): array
    {
        $pageMap = [];

        $sourcePages = Page::query()
            ->where('site_id', $sourceSite->id)
            ->with(['translations.locale', 'slots'])
            ->orderBy('id')
            ->get();

        foreach ($sourcePages as $page) {
            $defaultTranslation = $page->defaultTranslation();
            $canonicalName = $defaultTranslation?->name ?? $page->name ?? 'Cloned Page';
            $canonicalSlug = $defaultTranslation?->slug ?? $page->slug ?? Str::slug($canonicalName);

            $newPage = Page::query()->create([
                'site_id' => $targetSite->id,
                'title' => $canonicalName,
                'slug' => $canonicalSlug,
                'page_type' => $page->page_type,
                'status' => $page->status,
            ]);

            $pageMap[$page->id] = $newPage->id;
            $counts['pages_cloned']++;

            $newPage->translations()->delete();

            foreach ($page->translations as $translation) {
                if (! $options->withTranslations && ! $translation->locale?->is_default) {
                    continue;
                }

                PageTranslation::query()->create([
                    'page_id' => $newPage->id,
                    'site_id' => $targetSite->id,
                    'locale_id' => $translation->locale_id,
                    'name' => $translation->name,
                    'slug' => $translation->slug,
                    'path' => $translation->path,
                    'created_at' => $translation->created_at,
                    'updated_at' => $translation->updated_at,
                ]);

                $counts['page_translations_cloned']++;
            }

            foreach ($page->slots as $slot) {
                PageSlot::query()->create([
                    'page_id' => $newPage->id,
                    'slot_type_id' => $slot->slot_type_id,
                    'sort_order' => $slot->sort_order,
                    'created_at' => $slot->created_at,
                    'updated_at' => $slot->updated_at,
                ]);

                $counts['page_slots_cloned']++;
            }
        }

        return $pageMap;
    }

    private function cloneBlocks(Site $sourceSite, Site $targetSite, array $pageMap, array &$assetMap, SiteCloneOptions $options, array &$counts): void
    {
        $blockMap = [];

        $blocks = Block::query()
            ->whereIn('page_id', array_keys($pageMap))
            ->with(['blockAssets', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get();

        foreach ($blocks as $block) {
            $attributes = Arr::except($block->getAttributes(), ['id', 'parent_id', 'page_id', 'created_at', 'updated_at']);
            $attributes['page_id'] = $pageMap[$block->page_id];
            $attributes['parent_id'] = null;
            $attributes['created_at'] = $block->created_at;
            $attributes['updated_at'] = $block->updated_at;

            if (! $options->withMedia) {
                $attributes['asset_id'] = null;
            } elseif ($block->asset_id) {
                $attributes['asset_id'] = $this->clonedAssetId($block->asset_id, $assetMap, $options, $counts);
            }

            $newBlock = Block::query()->create($attributes);
            $blockMap[$block->id] = $newBlock->id;
            $counts['blocks_cloned']++;

            foreach ($block->blockAssets as $blockAsset) {
                if (! $options->withMedia) {
                    continue;
                }

                BlockAsset::query()->create([
                    'block_id' => $newBlock->id,
                    'asset_id' => $this->clonedAssetId($blockAsset->asset_id, $assetMap, $options, $counts),
                    'role' => $blockAsset->role,
                    'position' => $blockAsset->position,
                    'created_at' => $blockAsset->created_at,
                    'updated_at' => $blockAsset->updated_at,
                ]);

                $counts['block_asset_links_cloned']++;
            }

            $counts['block_translation_rows_cloned'] += $this->cloneBlockTranslations($block, $newBlock, $options);
        }

        foreach ($blocks as $block) {
            if (! $block->parent_id || ! isset($blockMap[$block->id], $blockMap[$block->parent_id])) {
                continue;
            }

            Block::query()->whereKey($blockMap[$block->id])->update(['parent_id' => $blockMap[$block->parent_id]]);
        }
    }

    private function cloneNavigation(Site $sourceSite, Site $targetSite, array $pageMap, SiteCloneOptions $options, array &$counts): void
    {
        $navigationMap = [];

        $items = NavigationItem::query()
            ->where('site_id', $sourceSite->id)
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $attributes = Arr::except($item->getAttributes(), ['id', 'site_id', 'parent_id', 'created_at', 'updated_at']);
            $attributes['site_id'] = $targetSite->id;
            $attributes['parent_id'] = null;
            $attributes['page_id'] = $item->page_id ? ($pageMap[$item->page_id] ?? null) : null;
            $attributes['created_at'] = $item->created_at;
            $attributes['updated_at'] = $item->updated_at;

            $newItem = NavigationItem::query()->create($attributes);
            $navigationMap[$item->id] = $newItem->id;
            $counts['navigation_items_cloned']++;
        }

        foreach ($items as $item) {
            if (! $item->parent_id || ! isset($navigationMap[$item->id], $navigationMap[$item->parent_id])) {
                continue;
            }

            NavigationItem::query()->whereKey($navigationMap[$item->id])->update([
                'parent_id' => $navigationMap[$item->parent_id],
            ]);
        }
    }

    private function cloneBlockTranslations(Block $source, Block $target, SiteCloneOptions $options): int
    {
        $count = 0;
        $defaultLocaleId = $this->defaultLocaleId();

        foreach ($source->textTranslations as $translation) {
            if (! $options->withTranslations && $translation->locale_id !== $defaultLocaleId) {
                continue;
            }

            BlockTextTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'subtitle' => $translation->subtitle,
                'content' => $translation->content,
                'created_at' => $translation->created_at,
                'updated_at' => $translation->updated_at,
            ]);
            $count++;
        }

        foreach ($source->buttonTranslations as $translation) {
            if (! $options->withTranslations && $translation->locale_id !== $defaultLocaleId) {
                continue;
            }

            BlockButtonTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'created_at' => $translation->created_at,
                'updated_at' => $translation->updated_at,
            ]);
            $count++;
        }

        foreach ($source->imageTranslations as $translation) {
            if (! $options->withTranslations && $translation->locale_id !== $defaultLocaleId) {
                continue;
            }

            BlockImageTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'caption' => $translation->caption,
                'alt_text' => $translation->alt_text,
                'created_at' => $translation->created_at,
                'updated_at' => $translation->updated_at,
            ]);
            $count++;
        }

        foreach ($source->contactFormTranslations as $translation) {
            if (! $options->withTranslations && $translation->locale_id !== $defaultLocaleId) {
                continue;
            }

            BlockContactFormTranslation::query()->create([
                'block_id' => $target->id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'content' => $translation->content,
                'submit_label' => $translation->submit_label,
                'success_message' => $translation->success_message,
                'created_at' => $translation->created_at,
                'updated_at' => $translation->updated_at,
            ]);
            $count++;
        }

        return $count;
    }

    private function clonedAssetId(int $assetId, array &$assetMap, SiteCloneOptions $options, array &$counts): int
    {
        if (! $options->copyMediaFiles) {
            if (! isset($assetMap[$assetId])) {
                $assetMap[$assetId] = $assetId;
                $counts['assets_linked']++;
            }

            return $assetId;
        }

        if (isset($assetMap[$assetId])) {
            return $assetMap[$assetId];
        }

        $asset = Asset::query()->findOrFail($assetId);
        $attributes = Arr::except($asset->getAttributes(), ['id', 'path', 'filename', 'created_at', 'updated_at']);
        $attributes['path'] = $this->copyAssetFile($asset, $counts);
        $attributes['filename'] = basename($attributes['path']);
        $attributes['created_at'] = $asset->created_at;
        $attributes['updated_at'] = $asset->updated_at;

        $newAsset = Asset::query()->create($attributes);
        $assetMap[$assetId] = $newAsset->id;
        $counts['assets_cloned']++;

        return $newAsset->id;
    }

    private function copyAssetFile(Asset $asset, array &$counts): string
    {
        $disk = Storage::disk($asset->disk);

        if (! $disk->exists($asset->path)) {
            throw new RuntimeException('Asset file could not be copied because the source file is missing: '.$asset->path);
        }

        $extension = pathinfo($asset->path, PATHINFO_EXTENSION);
        $basename = pathinfo($asset->filename ?: $asset->path, PATHINFO_FILENAME);
        $directory = pathinfo($asset->path, PATHINFO_DIRNAME);
        $newFilename = str($basename)->slug()->append('-', strtolower(str()->random(10)))->toString();
        $newPath = trim($directory === '.' ? '' : $directory, '/').'/'.$newFilename.($extension !== '' ? '.'.$extension : '');
        $newPath = ltrim($newPath, '/');

        $disk->copy($asset->path, $newPath);
        $counts['files_copied']++;

        return $newPath;
    }

    private function sourceCounts(Site $sourceSite, SiteCloneOptions $options): array
    {
        $pageIds = Page::query()->where('site_id', $sourceSite->id)->pluck('id');
        $blockIds = Block::query()->whereIn('page_id', $pageIds)->pluck('id');
        $defaultLocaleId = $this->defaultLocaleId();
        $assetIds = collect()
            ->merge(Block::query()->whereIn('id', $blockIds)->whereNotNull('asset_id')->pluck('asset_id'))
            ->merge(BlockAsset::query()->whereIn('block_id', $blockIds)->pluck('asset_id'))
            ->filter()
            ->unique()
            ->values();

        return [
            'sites_created' => 0,
            'sites_updated' => 1,
            'pages_cloned' => $pageIds->count(),
            'page_translations_cloned' => PageTranslation::query()
                ->whereIn('page_id', $pageIds)
                ->when(! $options->withTranslations && $defaultLocaleId, fn ($query) => $query->where('locale_id', $defaultLocaleId))
                ->count(),
            'page_slots_cloned' => PageSlot::query()->whereIn('page_id', $pageIds)->count(),
            'blocks_cloned' => $blockIds->count(),
            'block_translation_rows_cloned' => $options->withTranslations ? (
                BlockTextTranslation::query()->whereIn('block_id', $blockIds)->count()
                + BlockButtonTranslation::query()->whereIn('block_id', $blockIds)->count()
                + BlockImageTranslation::query()->whereIn('block_id', $blockIds)->count()
                + BlockContactFormTranslation::query()->whereIn('block_id', $blockIds)->count()
            ) : (
                BlockTextTranslation::query()->whereIn('block_id', $blockIds)->where('locale_id', $defaultLocaleId)->count()
                + BlockButtonTranslation::query()->whereIn('block_id', $blockIds)->where('locale_id', $defaultLocaleId)->count()
                + BlockImageTranslation::query()->whereIn('block_id', $blockIds)->where('locale_id', $defaultLocaleId)->count()
                + BlockContactFormTranslation::query()->whereIn('block_id', $blockIds)->where('locale_id', $defaultLocaleId)->count()
            ),
            'navigation_items_cloned' => $options->withNavigation ? NavigationItem::query()->where('site_id', $sourceSite->id)->count() : 0,
            'assets_linked' => $options->withMedia && ! $options->copyMediaFiles ? $assetIds->count() : 0,
            'assets_cloned' => $options->withMedia && $options->copyMediaFiles ? $assetIds->count() : 0,
            'block_asset_links_cloned' => $options->withMedia ? BlockAsset::query()->whereIn('block_id', $blockIds)->count() : 0,
            'files_copied' => 0,
        ];
    }

    private function defaultLocaleId(): ?int
    {
        return Locale::query()->where('is_default', true)->value('id');
    }

    private function buildDryRunTargetSite(Site $sourceSite, string|int $target, SiteCloneOptions $options): Site
    {
        return new Site([
            'name' => $options->targetName ?? $sourceSite->name.' Clone',
            'handle' => $options->targetHandle ?? (is_string($target) ? str($target)->slug()->toString() : null),
            'domain' => $this->normalizedTargetDomain($options),
            'is_primary' => false,
        ]);
    }

    private function normalizedTargetDomain(SiteCloneOptions $options): ?string
    {
        return $options->targetDomain !== null
            ? $this->domainNormalizer->normalize($options->targetDomain)
            : null;
    }
}
