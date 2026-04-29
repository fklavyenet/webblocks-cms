<?php

namespace App\Support\Sites\ExportImport;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use Illuminate\Support\Collection;

class SiteExportDataBuilder
{
    public function build(Site $site, bool $includesMedia): array
    {
        $site = $site->loadMissing(['siteLocales', 'locales']);
        $pages = Page::query()
            ->where('site_id', $site->id)
            ->with(['translations', 'slots.slotType', 'pageType', 'layout'])
            ->orderBy('id')
            ->get();
        $pageIds = $pages->pluck('id');

        $blocks = Block::query()
            ->whereIn('page_id', $pageIds)
            ->with(['blockType', 'slotType', 'blockAssets', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get();
        $blockIds = $blocks->pluck('id');

        $navigationItems = NavigationItem::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get();

        $assetIds = $includesMedia
            ? collect()
                ->merge($blocks->pluck('asset_id'))
                ->merge(BlockAsset::query()->whereIn('block_id', $blockIds)->pluck('asset_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        $assets = $assetIds->isEmpty()
            ? collect()
            : Asset::query()->whereIn('id', $assetIds)->orderBy('id')->get();

        $assetFolders = $includesMedia
            ? $this->assetFoldersFor($assets)
            : collect();

        $locales = Locale::query()
            ->whereIn('id', $site->siteLocales->pluck('locale_id'))
            ->orderBy('id')
            ->get();

        return [
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'handle' => $site->handle,
                'domain' => $site->domain,
                'is_primary' => (bool) $site->is_primary,
                'created_at' => $site->created_at?->toDateTimeString(),
                'updated_at' => $site->updated_at?->toDateTimeString(),
            ],
            'locales' => $locales->map(fn (Locale $locale) => [
                'id' => $locale->id,
                'code' => $locale->code,
                'name' => $locale->name,
                'is_default' => (bool) $locale->is_default,
                'is_enabled' => (bool) $locale->is_enabled,
                'created_at' => $locale->created_at?->toDateTimeString(),
                'updated_at' => $locale->updated_at?->toDateTimeString(),
            ])->all(),
            'site_locales' => $site->siteLocales->sortBy('id')->values()->map(fn ($siteLocale) => [
                'id' => $siteLocale->id,
                'site_id' => $siteLocale->site_id,
                'locale_id' => $siteLocale->locale_id,
                'is_enabled' => (bool) $siteLocale->is_enabled,
                'created_at' => $siteLocale->created_at?->toDateTimeString(),
                'updated_at' => $siteLocale->updated_at?->toDateTimeString(),
            ])->all(),
            'pages' => $pages->map(fn (Page $page) => [
                'id' => $page->id,
                'site_id' => $page->site_id,
                'title' => $page->defaultTranslation()?->name,
                'slug' => $page->defaultTranslation()?->slug,
                'page_type' => $page->page_type,
                'page_type_slug' => $page->page_type_id ? optional($page->pageType)->slug : null,
                'layout_slug' => $page->layout_id ? optional($page->layout)->slug : null,
                'status' => $page->status,
                'created_at' => $page->created_at?->toDateTimeString(),
                'updated_at' => $page->updated_at?->toDateTimeString(),
            ])->all(),
            'page_translations' => PageTranslation::query()->whereIn('page_id', $pageIds)->orderBy('id')->get()->map(fn (PageTranslation $translation) => [
                'id' => $translation->id,
                'page_id' => $translation->page_id,
                'locale_id' => $translation->locale_id,
                'name' => $translation->name,
                'slug' => $translation->slug,
                'path' => $translation->path,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ])->all(),
            'page_slots' => PageSlot::query()->whereIn('page_id', $pageIds)->orderBy('id')->get()->map(fn (PageSlot $slot) => [
                'id' => $slot->id,
                'page_id' => $slot->page_id,
                'slot_type_id' => $slot->slot_type_id,
                'slot_type_slug' => optional($slot->slotType)->slug,
                'sort_order' => $slot->sort_order,
                'created_at' => $slot->created_at?->toDateTimeString(),
                'updated_at' => $slot->updated_at?->toDateTimeString(),
            ])->all(),
            'blocks' => $blocks->map(fn (Block $block) => [
                'id' => $block->id,
                'page_id' => $block->page_id,
                'parent_id' => $block->parent_id,
                'type' => $block->type,
                'block_type_slug' => $block->blockType?->slug,
                'source_type' => $block->source_type,
                'slot' => $block->slot,
                'slot_type_slug' => $block->slotType?->slug,
                'sort_order' => $block->sort_order,
                'title' => $block->getRawOriginal('title'),
                'subtitle' => $block->getRawOriginal('subtitle'),
                'content' => $block->getRawOriginal('content'),
                'url' => $block->getRawOriginal('url'),
                'asset_id' => $includesMedia ? $block->asset_id : null,
                'variant' => $block->getRawOriginal('variant'),
                'meta' => $block->getRawOriginal('meta'),
                'settings' => $block->getRawOriginal('settings'),
                'status' => $block->status,
                'is_system' => (bool) $block->is_system,
                'created_at' => $block->created_at?->toDateTimeString(),
                'updated_at' => $block->updated_at?->toDateTimeString(),
            ])->all(),
            'block_assets' => $includesMedia
                ? BlockAsset::query()->whereIn('block_id', $blockIds)->orderBy('id')->get()->map(fn (BlockAsset $blockAsset) => [
                    'id' => $blockAsset->id,
                    'block_id' => $blockAsset->block_id,
                    'asset_id' => $blockAsset->asset_id,
                    'role' => $blockAsset->role,
                    'position' => $blockAsset->position,
                    'created_at' => $blockAsset->created_at?->toDateTimeString(),
                    'updated_at' => $blockAsset->updated_at?->toDateTimeString(),
                ])->all()
                : [],
            'block_text_translations' => $blocks->flatMap(fn (Block $block) => $block->textTranslations->map(fn ($translation) => [
                'id' => $translation->id,
                'block_id' => $translation->block_id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'eyebrow' => $translation->eyebrow,
                'subtitle' => $translation->subtitle,
                'content' => $translation->content,
                'meta' => $translation->meta,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ]))->values()->all(),
            'block_button_translations' => $blocks->flatMap(fn (Block $block) => $block->buttonTranslations->map(fn ($translation) => [
                'id' => $translation->id,
                'block_id' => $translation->block_id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ]))->values()->all(),
            'block_image_translations' => $blocks->flatMap(fn (Block $block) => $block->imageTranslations->map(fn ($translation) => [
                'id' => $translation->id,
                'block_id' => $translation->block_id,
                'locale_id' => $translation->locale_id,
                'caption' => $translation->caption,
                'alt_text' => $translation->alt_text,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ]))->values()->all(),
            'block_contact_form_translations' => $blocks->flatMap(fn (Block $block) => $block->contactFormTranslations->map(fn ($translation) => [
                'id' => $translation->id,
                'block_id' => $translation->block_id,
                'locale_id' => $translation->locale_id,
                'title' => $translation->title,
                'content' => $translation->content,
                'submit_label' => $translation->submit_label,
                'success_message' => $translation->success_message,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ]))->values()->all(),
            'navigation_items' => $navigationItems->map(fn (NavigationItem $item) => [
                'id' => $item->id,
                'site_id' => $item->site_id,
                'menu_key' => $item->menu_key,
                'parent_id' => $item->parent_id,
                'page_id' => $item->page_id,
                'title' => $item->title,
                'link_type' => $item->link_type,
                'url' => $item->url,
                'target' => $item->target,
                'position' => $item->position,
                'visibility' => $item->visibility,
                'is_system' => (bool) $item->is_system,
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ])->all(),
            'asset_folders' => $assetFolders->map(fn (AssetFolder $folder) => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'created_at' => $folder->created_at?->toDateTimeString(),
                'updated_at' => $folder->updated_at?->toDateTimeString(),
            ])->all(),
            'assets' => $assets->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'folder_id' => $asset->folder_id,
                'disk' => $asset->disk,
                'path' => $asset->path,
                'filename' => $asset->filename,
                'original_name' => $asset->original_name,
                'extension' => $asset->extension,
                'mime_type' => $asset->mime_type,
                'size' => $asset->size,
                'kind' => $asset->kind,
                'visibility' => $asset->visibility,
                'title' => $asset->title,
                'alt_text' => $asset->alt_text,
                'caption' => $asset->caption,
                'description' => $asset->description,
                'width' => $asset->width,
                'height' => $asset->height,
                'duration' => $asset->duration,
                'uploaded_by' => $asset->uploaded_by,
                'created_at' => $asset->created_at?->toDateTimeString(),
                'updated_at' => $asset->updated_at?->toDateTimeString(),
            ])->all(),
            'counts' => [
                'locales' => $locales->count(),
                'site_locales' => $site->siteLocales->count(),
                'pages' => $pages->count(),
                'page_translations' => PageTranslation::query()->whereIn('page_id', $pageIds)->count(),
                'page_slots' => PageSlot::query()->whereIn('page_id', $pageIds)->count(),
                'blocks' => $blocks->count(),
                'block_assets' => $includesMedia ? BlockAsset::query()->whereIn('block_id', $blockIds)->count() : 0,
                'block_text_translations' => $blocks->sum(fn (Block $block) => $block->textTranslations->count()),
                'block_button_translations' => $blocks->sum(fn (Block $block) => $block->buttonTranslations->count()),
                'block_image_translations' => $blocks->sum(fn (Block $block) => $block->imageTranslations->count()),
                'block_contact_form_translations' => $blocks->sum(fn (Block $block) => $block->contactFormTranslations->count()),
                'navigation_items' => $navigationItems->count(),
                'asset_folders' => $assetFolders->count(),
                'assets' => $assets->count(),
            ],
        ];
    }

    private function assetFoldersFor(Collection $assets): Collection
    {
        $folderIds = $assets->pluck('folder_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($folderIds->isEmpty()) {
            return collect();
        }

        $folders = AssetFolder::query()->whereIn('id', $folderIds)->get()->keyBy('id');
        $allFolderIds = $folderIds->values();

        foreach ($folders as $folder) {
            $parentId = $folder->parent_id;

            while ($parentId) {
                $allFolderIds->push((int) $parentId);
                $parentId = AssetFolder::query()->whereKey($parentId)->value('parent_id');
            }
        }

        return AssetFolder::query()->whereIn('id', $allFolderIds->unique()->values())->orderBy('id')->get();
    }
}
