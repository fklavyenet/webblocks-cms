<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use WebBlocks\Cms\Models\BlockMedia as BlockAsset;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockButtonTranslation;
use WebBlocks\Cms\Models\BlockContactFormTranslation;
use WebBlocks\Cms\Models\BlockGalleryItemTranslation;
use WebBlocks\Cms\Models\BlockImageTranslation;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\PageType;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Media\LegacyAssetPayloadNormalizer;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\System\SystemBackupManager;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class SitePromotionApplier
{
    public function __construct(
        private readonly SitePromotionPlanStore $planStore,
        private readonly SitePromotionPackageInspector $packageInspector,
        private readonly SystemBackupManager $systemBackupManager,
        private readonly DatabaseManager $db,
        private readonly PublicSearchIndexer $searchIndexer,
        private readonly BlockTranslationWriter $blockTranslationWriter,
        private readonly SharedSlotSourcePageManager $sharedSlotSourcePageManager,
        private readonly PageAssetPathValidator $pageAssetPathValidator,
        private readonly LegacyAssetPayloadNormalizer $legacyAssetPayloadNormalizer,
    ) {}

    public function apply(string $planToken, ?int $userId = null): SitePromotionResult
    {
        $plan = $this->planStore->load($planToken);

        if (! $plan instanceof SitePromotionPlan || ! $plan->canApply()) {
            throw new RuntimeException('Apply requires a successful dry run plan.');
        }

        $targetSite = Site::query()->find((int) ($plan->targetSite['id'] ?? 0));

        if (! $targetSite instanceof Site) {
            throw new RuntimeException('Target site is missing.');
        }

        $inspection = $this->packageInspector->inspectStoredArchive($plan->archivePath, $plan->archiveName);

        if ($inspection->errors !== []) {
            throw new RuntimeException($inspection->errors[0]);
        }

        $backup = $this->systemBackupManager->createManualBackup($userId, 'Safety backup before site promotion into '.$targetSite->handle);

        if (! $backup->isSuccessful()) {
            throw new RuntimeException('Safety backup could not be created.');
        }

        $copiedFiles = [];
        $archive = new ZipArchive;

        if ($archive->open(Storage::disk($inspection->archiveDisk)->path($inspection->archivePath)) !== true) {
            throw new RuntimeException('Promotion package could not be reopened.');
        }

        try {
            $this->db->transaction(function () use ($plan, $targetSite, $inspection, $archive, &$copiedFiles): void {
                $payload = $this->legacyAssetPayloadNormalizer->normalizePayload($inspection->payload);
                $localeMap = $this->ensureLocales($payload);
                $assetMaps = $plan->applyAssets() && $inspection->includesAssets
                    ? $this->syncAssets($archive, $payload, $copiedFiles)
                    : ['folder_map' => [], 'asset_map' => []];

                $this->syncSiteMetadata($targetSite, $payload, $assetMaps['asset_map'], $plan->applyAssets());
                $this->syncSiteLocales($targetSite, $payload, $localeMap, $plan->isMirror());
                $this->syncSiteVariables($targetSite, $payload, $plan->isMirror());

                $sharedSlotState = $this->syncSharedSlots($targetSite, $payload, $plan->isMirror());
                $pageState = $this->syncPages($targetSite, $payload);
                $allPageMap = $pageState['source_page_map'] + $sharedSlotState['source_page_map'];

                $this->syncPageTranslations($targetSite, $payload, $allPageMap, $localeMap, $assetMaps['asset_map'], $plan->applyAssets());
                $this->syncPageSlots($payload, $allPageMap, $sharedSlotState['handle_map']);
                $this->syncPageAssets($archive, $payload, $allPageMap, $plan->applyAssets(), $inspection->includesAssets, $copiedFiles);
                $this->syncBlocks($payload, $allPageMap, $localeMap, $assetMaps['asset_map'], $plan->applyAssets());
                $this->rebuildSharedSlotAssignments($sharedSlotState['shared_slots']);
                $this->syncNavigation($targetSite, $payload, $pageState['source_page_map'], $plan->isMirror());
                $this->archiveMirrorPages($targetSite, array_keys($pageState['source_page_map']), $plan->isMirror());
            });
        } catch (Throwable $throwable) {
            foreach ($copiedFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $throwable;
        } finally {
            $archive->close();
        }

        $search = $this->searchIndexer->rebuild($targetSite);

        return new SitePromotionResult(
            plan: $plan,
            targetSite: $targetSite->fresh(['locales']),
            safetyBackup: $backup,
            searchIndexed: $search->indexed,
            searchSkipped: $search->skipped,
            warnings: $plan->warnings,
        );
    }

    private function ensureLocales(array $payload): array
    {
        $map = [];

        foreach ($payload['locales'] ?? [] as $localeData) {
            $code = Locale::normalizeCode((string) ($localeData['code'] ?? ''));

            if (! $code) {
                throw new RuntimeException('Promotion package contains an invalid locale code.');
            }

            $locale = Locale::query()->firstOrCreate(
                ['code' => $code],
                ['name' => (string) ($localeData['name'] ?? strtoupper($code)), 'is_default' => false, 'is_enabled' => true],
            );

            if (! $locale->is_enabled) {
                $locale->forceFill(['is_enabled' => true])->save();
            }

            $map[(int) ($localeData['id'] ?? 0)] = $locale->id;
        }

        return $map;
    }

    private function syncSiteMetadata(Site $targetSite, array $payload, array $assetMap, bool $applyAssets): void
    {
        $siteData = (array) ($payload['site'] ?? []);

        $targetSite->update([
            'name' => $siteData['name'] ?? $targetSite->name,
            'display_name' => $siteData['display_name'] ?? $targetSite->display_name,
            'tagline' => $siteData['tagline'] ?? $targetSite->tagline,
            'seo_title' => $siteData['seo_title'] ?? $targetSite->seo_title,
            'seo_description' => $siteData['seo_description'] ?? $targetSite->seo_description,
            'seo_keywords' => $siteData['seo_keywords'] ?? $targetSite->seo_keywords,
            'favicon_media_id' => $applyAssets ? ($assetMap[(int) ($siteData['favicon_media_id'] ?? 0)] ?? $targetSite->favicon_media_id) : $targetSite->favicon_media_id,
            'social_image_media_id' => $applyAssets ? ($assetMap[(int) ($siteData['social_image_media_id'] ?? 0)] ?? $targetSite->social_image_media_id) : $targetSite->social_image_media_id,
        ]);
    }

    private function syncSiteLocales(Site $targetSite, array $payload, array $localeMap, bool $mirror): void
    {
        $sync = [];

        foreach ($payload['site_locales'] ?? [] as $siteLocale) {
            $mappedLocaleId = $localeMap[(int) ($siteLocale['locale_id'] ?? 0)] ?? null;

            if (! $mappedLocaleId) {
                continue;
            }

            $sync[$mappedLocaleId] = ['is_enabled' => (bool) ($siteLocale['is_enabled'] ?? true)];
        }

        if ($mirror) {
            $targetSite->locales()->sync($sync);

            return;
        }

        $targetSite->locales()->syncWithoutDetaching($sync);
    }

    private function syncSiteVariables(Site $targetSite, array $payload, bool $mirror): void
    {
        $source = collect($payload['site_variables'] ?? [])->keyBy('key');
        $existing = $targetSite->siteVariables()->get()->keyBy('key');

        foreach ($source as $key => $row) {
            $targetSite->siteVariables()->updateOrCreate(
                ['key' => $key],
                [
                    'label' => $row['label'] ?? null,
                    'value' => $row['value'] ?? null,
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                    'is_enabled' => (bool) ($row['is_enabled'] ?? true),
                ],
            );
        }

        if ($mirror) {
            $removeKeys = array_diff($existing->keys()->all(), $source->keys()->all());
            if ($removeKeys !== []) {
                $targetSite->siteVariables()->whereIn('key', $removeKeys)->delete();
            }
        }
    }

    private function syncSharedSlots(Site $targetSite, array $payload, bool $mirror): array
    {
        $sourceRows = collect($payload['shared_slots'] ?? [])->keyBy('handle');
        $existing = SharedSlot::query()->where('site_id', $targetSite->id)->get()->keyBy('handle');
        $handleMap = [];
        $sourcePageMap = [];
        $sharedSlots = [];

        foreach ($sourceRows as $handle => $row) {
            $sharedSlot = SharedSlot::query()->updateOrCreate(
                ['site_id' => $targetSite->id, 'handle' => $handle],
                [
                    'name' => $row['name'] ?? $handle,
                    'slot_name' => $row['slot_name'] ?? null,
                    'public_shell' => $row['public_shell'] ?? null,
                    'is_active' => (bool) ($row['is_active'] ?? true),
                    'created_by_user_id' => null,
                    'updated_by_user_id' => null,
                ],
            );

            $targetSourcePage = $this->sharedSlotSourcePageManager->ensureFor($sharedSlot);
            $sourcePageMap[(int) ($row['source_page_id'] ?? 0)] = $targetSourcePage->id;
            $handleMap[$handle] = $sharedSlot->id;
            $sharedSlots[] = $sharedSlot;
        }

        if ($mirror) {
            $deactivateHandles = array_diff($existing->keys()->all(), $sourceRows->keys()->all());
            if ($deactivateHandles !== []) {
                SharedSlot::query()->where('site_id', $targetSite->id)->whereIn('handle', $deactivateHandles)->update(['is_active' => false]);
            }
        }

        return [
            'shared_slots' => $sharedSlots,
            'handle_map' => $handleMap,
            'source_page_map' => $sourcePageMap,
        ];
    }

    private function syncPages(Site $targetSite, array $payload): array
    {
        $sourcePages = collect($payload['pages'] ?? []);
        $translations = collect($payload['page_translations'] ?? [])->groupBy('page_id');
        $locales = collect($payload['locales'] ?? [])->keyBy('id');
        $defaultLocaleId = $locales->firstWhere('is_default', true)['id'] ?? null;

        $existing = Page::query()
            ->where('site_id', $targetSite->id)
            ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
            ->with('translations')
            ->get();

        $existingByKey = [];

        foreach ($existing as $page) {
            $defaultTranslation = $page->defaultTranslation() ?? $page->translations->first();
            $key = trim((string) ($defaultTranslation?->path ?? ''));
            if ($key === '') {
                $key = 'slug:'.trim((string) ($defaultTranslation?->slug ?? $page->slug ?? $page->id));
            }
            $existingByKey[$key] = $page;
        }

        $sourcePageMap = [];

        foreach ($sourcePages as $row) {
            $group = collect($translations[(int) ($row['id'] ?? 0)] ?? []);
            $defaultTranslation = $group->firstWhere('locale_id', $defaultLocaleId) ?? $group->first();
            $key = trim((string) ($defaultTranslation['path'] ?? ''));
            if ($key === '') {
                $key = 'slug:'.trim((string) ($defaultTranslation['slug'] ?? $row['slug'] ?? $row['id']));
            }

            $page = $existingByKey[$key] ?? new Page(['site_id' => $targetSite->id]);
            $page->fill([
                'site_id' => $targetSite->id,
                'title' => $row['title'] ?? 'Promoted Page',
                'slug' => $row['slug'] ?? 'promoted-page',
                'page_type' => $row['page_type'] ?? Page::TYPE_DEFAULT,
                'page_type_id' => ! empty($row['page_type_slug']) ? PageType::query()->where('slug', $row['page_type_slug'])->value('id') : null,
                'layout_id' => ! empty($row['layout_slug']) ? Layout::query()->where('slug', $row['layout_slug'])->value('id') : null,
                'status' => $row['status'] ?? Page::STATUS_DRAFT,
                'settings' => Page::sanitizeSettings($row['settings'] ?? null, $row['public_shell'] ?? null),
                'created_by_user_id' => null,
                'updated_by_user_id' => null,
                'published_by_user_id' => null,
                'archived_by_user_id' => null,
                'review_requested_by_user_id' => null,
            ]);
            $page->save();

            $sourcePageMap[(int) ($row['id'] ?? 0)] = $page->id;
        }

        return ['source_page_map' => $sourcePageMap];
    }

    private function syncPageTranslations(Site $targetSite, array $payload, array $pageMap, array $localeMap, array $assetMap, bool $applyAssets): void
    {
        $grouped = collect($payload['page_translations'] ?? [])->groupBy('page_id');

        foreach ($pageMap as $sourcePageId => $targetPageId) {
            $page = Page::query()->findOrFail($targetPageId);

            if ($page->isSharedSlotSourcePage()) {
                continue;
            }

            $page->translations()->delete();

            foreach ($grouped[(int) $sourcePageId] ?? [] as $translation) {
                $mappedLocaleId = $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null;

                if (! $mappedLocaleId) {
                    continue;
                }

                PageTranslation::query()->create([
                    'page_id' => $page->id,
                    'site_id' => $targetSite->id,
                    'locale_id' => $mappedLocaleId,
                    'name' => $translation['name'] ?? null,
                    'slug' => $translation['slug'] ?? null,
                    'path' => $translation['path'] ?? null,
                    'seo_title' => $translation['seo_title'] ?? null,
                    'seo_description' => $translation['seo_description'] ?? null,
                    'seo_keywords' => $translation['seo_keywords'] ?? null,
                    'og_title' => $translation['og_title'] ?? null,
                    'og_description' => $translation['og_description'] ?? null,
                    'og_image_media_id' => $applyAssets ? ($assetMap[(int) ($translation['og_image_media_id'] ?? 0)] ?? null) : null,
                ]);
            }
        }
    }

    private function syncPageSlots(array $payload, array $pageMap, array $sharedSlotHandleMap): void
    {
        $grouped = collect($payload['page_slots'] ?? [])->groupBy('page_id');

        foreach ($pageMap as $sourcePageId => $targetPageId) {
            $page = Page::query()->findOrFail($targetPageId);
            $page->slots()->delete();

            foreach ($grouped[(int) $sourcePageId] ?? [] as $slot) {
                $slotTypeId = ! empty($slot['slot_type_slug']) ? SlotType::query()->where('slug', $slot['slot_type_slug'])->value('id') : null;

                if (! $slotTypeId) {
                    continue;
                }

                $sourceType = PageSlot::normalizeRuntimeSourceType($slot['source_type'] ?? PageSlot::SOURCE_TYPE_PAGE);
                $sharedSlotId = $sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT
                    ? ($sharedSlotHandleMap[(string) ($slot['shared_slot_handle'] ?? '')] ?? null)
                    : null;

                if ($sourceType === PageSlot::SOURCE_TYPE_SHARED_SLOT && ! $sharedSlotId) {
                    throw new RuntimeException('Shared Slot compatibility could not be resolved safely during promotion.');
                }

                PageSlot::query()->create([
                    'page_id' => $page->id,
                    'slot_type_id' => $slotTypeId,
                    'source_type' => $sourceType,
                    'shared_slot_id' => $sharedSlotId,
                    'sort_order' => (int) ($slot['sort_order'] ?? 0),
                    'settings' => PageSlot::sanitizeSettings($slot['settings'] ?? null),
                ]);
            }
        }
    }

    private function syncPageAssets(ZipArchive $archive, array $payload, array $pageMap, bool $applyAssets, bool $packageIncludesAssets, array &$copiedFiles): void
    {
        $grouped = collect($payload['page_assets'] ?? [])->groupBy('page_id');

        foreach ($pageMap as $sourcePageId => $targetPageId) {
            $page = Page::query()->findOrFail($targetPageId);

            if ($page->isSharedSlotSourcePage()) {
                continue;
            }

            $page->pageAssets()->delete();

            foreach ($grouped[(int) $sourcePageId] ?? [] as $pageAsset) {
                $path = $this->pageAssetPathValidator->normalizeForStorage((string) ($pageAsset['type'] ?? ''), (string) ($pageAsset['path'] ?? ''));

                PageAsset::query()->create([
                    'page_id' => $page->id,
                    'type' => $pageAsset['type'] ?? PageAsset::TYPE_CSS,
                    'path' => $path,
                    'load_position' => $pageAsset['load_position'] ?? PageAsset::LOAD_POSITION_HEAD,
                    'is_defer' => (bool) ($pageAsset['is_defer'] ?? true),
                    'is_async' => (bool) ($pageAsset['is_async'] ?? false),
                    'is_module' => (bool) ($pageAsset['is_module'] ?? false),
                    'is_enabled' => (bool) ($pageAsset['is_enabled'] ?? true),
                    'sort_order' => (int) ($pageAsset['sort_order'] ?? 0),
                ]);

                if (! $applyAssets || ! $packageIncludesAssets) {
                    continue;
                }

                $relativePath = $this->pageAssetPathValidator->relativePublicPath($path);
                $archiveEntry = 'files/public/'.$relativePath;

                if ($archive->locateName($archiveEntry) === false) {
                    continue;
                }

                $stream = $archive->getStream($archiveEntry);
                if (! is_resource($stream)) {
                    throw new RuntimeException('Could not read promoted public asset file '.$archiveEntry.'.');
                }

                Storage::disk('public')->writeStream($relativePath, $stream);
                fclose($stream);
                $copiedFiles[] = ['public', $relativePath];
            }
        }
    }

    private function syncBlocks(array $payload, array $pageMap, array $localeMap, array $assetMap, bool $applyAssets): void
    {
        $blocks = collect($payload['blocks'] ?? [])->groupBy('page_id');
        $textTranslations = collect($payload['block_text_translations'] ?? [])->groupBy('block_id');
        $buttonTranslations = collect($payload['block_button_translations'] ?? [])->groupBy('block_id');
        $imageTranslations = collect($payload['block_image_translations'] ?? [])->groupBy('block_id');
        $contactTranslations = collect($payload['block_contact_form_translations'] ?? [])->groupBy('block_id');
        $blockAssets = collect($payload['block_media'] ?? [])->groupBy('block_id');
        $galleryItemTranslations = collect($payload['block_gallery_item_translations'] ?? [])->groupBy('block_media_id');

        foreach ($pageMap as $sourcePageId => $targetPageId) {
            Block::query()->where('page_id', $targetPageId)->delete();
            $map = [];

            foreach ($blocks[(int) $sourcePageId] ?? [] as $row) {
                $blockTypeId = ! empty($row['block_type_slug']) ? BlockType::query()->where('slug', $row['block_type_slug'])->value('id') : null;
                $slotTypeId = ! empty($row['slot_type_slug']) ? SlotType::query()->where('slug', $row['slot_type_slug'])->value('id') : null;

                if (! $blockTypeId || ! $slotTypeId) {
                    continue;
                }

                $block = Block::query()->create([
                    'page_id' => $targetPageId,
                    'parent_id' => null,
                    'type' => $row['type'] ?? null,
                    'block_type_id' => $blockTypeId,
                    'source_type' => $row['source_type'] ?? 'static',
                    'slot' => $row['slot'] ?? null,
                    'slot_type_id' => $slotTypeId,
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                    'title' => $row['title'] ?? null,
                    'subtitle' => $row['subtitle'] ?? null,
                    'content' => $row['content'] ?? null,
                    'url' => $row['url'] ?? null,
                    'media_id' => $applyAssets ? ($assetMap[(int) ($row['media_id'] ?? 0)] ?? null) : null,
                    'variant' => $row['variant'] ?? null,
                    'meta' => $row['meta'] ?? null,
                    'settings' => $row['settings'] ?? null,
                    'status' => $row['status'] ?? 'draft',
                    'is_system' => (bool) ($row['is_system'] ?? false),
                ]);

                $map[(int) ($row['id'] ?? 0)] = $block;
            }

            foreach ($blocks[(int) $sourcePageId] ?? [] as $row) {
                $block = $map[(int) ($row['id'] ?? 0)] ?? null;
                if (! $block instanceof Block) {
                    continue;
                }

                $parentId = (int) ($row['parent_id'] ?? 0);
                if ($parentId > 0 && isset($map[$parentId])) {
                    $block->forceFill(['parent_id' => $map[$parentId]->id])->save();
                }

                foreach ($textTranslations[(int) ($row['id'] ?? 0)] ?? [] as $translation) {
                    BlockTextTranslation::query()->create([
                        'block_id' => $block->id,
                        'locale_id' => $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null,
                        'title' => $translation['title'] ?? null,
                        'eyebrow' => $translation['eyebrow'] ?? null,
                        'subtitle' => $translation['subtitle'] ?? null,
                        'content' => $translation['content'] ?? null,
                        'meta' => $translation['meta'] ?? null,
                    ]);
                }

                foreach ($buttonTranslations[(int) ($row['id'] ?? 0)] ?? [] as $translation) {
                    $localeId = $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null;
                    if (! $localeId) {
                        continue;
                    }

                    BlockButtonTranslation::query()->create([
                        'block_id' => $block->id,
                        'locale_id' => $localeId,
                        'title' => $translation['title'] ?? null,
                    ]);
                }

                foreach ($imageTranslations[(int) ($row['id'] ?? 0)] ?? [] as $translation) {
                    $localeId = $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null;
                    if (! $localeId) {
                        continue;
                    }

                    BlockImageTranslation::query()->create([
                        'block_id' => $block->id,
                        'locale_id' => $localeId,
                        'caption' => $translation['caption'] ?? null,
                        'alt_text' => $translation['alt_text'] ?? null,
                    ]);
                }

                foreach ($contactTranslations[(int) ($row['id'] ?? 0)] ?? [] as $translation) {
                    $localeId = $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null;
                    if (! $localeId) {
                        continue;
                    }

                    BlockContactFormTranslation::query()->create([
                        'block_id' => $block->id,
                        'locale_id' => $localeId,
                        'title' => $translation['title'] ?? null,
                        'content' => $translation['content'] ?? null,
                        'submit_label' => $translation['submit_label'] ?? null,
                        'success_message' => $translation['success_message'] ?? null,
                    ]);
                }

                foreach ($blockAssets[(int) ($row['id'] ?? 0)] ?? [] as $blockAsset) {
                    if (! $applyAssets) {
                        continue;
                    }

                    $assetId = $assetMap[(int) ($blockAsset['media_id'] ?? 0)] ?? null;
                    if (! $assetId) {
                        continue;
                    }

                    $createdBlockAsset = BlockAsset::query()->create([
                        'block_id' => $block->id,
                        'media_id' => $assetId,
                        'role' => $blockAsset['role'] ?? null,
                        'position' => $blockAsset['position'] ?? 0,
                    ]);

                    foreach ($galleryItemTranslations[(int) ($blockAsset['id'] ?? 0)] ?? [] as $translation) {
                        $localeId = $localeMap[(int) ($translation['locale_id'] ?? 0)] ?? null;

                        if (! $localeId) {
                            continue;
                        }

                        BlockGalleryItemTranslation::query()->create([
                            'block_media_id' => $createdBlockAsset->id,
                            'locale_id' => $localeId,
                            'alt_text' => $translation['alt_text'] ?? null,
                            'caption' => $translation['caption'] ?? null,
                            'overlay_title' => $translation['overlay_title'] ?? null,
                            'overlay_text' => $translation['overlay_text'] ?? null,
                        ]);
                    }
                }

                $translatedBlock = $block->fresh(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations']);

                if (
                    $translatedBlock->textTranslations->isNotEmpty()
                    || $translatedBlock->buttonTranslations->isNotEmpty()
                    || $translatedBlock->imageTranslations->isNotEmpty()
                    || $translatedBlock->contactFormTranslations->isNotEmpty()
                ) {
                    $this->blockTranslationWriter->normalizeCanonicalStorage($translatedBlock);
                }
            }
        }
    }

    private function rebuildSharedSlotAssignments(array $sharedSlots): void
    {
        foreach ($sharedSlots as $sharedSlot) {
            $this->sharedSlotSourcePageManager->rebuildAssignments($sharedSlot);
        }
    }

    private function syncNavigation(Site $targetSite, array $payload, array $pageMap, bool $mirror): void
    {
        $items = collect($payload['navigation_items'] ?? [])->sortBy('position')->values();
        $existing = NavigationItem::query()->where('site_id', $targetSite->id)->get()->keyBy(function (NavigationItem $item): string {
            return $item->menu_key.'|'.$item->link_type.'|'.($item->page_id ?: $item->url ?: $item->title).'|'.($item->parent_id ?: 'root');
        });
        $keptIds = [];
        $newMap = [];

        foreach ($items as $item) {
            $parentTargetId = null;
            $parentSourceId = (int) ($item['parent_id'] ?? 0);
            if ($parentSourceId > 0) {
                $parentTargetId = $newMap[$parentSourceId] ?? null;
            }

            $pageId = (int) ($item['page_id'] ?? 0) > 0 ? ($pageMap[(int) $item['page_id']] ?? null) : null;
            $signature = (string) ($item['menu_key'] ?? '').'|'.(string) ($item['link_type'] ?? '').'|'.($pageId ?: ($item['url'] ?? $item['title'] ?? '')).'|'.($parentTargetId ?: 'root');

            $navigationItem = $existing[$signature] ?? new NavigationItem(['site_id' => $targetSite->id]);
            $navigationItem->fill([
                'site_id' => $targetSite->id,
                'menu_key' => $item['menu_key'] ?? NavigationItem::MENU_PRIMARY,
                'parent_id' => $parentTargetId,
                'page_id' => $pageId,
                'title' => $item['title'] ?? null,
                'link_type' => $item['link_type'] ?? NavigationItem::LINK_CUSTOM_URL,
                'url' => $item['url'] ?? null,
                'target' => $item['target'] ?? null,
                'icon' => $item['icon'] ?? null,
                'position' => (int) ($item['position'] ?? 0),
                'visibility' => $item['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE,
                'is_system' => (bool) ($item['is_system'] ?? false),
            ]);
            $navigationItem->save();

            $keptIds[] = $navigationItem->id;
            $newMap[(int) ($item['id'] ?? 0)] = $navigationItem->id;
        }

        if ($mirror) {
            NavigationItem::query()->where('site_id', $targetSite->id)->whereNotIn('id', $keptIds)->delete();
        }
    }

    private function archiveMirrorPages(Site $targetSite, array $keptPageIds, bool $mirror): void
    {
        if (! $mirror) {
            return;
        }

        Page::query()
            ->where('site_id', $targetSite->id)
            ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
            ->whereNotIn('id', $keptPageIds)
            ->update(['status' => Page::STATUS_ARCHIVED]);
    }

    private function syncAssets(ZipArchive $archive, array $payload, array &$copiedFiles): array
    {
        $folderRows = collect($payload['media_folders'] ?? [])->keyBy('id');
        $folderKeys = [];
        $folderMap = [];

        $resolveFolderPath = function (int $folderId) use (&$resolveFolderPath, $folderRows, &$folderKeys): string {
            if (isset($folderKeys[$folderId])) {
                return $folderKeys[$folderId];
            }

            $row = $folderRows[$folderId] ?? null;
            if (! is_array($row)) {
                return '';
            }

            $parentId = (int) ($row['parent_id'] ?? 0);
            $prefix = $parentId > 0 ? $resolveFolderPath($parentId).'/' : '';

            return $folderKeys[$folderId] = trim($prefix.($row['slug'] ?? $row['name'] ?? $folderId), '/');
        };

        foreach ($folderRows as $row) {
            $key = $resolveFolderPath((int) $row['id']);
            $segments = array_values(array_filter(explode('/', $key)));
            $parentId = null;

            foreach ($segments as $segment) {
                $folder = MediaFolder::query()->firstOrCreate([
                    'parent_id' => $parentId,
                    'slug' => $segment,
                ], [
                    'name' => str($segment)->replace('-', ' ')->title()->toString(),
                ]);
                $parentId = $folder->id;
            }

            $folderMap[(int) $row['id']] = $parentId;
        }

        $assetMap = [];

        foreach ($payload['media'] ?? [] as $assetData) {
            $diskName = (string) ($assetData['disk'] ?? 'public');
            $path = (string) ($assetData['path'] ?? '');
            $archiveEntry = 'files/'.$diskName.'/'.$path;

            if ($archive->locateName($archiveEntry) === false) {
                throw new RuntimeException('Promotion package is missing media file '.$archiveEntry.'.');
            }

            $stream = $archive->getStream($archiveEntry);
            if (! is_resource($stream)) {
                throw new RuntimeException('Could not read promoted media file '.$archiveEntry.'.');
            }

            Storage::disk($diskName)->writeStream($path, $stream);
            fclose($stream);
            $copiedFiles[] = [$diskName, $path];

            $asset = Media::query()->updateOrCreate(
                ['disk' => $diskName, 'path' => $path],
                [
                    'folder_id' => $folderMap[(int) ($assetData['folder_id'] ?? 0)] ?? null,
                    'filename' => $assetData['filename'] ?? basename($path),
                    'original_name' => $assetData['original_name'] ?? basename($path),
                    'extension' => $assetData['extension'] ?? pathinfo($path, PATHINFO_EXTENSION),
                    'mime_type' => $assetData['mime_type'] ?? null,
                    'size' => $assetData['size'] ?? null,
                    'kind' => $assetData['kind'] ?? Media::KIND_OTHER,
                    'visibility' => $assetData['visibility'] ?? 'public',
                    'title' => $assetData['title'] ?? null,
                    'alt_text' => $assetData['alt_text'] ?? null,
                    'caption' => $assetData['caption'] ?? null,
                    'description' => $assetData['description'] ?? null,
                    'width' => $assetData['width'] ?? null,
                    'height' => $assetData['height'] ?? null,
                    'duration' => $assetData['duration'] ?? null,
                    'uploaded_by' => null,
                ],
            );

            $assetMap[(int) ($assetData['id'] ?? 0)] = $asset->id;
        }

        return ['folder_map' => $folderMap, 'asset_map' => $assetMap];
    }
}
