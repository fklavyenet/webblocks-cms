<?php

namespace Tests\Concerns;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Support\Facades\Storage;

trait BuildsCloneableSite
{
    protected function seedCloneableSite(bool $withFile = false): array
    {
        if ($withFile) {
            Storage::fake('public');
        }

        $sourceSite = Site::query()->create([
            'name' => 'WebBlocks UI',
            'handle' => 'webblocks-ui',
            'domain' => 'webblocksui.com',
            'is_primary' => false,
        ]);

        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $turkish = Locale::query()->create([
            'code' => 'tr',
            'name' => 'Turkish',
            'is_default' => false,
            'is_enabled' => true,
        ]);
        $sourceSite->locales()->sync([
            $defaultLocale->id => ['is_enabled' => true],
            $turkish->id => ['is_enabled' => true],
        ]);

        $mainSlotType = SlotType::query()->firstOrCreate(
            ['slug' => 'main'],
            ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );

        $columnsType = BlockType::query()->firstOrCreate(
            ['slug' => 'columns'],
            ['name' => 'Columns', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
        );
        $columnItemType = BlockType::query()->firstOrCreate(
            ['slug' => 'column_item'],
            ['name' => 'Column Item', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2, 'is_system' => true],
        );
        $imageType = BlockType::query()->firstOrCreate(
            ['slug' => 'image'],
            ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 3, 'is_system' => false],
        );

        $homePage = Page::query()->create([
            'site_id' => $sourceSite->id,
            'title' => 'Home',
            'slug' => 'home',
            'status' => 'published',
        ]);

        $aboutPage = Page::query()->create([
            'site_id' => $sourceSite->id,
            'title' => 'About',
            'slug' => 'about',
            'status' => 'published',
        ]);

        PageTranslation::query()->create([
            'page_id' => $aboutPage->id,
            'locale_id' => $turkish->id,
            'name' => 'Hakkinda',
            'slug' => 'hakkinda',
            'path' => '/p/hakkinda',
        ]);

        foreach ([$homePage, $aboutPage] as $index => $page) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $mainSlotType->id,
                'sort_order' => $index,
            ]);
        }

        $heroAssetPath = 'media/images/hero.jpg';

        if ($withFile) {
            Storage::disk('public')->put($heroAssetPath, 'hero-image');
        }

        $heroAsset = Asset::query()->create([
            'disk' => 'public',
            'path' => $heroAssetPath,
            'filename' => 'hero.jpg',
            'original_name' => 'hero.jpg',
            'extension' => 'jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'kind' => Asset::KIND_IMAGE,
            'visibility' => 'public',
            'title' => 'Hero',
        ]);

        $columns = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'columns',
            'block_type_id' => $columnsType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'title' => 'Starter features',
            'content' => 'English features',
            'status' => 'published',
            'is_system' => true,
        ]);

        $columnItem = Block::query()->create([
            'page_id' => $aboutPage->id,
            'parent_id' => $columns->id,
            'type' => 'column_item',
            'block_type_id' => $columnItemType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'title' => 'Fast setup',
            'content' => 'English child content',
            'status' => 'published',
            'is_system' => false,
        ]);

        $columnItem->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Fast setup',
            'content' => 'English child content',
        ]);
        $columnItem->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Hizli kurulum',
            'content' => 'Turkce alt icerik',
        ]);

        $imageBlock = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'image',
            'block_type_id' => $imageType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 1,
            'title' => 'Hero image',
            'subtitle' => 'Hero alt',
            'asset_id' => $heroAsset->id,
            'status' => 'published',
            'is_system' => false,
        ]);

        $imageBlock->imageTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'caption' => 'Hero image',
            'alt_text' => 'Hero alt',
        ]);
        $imageBlock->imageTranslations()->create([
            'locale_id' => $turkish->id,
            'caption' => 'Kahraman gorseli',
            'alt_text' => 'Kahraman alternatif',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($columns->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($columnItem->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($imageBlock->fresh(['imageTranslations']));

        NavigationItem::query()->create([
            'site_id' => $sourceSite->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'title' => 'About',
            'link_type' => NavigationItem::LINK_PAGE,
            'page_id' => $aboutPage->id,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
        ]);

        return [$sourceSite, $heroAsset];
    }
}
