<?php

namespace App\Support\Sites\ExportImport;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\Layout;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\PageType;
use App\Models\Site;
use App\Models\SiteImport;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use App\Support\Sites\SiteDomainNormalizer;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class ImportDataMapper
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SiteDomainNormalizer $domainNormalizer,
        private readonly SiteTransferPathGuard $pathGuard,
        private readonly BlockTranslationWriter $blockTranslationWriter,
    ) {}

    public function import(SiteImport $siteImport, SiteImportOptions $options, ZipArchive $archive, array $payload, array &$output = []): Site
    {
        $copiedFiles = [];

        try {
            $site = $this->db->transaction(function () use ($siteImport, $options, $archive, $payload, &$output, &$copiedFiles): Site {
                $localeMap = $this->importLocales($payload, $output);
                $site = $this->createSite($payload['site'], $options, $output);
                $this->syncSiteLocales($site, $payload, $localeMap, $output);

                $folderMap = $this->importAssetFolders($payload, $output);
                $assetMap = $this->importAssets($archive, $payload, $folderMap, $copiedFiles, $output);
                $pageMap = $this->importPages($site, $payload, $localeMap, $output);
                $this->importPageSlots($payload, $pageMap, $output);
                $blockMap = $this->importBlocks($payload, $pageMap, $assetMap, $output);
                $this->importBlockTranslations($payload, $blockMap, $localeMap, $output);
                $this->importBlockAssets($payload, $blockMap, $assetMap, $output);
                $this->importNavigation($site, $payload, $pageMap, $output);

                $siteImport->forceFill([
                    'status' => SiteImport::STATUS_COMPLETED,
                    'target_site_id' => $site->id,
                    'imported_site_handle' => $site->handle,
                    'imported_site_domain' => $site->domain,
                ])->save();

                return $site;
            });

            return $site;
        } catch (Throwable $throwable) {
            foreach ($copiedFiles as [$disk, $path]) {
                Storage::disk($disk)->delete($path);
            }

            throw $throwable;
        }
    }

    private function importLocales(array $payload, array &$output): array
    {
        $map = [];

        foreach ($payload['locales'] as $localeData) {
            $code = Locale::normalizeCode((string) ($localeData['code'] ?? ''));

            if (! $code) {
                throw new RuntimeException('Import package contains a locale without a valid code.');
            }

            $locale = Locale::query()->where('code', $code)->first();

            if (! $locale) {
                $locale = Locale::query()->create([
                    'code' => $code,
                    'name' => (string) ($localeData['name'] ?? Str::upper($code)),
                    'is_default' => false,
                    'is_enabled' => true,
                ]);

                $output[] = 'Created missing locale ['.$code.'].';
            }

            $map[(int) $localeData['id']] = $locale->id;
        }

        return $map;
    }

    private function createSite(array $siteData, SiteImportOptions $options, array &$output): Site
    {
        $requestedHandle = str($options->siteHandle ?: (string) ($siteData['handle'] ?? 'imported-site'))->slug()->toString();
        $handle = $this->availableHandle($requestedHandle !== '' ? $requestedHandle : 'imported-site');

        if ($handle !== $requestedHandle) {
            $output[] = 'Adjusted imported site handle to ['.$handle.'] to avoid collisions.';
        }

        $domain = $options->siteDomain !== null
            ? $this->domainNormalizer->normalize($options->siteDomain)
            : null;

        if ($domain !== null && Site::query()->where('domain', $domain)->exists()) {
            throw new RuntimeException('Selected site domain already exists locally. Choose a different domain or leave it blank.');
        }

        return Site::query()->create([
            'name' => $options->siteName,
            'handle' => $handle,
            'domain' => $domain,
            'is_primary' => false,
        ]);
    }

    private function syncSiteLocales(Site $site, array $payload, array $localeMap, array &$output): void
    {
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $sync = [];

        foreach ($payload['site_locales'] as $siteLocale) {
            $mappedLocaleId = $localeMap[(int) ($siteLocale['locale_id'] ?? 0)] ?? null;

            if (! $mappedLocaleId) {
                continue;
            }

            $sync[$mappedLocaleId] = ['is_enabled' => (bool) ($siteLocale['is_enabled'] ?? true)];
        }

        if ($defaultLocaleId) {
            $sync[$defaultLocaleId] = ['is_enabled' => true];
        }

        if ($sync === []) {
            throw new RuntimeException('Import package does not provide a valid site locale mapping.');
        }

        $site->locales()->sync($sync);
        $output[] = 'Imported '.count($sync).' site locale assignment(s).';
    }

    private function importAssetFolders(array $payload, array &$output): array
    {
        $folders = $payload['asset_folders'] ?? [];
        $map = [];

        foreach ($folders as $folderData) {
            $folder = AssetFolder::query()->create([
                'parent_id' => null,
                'name' => $folderData['name'] ?? 'Imported Folder',
                'slug' => $folderData['slug'] ?? Str::slug((string) ($folderData['name'] ?? 'imported-folder')),
            ]);

            $map[(int) $folderData['id']] = $folder->id;
        }

        foreach ($folders as $folderData) {
            $newFolderId = $map[(int) $folderData['id']] ?? null;
            $newParentId = $map[(int) ($folderData['parent_id'] ?? 0)] ?? null;

            if ($newFolderId) {
                AssetFolder::query()->whereKey($newFolderId)->update(['parent_id' => $newParentId]);
            }
        }

        if ($map !== []) {
            $output[] = 'Imported '.count($map).' asset folder(s).';
        }

        return $map;
    }

    private function importAssets(ZipArchive $archive, array $payload, array $folderMap, array &$copiedFiles, array &$output): array
    {
        $map = [];

        foreach (($payload['assets'] ?? []) as $assetData) {
            $diskName = (string) ($assetData['disk'] ?? 'public');
            $sourcePath = (string) ($assetData['path'] ?? '');
            $archiveEntry = 'files/'.$diskName.'/'.$sourcePath;

            $this->pathGuard->assertSafeRelativePath($sourcePath, 'Asset path');
            $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive media path');

            if ($archive->locateName($archiveEntry) === false) {
                throw new RuntimeException('Import package is missing asset file '.$archiveEntry.'.');
            }

            $targetPath = $this->availableAssetPath($diskName, $sourcePath);
            $stream = $archive->getStream($archiveEntry);

            if (! is_resource($stream)) {
                throw new RuntimeException('Could not read asset file '.$archiveEntry.' from import package.');
            }

            Storage::disk($diskName)->writeStream($targetPath, $stream);
            fclose($stream);
            $copiedFiles[] = [$diskName, $targetPath];

            $asset = Asset::query()->create([
                'folder_id' => $folderMap[(int) ($assetData['folder_id'] ?? 0)] ?? null,
                'disk' => $diskName,
                'path' => $targetPath,
                'filename' => basename($targetPath),
                'original_name' => $assetData['original_name'] ?? basename($sourcePath),
                'extension' => $assetData['extension'] ?? pathinfo($targetPath, PATHINFO_EXTENSION),
                'mime_type' => $assetData['mime_type'] ?? null,
                'size' => $assetData['size'] ?? null,
                'kind' => $assetData['kind'] ?? Asset::KIND_OTHER,
                'visibility' => $assetData['visibility'] ?? 'public',
                'title' => $assetData['title'] ?? null,
                'alt_text' => $assetData['alt_text'] ?? null,
                'caption' => $assetData['caption'] ?? null,
                'description' => $assetData['description'] ?? null,
                'width' => $assetData['width'] ?? null,
                'height' => $assetData['height'] ?? null,
                'duration' => $assetData['duration'] ?? null,
                'uploaded_by' => null,
                'created_at' => $assetData['created_at'] ?? null,
                'updated_at' => $assetData['updated_at'] ?? null,
            ]);

            $map[(int) $assetData['id']] = $asset->id;
        }

        if ($map !== []) {
            $output[] = 'Imported '.count($map).' asset record(s) and media file(s).';
        }

        return $map;
    }

    private function importPages(Site $site, array $payload, array $localeMap, array &$output): array
    {
        $map = [];

        foreach ($payload['pages'] as $pageData) {
            $pageTypeSlug = $pageData['page_type_slug'] ?? null;
            $layoutSlug = $pageData['layout_slug'] ?? null;

            $page = Page::query()->create([
                'site_id' => $site->id,
                'title' => $pageData['title'] ?? 'Imported Page',
                'slug' => $pageData['slug'] ?? Str::slug((string) ($pageData['title'] ?? 'imported-page')),
                'page_type' => $pageData['page_type'] ?? 'default',
                'page_type_id' => $pageTypeSlug ? PageType::query()->where('slug', $pageTypeSlug)->value('id') : null,
                'layout_id' => $layoutSlug ? Layout::query()->where('slug', $layoutSlug)->value('id') : null,
                'status' => $pageData['status'] ?? 'draft',
                'settings' => $pageData['settings'] ?? null,
                'created_at' => $pageData['created_at'] ?? null,
                'updated_at' => $pageData['updated_at'] ?? null,
            ]);

            $page->translations()->delete();
            $map[(int) $pageData['id']] = $page->id;
        }

        foreach ($payload['page_translations'] as $translationData) {
            $pageId = $map[(int) ($translationData['page_id'] ?? 0)] ?? null;
            $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

            if (! $pageId || ! $localeId) {
                continue;
            }

            PageTranslation::query()->create([
                'page_id' => $pageId,
                'site_id' => $site->id,
                'locale_id' => $localeId,
                'name' => $translationData['name'] ?? null,
                'slug' => $translationData['slug'] ?? null,
                'path' => $translationData['path'] ?? null,
                'created_at' => $translationData['created_at'] ?? null,
                'updated_at' => $translationData['updated_at'] ?? null,
            ]);
        }

        $output[] = 'Imported '.count($map).' page(s).';

        return $map;
    }

    private function importPageSlots(array $payload, array $pageMap, array &$output): void
    {
        $count = 0;

        foreach ($payload['page_slots'] as $slotData) {
            $pageId = $pageMap[(int) ($slotData['page_id'] ?? 0)] ?? null;
            $slotTypeSlug = $slotData['slot_type_slug'] ?? null;
            $slotTypeId = $slotTypeSlug
                ? SlotType::query()->where('slug', $slotTypeSlug)->value('id')
                : null;

            if (! $pageId || ! $slotTypeId) {
                throw new RuntimeException('Import package references a missing slot type for page slots.');
            }

            PageSlot::query()->create([
                'page_id' => $pageId,
                'slot_type_id' => $slotTypeId,
                'sort_order' => $slotData['sort_order'] ?? 0,
                'settings' => $slotData['settings'] ?? null,
                'created_at' => $slotData['created_at'] ?? null,
                'updated_at' => $slotData['updated_at'] ?? null,
            ]);

            $count++;
        }

        $output[] = 'Imported '.$count.' page slot assignment(s).';
    }

    private function importBlocks(array $payload, array $pageMap, array $assetMap, array &$output): array
    {
        $map = [];

        foreach ($payload['blocks'] as $blockData) {
            $pageId = $pageMap[(int) ($blockData['page_id'] ?? 0)] ?? null;
            $blockTypeSlug = $blockData['block_type_slug'] ?? $blockData['type'] ?? null;
            $slotTypeSlug = $blockData['slot_type_slug'] ?? $blockData['slot'] ?? null;
            $blockTypeId = $blockTypeSlug ? BlockType::query()->where('slug', $blockTypeSlug)->value('id') : null;
            $slotTypeId = $slotTypeSlug ? SlotType::query()->where('slug', $slotTypeSlug)->value('id') : null;

            if (! $pageId || ! $blockTypeId || ! $slotTypeId) {
                throw new RuntimeException('Import package references a missing block type or slot type.');
            }

            $block = Block::query()->create([
                'page_id' => $pageId,
                'parent_id' => null,
                'type' => $blockData['type'] ?? $blockTypeSlug,
                'block_type_id' => $blockTypeId,
                'source_type' => $blockData['source_type'] ?? 'static',
                'slot' => $blockData['slot'] ?? $slotTypeSlug,
                'slot_type_id' => $slotTypeId,
                'sort_order' => $blockData['sort_order'] ?? 0,
                'title' => $blockData['title'] ?? null,
                'subtitle' => $blockData['subtitle'] ?? null,
                'content' => $blockData['content'] ?? null,
                'url' => $blockData['url'] ?? null,
                'asset_id' => $assetMap[(int) ($blockData['asset_id'] ?? 0)] ?? null,
                'variant' => $blockData['variant'] ?? null,
                'meta' => $blockData['meta'] ?? null,
                'settings' => $blockData['settings'] ?? null,
                'status' => $blockData['status'] ?? 'draft',
                'is_system' => (bool) ($blockData['is_system'] ?? false),
                'created_at' => $blockData['created_at'] ?? null,
                'updated_at' => $blockData['updated_at'] ?? null,
            ]);

            $map[(int) $blockData['id']] = $block->id;
        }

        foreach ($payload['blocks'] as $blockData) {
            $newBlockId = $map[(int) ($blockData['id'] ?? 0)] ?? null;
            $newParentId = $map[(int) ($blockData['parent_id'] ?? 0)] ?? null;

            if ($newBlockId && $newParentId) {
                Block::query()->whereKey($newBlockId)->update(['parent_id' => $newParentId]);
            }
        }

        $output[] = 'Imported '.count($map).' block(s).';

        return $map;
    }

    private function importBlockTranslations(array $payload, array $blockMap, array $localeMap, array &$output): void
    {
        $count = 0;

        foreach (($payload['block_text_translations'] ?? []) as $translationData) {
            $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
            $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

            if ($blockId && $localeId) {
                
                \App\Models\BlockTextTranslation::query()->create([
                    'block_id' => $blockId,
                    'locale_id' => $localeId,
                    'title' => $translationData['title'] ?? null,
                    'eyebrow' => $translationData['eyebrow'] ?? null,
                    'subtitle' => $translationData['subtitle'] ?? null,
                    'content' => $translationData['content'] ?? null,
                    'meta' => $translationData['meta'] ?? null,
                    'created_at' => $translationData['created_at'] ?? null,
                    'updated_at' => $translationData['updated_at'] ?? null,
                ]);
                $count++;
            }
        }

        foreach (($payload['block_button_translations'] ?? []) as $translationData) {
            $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
            $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

            if ($blockId && $localeId) {
                \App\Models\BlockButtonTranslation::query()->create([
                    'block_id' => $blockId,
                    'locale_id' => $localeId,
                    'title' => $translationData['title'] ?? null,
                    'created_at' => $translationData['created_at'] ?? null,
                    'updated_at' => $translationData['updated_at'] ?? null,
                ]);
                $count++;
            }
        }

        foreach (($payload['block_image_translations'] ?? []) as $translationData) {
            $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
            $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

            if ($blockId && $localeId) {
                \App\Models\BlockImageTranslation::query()->create([
                    'block_id' => $blockId,
                    'locale_id' => $localeId,
                    'caption' => $translationData['caption'] ?? null,
                    'alt_text' => $translationData['alt_text'] ?? null,
                    'created_at' => $translationData['created_at'] ?? null,
                    'updated_at' => $translationData['updated_at'] ?? null,
                ]);
                $count++;
            }
        }

        foreach (($payload['block_contact_form_translations'] ?? []) as $translationData) {
            $blockId = $blockMap[(int) ($translationData['block_id'] ?? 0)] ?? null;
            $localeId = $localeMap[(int) ($translationData['locale_id'] ?? 0)] ?? null;

            if ($blockId && $localeId) {
                \App\Models\BlockContactFormTranslation::query()->create([
                    'block_id' => $blockId,
                    'locale_id' => $localeId,
                    'title' => $translationData['title'] ?? null,
                    'content' => $translationData['content'] ?? null,
                    'submit_label' => $translationData['submit_label'] ?? null,
                    'success_message' => $translationData['success_message'] ?? null,
                    'created_at' => $translationData['created_at'] ?? null,
                    'updated_at' => $translationData['updated_at'] ?? null,
                ]);
                $count++;
            }
        }

        $output[] = 'Imported '.$count.' block translation row(s).';

        Block::query()
            ->whereIn('id', array_values($blockMap))
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get()
            ->each(fn (Block $block) => $this->blockTranslationWriter->normalizeCanonicalStorage($block));
    }

    private function importBlockAssets(array $payload, array $blockMap, array $assetMap, array &$output): void
    {
        $count = 0;

        foreach (($payload['block_assets'] ?? []) as $blockAssetData) {
            $blockId = $blockMap[(int) ($blockAssetData['block_id'] ?? 0)] ?? null;
            $assetId = $assetMap[(int) ($blockAssetData['asset_id'] ?? 0)] ?? null;

            if (! $blockId || ! $assetId) {
                continue;
            }

            BlockAsset::query()->create([
                'block_id' => $blockId,
                'asset_id' => $assetId,
                'role' => $blockAssetData['role'] ?? null,
                'position' => $blockAssetData['position'] ?? 0,
                'created_at' => $blockAssetData['created_at'] ?? null,
                'updated_at' => $blockAssetData['updated_at'] ?? null,
            ]);

            $count++;
        }

        $output[] = 'Imported '.$count.' block asset link(s).';
    }

    private function importNavigation(Site $site, array $payload, array $pageMap, array &$output): void
    {
        $map = [];

        foreach ($payload['navigation_items'] as $itemData) {
            $item = NavigationItem::query()->create([
                'site_id' => $site->id,
                'menu_key' => $itemData['menu_key'] ?? NavigationItem::MENU_PRIMARY,
                'parent_id' => null,
                'page_id' => $pageMap[(int) ($itemData['page_id'] ?? 0)] ?? null,
                'title' => $itemData['title'] ?? null,
                'link_type' => $itemData['link_type'] ?? NavigationItem::LINK_CUSTOM_URL,
                'url' => $itemData['url'] ?? null,
                'target' => $itemData['target'] ?? null,
                'position' => $itemData['position'] ?? 0,
                'visibility' => $itemData['visibility'] ?? NavigationItem::VISIBILITY_VISIBLE,
                'is_system' => (bool) ($itemData['is_system'] ?? false),
                'created_at' => $itemData['created_at'] ?? null,
                'updated_at' => $itemData['updated_at'] ?? null,
            ]);

            $map[(int) $itemData['id']] = $item->id;
        }

        foreach ($payload['navigation_items'] as $itemData) {
            $itemId = $map[(int) ($itemData['id'] ?? 0)] ?? null;
            $parentId = $map[(int) ($itemData['parent_id'] ?? 0)] ?? null;

            if ($itemId && $parentId) {
                NavigationItem::query()->whereKey($itemId)->update(['parent_id' => $parentId]);
            }
        }

        $output[] = 'Imported '.count($map).' navigation item(s).';
    }

    private function availableHandle(string $requestedHandle): string
    {
        $handle = $requestedHandle;

        if (! Site::query()->where('handle', $handle)->exists()) {
            return $handle;
        }

        $handle = $requestedHandle.'-imported';

        if (! Site::query()->where('handle', $handle)->exists()) {
            return $handle;
        }

        $suffix = 2;

        while (Site::query()->where('handle', $handle.'-'.$suffix)->exists()) {
            $suffix++;
        }

        return $handle.'-'.$suffix;
    }

    private function availableAssetPath(string $diskName, string $requestedPath): string
    {
        $this->pathGuard->assertSafeRelativePath($requestedPath, 'Asset path');

        if (! Storage::disk($diskName)->exists($requestedPath)) {
            return $requestedPath;
        }

        $directory = trim(pathinfo($requestedPath, PATHINFO_DIRNAME), '.');
        $filename = pathinfo($requestedPath, PATHINFO_FILENAME);
        $extension = pathinfo($requestedPath, PATHINFO_EXTENSION);
        $candidate = ($directory !== '' ? $directory.'/' : '').$filename.'-'.Str::lower(Str::random(8)).($extension !== '' ? '.'.$extension : '');

        return $candidate;
    }
}
