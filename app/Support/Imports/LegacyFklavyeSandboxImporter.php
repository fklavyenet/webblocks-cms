<?php

namespace App\Support\Imports;

use App\Models\Block;
use App\Models\Locale;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LegacyFklavyeSandboxImporter
{
    public function __construct(private readonly BlockTranslationWriter $blockTranslationWriter) {}

    public function import(array $sourceConfig): array
    {
        $source = $this->configureSourceConnection($sourceConfig);

        $sourcePages = $source->table('pages')->orderBy('id')->get();
        $sourceBlocks = $source->table('blocks')->orderBy('id')->get();
        $sourceNavigationItems = $source->table('navigation_items')->orderBy('location')->orderBy('sort_order')->orderBy('id')->get();
        $sourceAssets = $source->table('assets')->orderBy('id')->get();
        $sourceAssetFolders = $source->table('asset_folders')->orderBy('id')->get();
        $sourceBlockTypeSlugs = $source->table('block_types')->pluck('slug', 'id');

        $cleanupCounts = $this->cleanupTargetSiteContent();

        $summary = DB::transaction(function () use (
            $sourcePages,
            $sourceBlocks,
            $sourceNavigationItems,
            $sourceAssets,
            $sourceAssetFolders,
            $sourceBlockTypeSlugs
        ): array {
            $this->ensureImportedBlockTypes();

            $pageTypeId = DB::table('page_types')->where('slug', 'default')->value('id');
            $slotTypeIds = DB::table('slot_types')->whereIn('slug', ['header', 'main', 'footer'])->pluck('id', 'slug');
            $blockTypeMap = DB::table('block_types')->pluck('id', 'slug');
            $blockTypeSourceTypes = DB::table('block_types')->pluck('source_type', 'slug');
            $adminUserId = (int) (DB::table('users')->where('email', 'admin@example.com')->value('id') ?: DB::table('users')->min('id'));

            $this->importAssetFolders($sourceAssetFolders);
            $this->importAssets($sourceAssets, $adminUserId);
            $this->importPages($sourcePages, $pageTypeId);
            $this->importPageSlots($sourcePages, $slotTypeIds);
            $this->importMainBlocks($sourceBlocks, $sourceBlockTypeSlugs, $blockTypeMap, $blockTypeSourceTypes, (int) $slotTypeIds['main']);
            $this->seedDefaultContactFormTranslations();
            $this->importNavigation($sourceNavigationItems);
            $this->seedSiteChrome($sourcePages, $blockTypeMap, $blockTypeSourceTypes, $slotTypeIds, $sourceBlocks, $sourceBlockTypeSlugs);

            return [
                'pages' => $sourcePages->count(),
                'blocks' => DB::table('blocks')->count(),
                'navigation_items' => DB::table('navigation_items')->count(),
                'assets' => DB::table('assets')->count(),
                'asset_folders' => DB::table('asset_folders')->count(),
                'page_slots' => DB::table('page_slots')->count(),
                'block_assets' => DB::table('block_assets')->count(),
            ];
        });

        Block::query()
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->orderBy('id')
            ->get()
            ->each(fn (Block $block) => $this->blockTranslationWriter->normalizeCanonicalStorage($block));

        return [
            'cleanup' => $cleanupCounts,
            'imported' => $summary,
        ];
    }

    private function configureSourceConnection(array $sourceConfig): ConnectionInterface
    {
        $configuredConnection = config('database.connections.legacy_fklavye_source');
        $driver = (string) ($sourceConfig['driver'] ?? $configuredConnection['driver'] ?? 'mysql');

        $connection = match ($driver) {
            'sqlite' => [
                'driver' => 'sqlite',
                'database' => $sourceConfig['database'],
                'prefix' => $sourceConfig['prefix'] ?? ($configuredConnection['prefix'] ?? ''),
                'foreign_key_constraints' => (bool) ($sourceConfig['foreign_key_constraints'] ?? $configuredConnection['foreign_key_constraints'] ?? false),
            ],
            default => array_merge(config('database.connections.mysql'), [
                'driver' => $driver,
                'host' => $sourceConfig['host'],
                'port' => $sourceConfig['port'],
                'database' => $sourceConfig['database'],
                'username' => $sourceConfig['username'],
                'password' => $sourceConfig['password'],
            ]),
        };

        config()->set('database.connections.legacy_fklavye_source', $connection);

        DB::purge('legacy_fklavye_source');

        return DB::connection('legacy_fklavye_source');
    }

    private function cleanupTargetSiteContent(): array
    {
        return DB::transaction(function (): array {
            $primarySiteId = (int) (Site::query()->where('is_primary', true)->value('id') ?: 0);
            $counts = [
                'block_assets' => DB::table('block_assets')->count(),
                'blocks' => DB::table('blocks')->count(),
                'page_slots' => DB::table('page_slots')->count(),
                'navigation_items' => DB::table('navigation_items')->count(),
                'assets' => DB::table('assets')->count(),
                'asset_folders' => DB::table('asset_folders')->count(),
                'pages' => DB::table('pages')->count(),
                'layouts' => DB::table('layouts')->count(),
                'contact_messages' => Schema::hasTable('contact_messages') ? DB::table('contact_messages')->count() : 0,
            ];

            if ($primarySiteId > 0) {
                DB::table('navigation_items')->where('site_id', $primarySiteId)->delete();
                DB::table('pages')->where('site_id', $primarySiteId)->delete();
            }

            DB::table('block_assets')->delete();
            DB::table('blocks')->delete();
            DB::table('page_slots')->delete();
            DB::table('assets')->delete();
            DB::table('asset_folders')->delete();
            DB::table('layouts')->delete();

            if (Schema::hasTable('contact_messages')) {
                DB::table('contact_messages')->delete();
            }

            return $counts;
        });
    }

    private function ensureImportedBlockTypes(): void
    {
        $now = now();

        foreach ([
            ['name' => 'Text', 'slug' => 'text', 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => false, 'sort_order' => 1, 'status' => 'published'],
            ['name' => 'Rich Text', 'slug' => 'rich-text', 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => false, 'sort_order' => 3, 'status' => 'published'],
            ['name' => 'Image', 'slug' => 'image', 'category' => 'media', 'source_type' => 'asset', 'is_system' => false, 'is_container' => false, 'sort_order' => 12, 'status' => 'published'],
            ['name' => 'Navigation Auto', 'slug' => 'navigation-auto', 'category' => 'system', 'source_type' => 'navigation', 'is_system' => true, 'is_container' => false, 'sort_order' => 58, 'status' => 'published'],
            ['name' => 'Hero', 'slug' => 'hero', 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => false, 'sort_order' => 80, 'status' => 'published'],
            ['name' => 'Card Grid', 'slug' => 'card-grid', 'category' => 'data display', 'source_type' => 'static', 'is_system' => false, 'is_container' => true, 'sort_order' => 81, 'status' => 'published'],
            ['name' => 'Showcase List', 'slug' => 'showcase-list', 'category' => 'data display', 'source_type' => 'static', 'is_system' => false, 'is_container' => true, 'sort_order' => 82, 'status' => 'published'],
            ['name' => 'Contact Info', 'slug' => 'contact-info', 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => false, 'sort_order' => 83, 'status' => 'published'],
        ] as $definition) {
            DB::table('block_types')->updateOrInsert(
                ['slug' => $definition['slug']],
                $definition + ['created_at' => $now, 'updated_at' => $now],
            );
        }
    }

    private function importAssetFolders($sourceAssetFolders): void
    {
        foreach ($sourceAssetFolders as $folder) {
            DB::table('asset_folders')->insert([
                'id' => $folder->id,
                'parent_id' => $folder->parent_id,
                'name' => $folder->name,
                'slug' => $folder->slug,
                'created_at' => $folder->created_at,
                'updated_at' => $folder->updated_at,
            ]);
        }
    }

    private function importAssets($sourceAssets, int $adminUserId): void
    {
        foreach ($sourceAssets as $asset) {
            DB::table('assets')->insert([
                'id' => $asset->id,
                'folder_id' => $asset->asset_folder_id,
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
                'width' => null,
                'height' => null,
                'duration' => null,
                'uploaded_by' => $adminUserId > 0 ? $adminUserId : null,
                'created_at' => $asset->created_at,
                'updated_at' => $asset->updated_at,
            ]);
        }
    }

    private function importPages($sourcePages, mixed $pageTypeId): void
    {
        $primarySiteId = (int) (Site::query()->where('is_primary', true)->value('id') ?: 0);
        $defaultLocaleId = (int) (Locale::query()->where('is_default', true)->value('id') ?: 0);

        foreach ($sourcePages as $page) {
            $payload = [
                'id' => $page->id,
                'site_id' => $primarySiteId,
                'page_type' => 'default',
                'page_type_id' => $pageTypeId,
                'layout_id' => null,
                'status' => $page->status,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ];

            if (Schema::hasColumn('pages', 'meta_title')) {
                $payload['meta_title'] = $page->meta_title ?? null;
            }

            if (Schema::hasColumn('pages', 'meta_description')) {
                $payload['meta_description'] = $page->meta_description ?? null;
            }

            DB::table('pages')->insert($payload);

            if ($defaultLocaleId > 0) {
                PageTranslation::query()->updateOrCreate(
                    ['page_id' => $page->id, 'locale_id' => $defaultLocaleId],
                    [
                        'site_id' => $primarySiteId,
                        'name' => $page->title,
                        'slug' => $page->slug,
                        'path' => PageTranslation::pathFromSlug((string) $page->slug),
                        'created_at' => $page->created_at,
                        'updated_at' => $page->updated_at,
                    ],
                );
            }
        }
    }

    private function importPageSlots($sourcePages, $slotTypeIds): void
    {
        foreach ($sourcePages as $page) {
            foreach (['header', 'main', 'footer'] as $index => $slotSlug) {
                DB::table('page_slots')->insert([
                    'page_id' => $page->id,
                    'slot_type_id' => $slotTypeIds[$slotSlug],
                    'sort_order' => $index,
                    'created_at' => $page->created_at,
                    'updated_at' => $page->updated_at,
                ]);
            }
        }
    }

    private function importMainBlocks($sourceBlocks, $sourceBlockTypeSlugs, $blockTypeMap, $blockTypeSourceTypes, int $mainSlotTypeId): void
    {
        foreach ($sourceBlocks as $block) {
            $sourceSlug = (string) ($sourceBlockTypeSlugs[$block->block_type_id] ?? 'text');
            $targetSlug = $sourceSlug === 'contact-form' ? 'contact_form' : $sourceSlug;
            $targetTypeId = $blockTypeMap[$targetSlug] ?? $blockTypeMap['text'];
            $settings = $this->normalizeBlockSettings($targetSlug, $block->settings);

            DB::table('blocks')->insert([
                'id' => $block->id,
                'page_id' => $block->page_id,
                'parent_id' => null,
                'type' => $targetSlug,
                'block_type_id' => $targetTypeId,
                'source_type' => $blockTypeSourceTypes[$targetSlug] ?? 'static',
                'slot' => 'main',
                'slot_type_id' => $mainSlotTypeId,
                'sort_order' => $block->sort_order,
                'title' => $block->title,
                'subtitle' => $block->subtitle,
                'content' => $targetSlug === 'contact_form' && blank($block->content) ? $block->subtitle : $block->content,
                'url' => $block->url,
                'asset_id' => $block->asset_id,
                'variant' => $targetSlug === 'hero' ? 'accent' : null,
                'meta' => null,
                'settings' => $settings,
                'status' => $block->status,
                'is_system' => false,
                'created_at' => $block->created_at,
                'updated_at' => $block->updated_at,
            ]);

            $this->importDerivedBlockAssets($block->id, $targetSlug, $settings);
        }
    }

    private function importNavigation($sourceNavigationItems): void
    {
        $primarySiteId = (int) (Site::query()->where('is_primary', true)->value('id') ?: 0);

        foreach ($sourceNavigationItems as $item) {
            DB::table('navigation_items')->insert([
                'site_id' => $primarySiteId,
                'menu_key' => $item->location,
                'parent_id' => null,
                'page_id' => $item->page_id,
                'title' => $item->title,
                'link_type' => $item->page_id ? 'page' : 'custom_url',
                'url' => $item->url,
                'target' => $item->target,
                'position' => $item->sort_order,
                'visibility' => $item->status === 'published' ? 'visible' : 'hidden',
                'is_system' => false,
                'created_at' => $item->created_at,
                'updated_at' => $item->updated_at,
            ]);

            if ($item->location === 'primary') {
                DB::table('navigation_items')->insert([
                    'site_id' => $primarySiteId,
                    'menu_key' => 'mobile',
                    'parent_id' => null,
                    'page_id' => $item->page_id,
                    'title' => $item->title,
                    'link_type' => $item->page_id ? 'page' : 'custom_url',
                    'url' => $item->url,
                    'target' => $item->target,
                    'position' => $item->sort_order,
                    'visibility' => $item->status === 'published' ? 'visible' : 'hidden',
                    'is_system' => false,
                    'created_at' => $item->created_at,
                    'updated_at' => $item->updated_at,
                ]);
            }
        }
    }

    private function seedSiteChrome($sourcePages, $blockTypeMap, $blockTypeSourceTypes, $slotTypeIds, $sourceBlocks, $sourceBlockTypeSlugs): void
    {
        $homeHero = $sourceBlocks->first(function ($block) use ($sourceBlockTypeSlugs) {
            return $block->page_id === 1 && ($sourceBlockTypeSlugs[$block->block_type_id] ?? null) === 'hero';
        });

        $brandingTitle = $homeHero?->title ?: 'Fklavye Web Services';
        $brandingContext = $homeHero?->subtitle ?: 'Web Services, Web Design, Web Programming, Update, Hosting, E-commerce';
        $brandingAssetId = (int) (DB::table('assets')->where('filename', 'logo.png')->value('id') ?: 0);

        foreach ($sourcePages as $page) {
            DB::table('blocks')->insert([
                'page_id' => $page->id,
                'parent_id' => null,
                'type' => 'image',
                'block_type_id' => $blockTypeMap['image'],
                'source_type' => $blockTypeSourceTypes['image'] ?? 'asset',
                'slot' => 'header',
                'slot_type_id' => $slotTypeIds['header'],
                'sort_order' => 0,
                'title' => $brandingTitle,
                'subtitle' => null,
                'content' => $brandingContext,
                'url' => null,
                'asset_id' => $brandingAssetId > 0 ? $brandingAssetId : null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);

            DB::table('blocks')->insert([
                'page_id' => $page->id,
                'parent_id' => null,
                'type' => 'navigation-auto',
                'block_type_id' => $blockTypeMap['navigation-auto'],
                'source_type' => $blockTypeSourceTypes['navigation-auto'] ?? 'navigation',
                'slot' => 'header',
                'slot_type_id' => $slotTypeIds['header'],
                'sort_order' => 1,
                'title' => null,
                'subtitle' => null,
                'content' => null,
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => json_encode(['menu_key' => 'primary'], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => true,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);

            DB::table('blocks')->insert([
                'page_id' => $page->id,
                'parent_id' => null,
                'type' => 'navigation-auto',
                'block_type_id' => $blockTypeMap['navigation-auto'],
                'source_type' => $blockTypeSourceTypes['navigation-auto'] ?? 'navigation',
                'slot' => 'header',
                'slot_type_id' => $slotTypeIds['header'],
                'sort_order' => 2,
                'title' => null,
                'subtitle' => null,
                'content' => null,
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => json_encode(['menu_key' => 'mobile'], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => true,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);

            DB::table('blocks')->insert([
                'page_id' => $page->id,
                'parent_id' => null,
                'type' => 'navigation-auto',
                'block_type_id' => $blockTypeMap['navigation-auto'],
                'source_type' => $blockTypeSourceTypes['navigation-auto'] ?? 'navigation',
                'slot' => 'footer',
                'slot_type_id' => $slotTypeIds['footer'],
                'sort_order' => 0,
                'title' => null,
                'subtitle' => null,
                'content' => null,
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => json_encode(['menu_key' => 'footer'], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => true,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);

            DB::table('blocks')->insert([
                'page_id' => $page->id,
                'parent_id' => null,
                'type' => 'rich-text',
                'block_type_id' => $blockTypeMap['rich-text'],
                'source_type' => $blockTypeSourceTypes['rich-text'] ?? 'static',
                'slot' => 'footer',
                'slot_type_id' => $slotTypeIds['footer'],
                'sort_order' => 1,
                'title' => null,
                'subtitle' => null,
                'content' => $brandingTitle,
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => false,
                'created_at' => $page->created_at,
                'updated_at' => $page->updated_at,
            ]);
        }
    }

    private function normalizeBlockSettings(string $targetSlug, mixed $settings): ?string
    {
        $decoded = [];

        if (is_string($settings) && trim($settings) !== '') {
            $decoded = json_decode($settings, true) ?: [];
        }

        if (! is_array($decoded)) {
            $decoded = [];
        }

        if ($targetSlug === 'contact_form') {
            $decoded = array_merge([
                'recipient_email' => null,
                'send_email_notification' => true,
                'store_submissions' => true,
            ], $decoded);
        }

        return $decoded === [] ? null : json_encode($decoded, JSON_UNESCAPED_SLASHES);
    }

    private function seedDefaultContactFormTranslations(): void
    {
        $defaultLocaleId = DB::table('locales')->where('is_default', true)->value('id');

        if (! $defaultLocaleId) {
            return;
        }

        DB::table('blocks')
            ->where('type', 'contact_form')
            ->orderBy('id')
            ->get()
            ->each(function (object $block) use ($defaultLocaleId): void {
                DB::table('block_contact_form_translations')->updateOrInsert(
                    ['block_id' => $block->id, 'locale_id' => $defaultLocaleId],
                    [
                        'title' => $block->title,
                        'content' => $block->content,
                        'submit_label' => 'Send message',
                        'success_message' => 'Thanks for your message. We will get back to you soon.',
                        'created_at' => $block->created_at,
                        'updated_at' => $block->updated_at,
                    ],
                );
            });
    }

    private function importDerivedBlockAssets(int $blockId, string $targetSlug, ?string $settings): void
    {
        $decoded = is_string($settings) && trim($settings) !== '' ? (json_decode($settings, true) ?: []) : [];

        if ($targetSlug === 'gallery') {
            foreach (collect($decoded['items'] ?? [])->pluck('asset_id')->filter() as $position => $assetId) {
                DB::table('block_assets')->insert([
                    'block_id' => $blockId,
                    'asset_id' => (int) $assetId,
                    'role' => 'gallery_item',
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
