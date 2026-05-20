<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use App\Models\BlockAsset;
use Illuminate\Support\Collection;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteVariable;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;

class SiteExportDataBuilder
{
    public function __construct(
        private readonly SharedSlotSourcePageManager $sharedSlotSourcePageManager,
        private readonly PageAssetPathValidator $pageAssetPathValidator,
    ) {}

    public function build(Site $site, bool $includesMedia): array
    {
        $site = $site->loadMissing(['siteLocales', 'locales', 'siteDomains', 'siteVariables']);
        $sharedSlots = SharedSlot::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get();
        $sharedSlotSourcePages = $sharedSlots
            ->mapWithKeys(fn (SharedSlot $sharedSlot) => [$sharedSlot->id => $this->sharedSlotSourcePageManager->findFor($sharedSlot)])
            ->filter(fn (?Page $page) => $page instanceof Page);
        $pages = Page::query()
            ->where('site_id', $site->id)
            ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
            ->with(['translations', 'slots.slotType', 'pageType', 'layout'])
            ->orderBy('id')
            ->get();
        $pageIds = $pages->pluck('id');
        $blockPageIds = $pageIds
            ->merge($sharedSlotSourcePages->pluck('id'))
            ->unique()
            ->values();

        $blocks = Block::query()
            ->whereIn('page_id', $blockPageIds)
            ->with(['blockType', 'slotType', 'blockAssets.galleryItemTranslations', 'textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get();
        $blockIds = $blocks->pluck('id');

        $navigationItems = NavigationItem::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->get();
        $pageAssets = PageAsset::query()->whereIn('page_id', $pageIds)->orderBy('sort_order')->orderBy('id')->get();

        $assetIds = $includesMedia
            ? collect()
                ->merge($blocks->pluck('media_id'))
                ->merge(BlockAsset::query()->whereIn('block_id', $blockIds)->pluck('media_id'))
                ->merge(PageTranslation::query()->whereIn('page_id', $pageIds)->pluck('og_image_media_id'))
                ->filter()
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
            : collect();

        $assets = $assetIds->isEmpty()
            ? collect()
            : Media::query()->whereIn('id', $assetIds)->orderBy('id')->get();

        $assetFolders = $includesMedia
            ? $this->mediaFoldersFor($assets)
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
            'site_domains' => $site->siteDomains->sortBy(fn ($siteDomain) => sprintf('%d-%s', $siteDomain->is_primary ? 0 : 1, $siteDomain->domain))->values()->map(fn ($siteDomain) => [
                'id' => $siteDomain->id,
                'site_id' => $siteDomain->site_id,
                'domain' => $siteDomain->domain,
                'is_primary' => (bool) $siteDomain->is_primary,
                'redirect_to_primary' => (bool) $siteDomain->redirect_to_primary,
                'status' => $siteDomain->status,
                'created_at' => $siteDomain->created_at?->toDateTimeString(),
                'updated_at' => $siteDomain->updated_at?->toDateTimeString(),
            ])->all(),
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
            'site_variables' => $site->siteVariables->sortBy(fn (SiteVariable $siteVariable) => sprintf('%010d-%010d', (int) $siteVariable->sort_order, (int) $siteVariable->id))->values()->map(fn (SiteVariable $siteVariable) => [
                'id' => $siteVariable->id,
                'site_id' => $siteVariable->site_id,
                'key' => $siteVariable->key,
                'label' => $siteVariable->label,
                'value' => $siteVariable->value,
                'sort_order' => (int) $siteVariable->sort_order,
                'is_enabled' => (bool) $siteVariable->is_enabled,
                'created_at' => $siteVariable->created_at?->toDateTimeString(),
                'updated_at' => $siteVariable->updated_at?->toDateTimeString(),
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
                'public_shell' => $page->publicShellPreset(),
                'settings' => Page::sanitizeSettings($page->settings),
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
                'seo_title' => $translation->seo_title,
                'seo_description' => $translation->seo_description,
                'seo_keywords' => $translation->seo_keywords,
                'og_title' => $translation->og_title,
                'og_description' => $translation->og_description,
                'og_image_media_id' => $includesMedia ? $translation->og_image_media_id : null,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ])->all(),
            'page_slots' => PageSlot::query()->whereIn('page_id', $pageIds)->orderBy('id')->get()->map(fn (PageSlot $slot) => [
                'id' => $slot->id,
                'page_id' => $slot->page_id,
                'page_export_id' => $slot->page_id,
                'slot_type_id' => $slot->slot_type_id,
                'slot_type_slug' => optional($slot->slotType)->slug,
                'source_type' => $slot->runtimeSourceType(),
                'shared_slot_handle' => $slot->runtimeSourceType() === PageSlot::SOURCE_TYPE_SHARED_SLOT
                    ? $slot->sharedSlot()->value('handle')
                    : null,
                'sort_order' => $slot->sort_order,
                'settings' => $slot->getRawOriginal('settings'),
                'created_at' => $slot->created_at?->toDateTimeString(),
                'updated_at' => $slot->updated_at?->toDateTimeString(),
            ])->all(),
            'page_assets' => $pageAssets->map(fn (PageAsset $pageAsset) => [
                'id' => $pageAsset->id,
                'page_id' => $pageAsset->page_id,
                'type' => $pageAsset->type,
                'path' => $pageAsset->path,
                'load_position' => $pageAsset->load_position,
                'is_defer' => (bool) $pageAsset->is_defer,
                'is_async' => (bool) $pageAsset->is_async,
                'is_module' => (bool) $pageAsset->is_module,
                'is_enabled' => (bool) $pageAsset->is_enabled,
                'sort_order' => $pageAsset->sort_order,
                'created_at' => $pageAsset->created_at?->toDateTimeString(),
                'updated_at' => $pageAsset->updated_at?->toDateTimeString(),
            ])->all(),
            'shared_slots' => $sharedSlots->map(function (SharedSlot $sharedSlot) use ($sharedSlotSourcePages) {
                $sourcePage = $sharedSlotSourcePages->get($sharedSlot->id);

                return [
                    'id' => $sharedSlot->id,
                    'site_id' => $sharedSlot->site_id,
                    'name' => $sharedSlot->name,
                    'handle' => $sharedSlot->handle,
                    'slot_name' => $sharedSlot->slot_name,
                    'public_shell' => $sharedSlot->public_shell,
                    'is_active' => (bool) $sharedSlot->is_active,
                    'source_page_id' => $sourcePage?->id,
                    'source_page_slug' => $sourcePage?->slug,
                    'created_at' => $sharedSlot->created_at?->toDateTimeString(),
                    'updated_at' => $sharedSlot->updated_at?->toDateTimeString(),
                ];
            })->all(),
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
                'media_id' => $includesMedia ? $block->media_id : null,
                'variant' => $block->getRawOriginal('variant'),
                'meta' => $block->getRawOriginal('meta'),
                'settings' => $block->getRawOriginal('settings'),
                'status' => $block->status,
                'is_system' => (bool) $block->is_system,
                'created_at' => $block->created_at?->toDateTimeString(),
                'updated_at' => $block->updated_at?->toDateTimeString(),
            ])->all(),
            'block_media' => $includesMedia
                ? BlockAsset::query()->whereIn('block_id', $blockIds)->orderBy('id')->get()->map(fn (BlockAsset $blockAsset) => [
                    'id' => $blockAsset->id,
                    'block_id' => $blockAsset->block_id,
                    'media_id' => $blockAsset->media_id,
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
            'block_gallery_item_translations' => $blocks->flatMap(fn (Block $block) => $block->blockAssets->flatMap(fn ($blockAsset) => $blockAsset->galleryItemTranslations->map(fn (BlockGalleryItemTranslation $translation) => [
                'id' => $translation->id,
                'block_media_id' => $translation->block_media_id,
                'locale_id' => $translation->locale_id,
                'alt_text' => $translation->alt_text,
                'caption' => $translation->caption,
                'overlay_title' => $translation->overlay_title,
                'overlay_text' => $translation->overlay_text,
                'created_at' => $translation->created_at?->toDateTimeString(),
                'updated_at' => $translation->updated_at?->toDateTimeString(),
            ]))->values())->values()->all(),
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
                'icon' => $item->icon,
                'position' => $item->position,
                'visibility' => $item->visibility,
                'is_system' => (bool) $item->is_system,
                'created_at' => $item->created_at?->toDateTimeString(),
                'updated_at' => $item->updated_at?->toDateTimeString(),
            ])->all(),
            'media_folders' => $assetFolders->map(fn (MediaFolder $folder) => [
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'created_at' => $folder->created_at?->toDateTimeString(),
                'updated_at' => $folder->updated_at?->toDateTimeString(),
            ])->all(),
            'media' => $assets->map(fn (Media $asset) => [
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
                'site_variables' => $site->siteVariables->count(),
                'pages' => $pages->count(),
                'page_translations' => PageTranslation::query()->whereIn('page_id', $pageIds)->count(),
                'page_slots' => PageSlot::query()->whereIn('page_id', $pageIds)->count(),
                'page_assets' => $pageAssets->count(),
                'shared_slots' => $sharedSlots->count(),
                'blocks' => $blocks->count(),
                'block_media' => $includesMedia ? BlockAsset::query()->whereIn('block_id', $blockIds)->count() : 0,
                'block_text_translations' => $blocks->sum(fn (Block $block) => $block->textTranslations->count()),
                'block_button_translations' => $blocks->sum(fn (Block $block) => $block->buttonTranslations->count()),
                'block_image_translations' => $blocks->sum(fn (Block $block) => $block->imageTranslations->count()),
                'block_contact_form_translations' => $blocks->sum(fn (Block $block) => $block->contactFormTranslations->count()),
                'block_gallery_item_translations' => $blocks->sum(fn (Block $block) => $block->blockAssets->sum(fn ($blockAsset) => $blockAsset->galleryItemTranslations->count())),
                'navigation_items' => $navigationItems->count(),
                'media_folders' => $assetFolders->count(),
                'media' => $assets->count(),
                'page_asset_files' => $includesMedia
                    ? $pageAssets->filter(function (PageAsset $pageAsset): bool {
                        try {
                            $this->pageAssetPathValidator->relativePublicPath($pageAsset->path);

                            return true;
                        } catch (\InvalidArgumentException) {
                            return false;
                        }
                    })->count()
                    : 0,
            ],
        ];
    }

    private function mediaFoldersFor(Collection $assets): Collection
    {
        $folderIds = $assets->pluck('folder_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();

        if ($folderIds->isEmpty()) {
            return collect();
        }

        $folders = MediaFolder::query()->whereIn('id', $folderIds)->get()->keyBy('id');
        $allFolderIds = $folderIds->values();

        foreach ($folders as $folder) {
            $parentId = $folder->parent_id;

            while ($parentId) {
                $allFolderIds->push((int) $parentId);
                $parentId = MediaFolder::query()->whereKey($parentId)->value('parent_id');
            }
        }

        return MediaFolder::query()->whereIn('id', $allFolderIds->unique()->values())->orderBy('id')->get();
    }
}
