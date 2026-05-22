<?php

namespace Tests\Concerns;

use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use Illuminate\Support\Facades\Storage;

trait BuildsCloneableSite
{
    protected function seedCloneableSite(bool $withFile = false): array
    {
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
        $headerSlotType = SlotType::query()->firstOrCreate(
            ['slug' => 'header'],
            ['name' => 'Header', 'status' => 'published', 'sort_order' => 0, 'is_system' => true],
        );

        $headerType = BlockType::query()->firstOrCreate(
            ['slug' => 'header'],
            ['name' => 'Header', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1, 'is_system' => false],
        );
        $plainTextType = BlockType::query()->firstOrCreate(
            ['slug' => 'plain_text'],
            ['name' => 'Plain Text', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 2, 'is_system' => false],
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

        PageTranslation::query()->create([
            'page_id' => $homePage->id,
            'locale_id' => $turkish->id,
            'name' => 'Ana Sayfa',
            'slug' => 'anasayfa',
            'path' => '/p/anasayfa',
        ]);

        foreach ([$homePage, $aboutPage] as $page) {
            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $headerSlotType->id,
                'sort_order' => 0,
            ]);

            PageSlot::query()->create([
                'page_id' => $page->id,
                'slot_type_id' => $mainSlotType->id,
                'sort_order' => 1,
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

        $aboutPage->defaultTranslation()?->update([
            'seo_title' => 'About SEO',
            'seo_description' => 'About SEO Description',
            'og_title' => 'About OG',
            'og_description' => 'About OG Description',
            'og_image_media_id' => $heroAsset->id,
        ]);

        $homePage->defaultTranslation()?->update([
            'seo_title' => 'Home SEO',
            'seo_description' => 'Home SEO Description',
        ]);

        $aboutPage->translations()->where('locale_id', $turkish->id)->update([
            'seo_title' => 'Hakkinda SEO',
            'seo_description' => 'Hakkinda SEO Description',
            'og_title' => 'Hakkinda OG',
            'og_description' => 'Hakkinda OG Description',
            'og_image_media_id' => $heroAsset->id,
        ]);

        $homePage->translations()->where('locale_id', $turkish->id)->update([
            'seo_title' => 'Ana Sayfa SEO',
            'seo_description' => 'Ana Sayfa SEO Description',
        ]);

        $header = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'variant' => 'h1',
            'status' => 'published',
            'is_system' => false,
        ]);

        $header->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'About',
            'subtitle' => null,
            'content' => null,
        ]);
        $header->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Hakkinda',
            'subtitle' => null,
            'content' => null,
        ]);

        $plainText = Block::query()->create([
            'page_id' => $aboutPage->id,
            'type' => 'plain_text',
            'block_type_id' => $plainTextType->id,
            'source_type' => 'static',
            'slot' => 'main',
            'slot_type_id' => $mainSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $plainText->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => null,
            'subtitle' => null,
            'content' => 'English paragraph content',
        ]);
        $plainText->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => null,
            'subtitle' => null,
            'content' => 'Turkce paragraf icerigi',
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

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($header->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($plainText->fresh(['textTranslations']));
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

        $sharedSlot = SharedSlot::query()->create([
            'site_id' => $sourceSite->id,
            'name' => 'Shared Header',
            'handle' => 'shared-header',
            'slot_name' => 'header',
            'public_shell' => null,
            'is_active' => true,
        ]);
        $sharedSourcePage = app(SharedSlotSourcePageManager::class)->ensureFor($sharedSlot);

        $sharedSectionType = BlockType::query()->firstOrCreate(
            ['slug' => 'section'],
            ['name' => 'Section', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 10, 'is_system' => false],
        );
        $sharedContainerType = BlockType::query()->firstOrCreate(
            ['slug' => 'container'],
            ['name' => 'Container', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 11, 'is_system' => false],
        );

        $sharedRoot = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'type' => 'section',
            'block_type_id' => $sharedSectionType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedRoot->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Shared Header Root',
        ]);
        $sharedRoot->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Paylasilan Baslik Koku',
        ]);

        $sharedContainer = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedRoot->id,
            'type' => 'container',
            'block_type_id' => $sharedContainerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'status' => 'published',
            'is_system' => false,
        ]);

        $sharedHeader = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedContainer->id,
            'type' => 'header',
            'block_type_id' => $headerType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 0,
            'variant' => 'h2',
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedHeader->textTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'title' => 'Shared About Header',
        ]);
        $sharedHeader->textTranslations()->create([
            'locale_id' => $turkish->id,
            'title' => 'Paylasilan Hakkinda Basligi',
        ]);

        $sharedImage = Block::query()->create([
            'page_id' => $sharedSourcePage->id,
            'parent_id' => $sharedContainer->id,
            'type' => 'image',
            'block_type_id' => $imageType->id,
            'source_type' => 'static',
            'slot' => 'header',
            'slot_type_id' => $headerSlotType->id,
            'sort_order' => 1,
            'asset_id' => $heroAsset->id,
            'status' => 'published',
            'is_system' => false,
        ]);
        $sharedImage->imageTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'caption' => 'Shared hero image',
            'alt_text' => 'Shared hero alt',
        ]);
        $sharedImage->imageTranslations()->create([
            'locale_id' => $turkish->id,
            'caption' => 'Paylasilan kahraman gorseli',
            'alt_text' => 'Paylasilan kahraman alternatif',
        ]);

        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedRoot->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedHeader->fresh(['textTranslations']));
        app(BlockTranslationWriter::class)->normalizeCanonicalStorage($sharedImage->fresh(['imageTranslations']));

        app(SharedSlotSourcePageManager::class)->rebuildAssignments($sharedSlot);

        PageSlot::query()
            ->where('page_id', $aboutPage->id)
            ->where('slot_type_id', $headerSlotType->id)
            ->update([
                'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
                'shared_slot_id' => $sharedSlot->id,
            ]);

        $disabledSlotType = SlotType::query()->firstOrCreate(
            ['slug' => 'sidebar'],
            ['name' => 'Sidebar', 'status' => 'published', 'sort_order' => 2, 'is_system' => true],
        );
        PageSlot::query()->create([
            'page_id' => $aboutPage->id,
            'slot_type_id' => $disabledSlotType->id,
            'source_type' => PageSlot::SOURCE_TYPE_DISABLED,
            'sort_order' => 2,
        ]);

        return [$sourceSite, $heroAsset, $sharedSlot];
    }

    protected function addGalleryBlockToPage(Page $page, Asset $asset, Locale $defaultLocale, Locale $translatedLocale, string $slot = 'main', int $sortOrder = 2): array
    {
        $slotType = SlotType::query()->firstOrCreate(
            ['slug' => $slot],
            ['name' => ucfirst($slot), 'status' => 'published', 'sort_order' => $slot === 'header' ? 0 : 1, 'is_system' => true],
        );
        $galleryType = BlockType::query()->firstOrCreate(
            ['slug' => 'gallery'],
            ['name' => 'Gallery', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 4, 'is_system' => false],
        );

        $gallery = Block::query()->create([
            'page_id' => $page->id,
            'type' => 'gallery',
            'block_type_id' => $galleryType->id,
            'source_type' => 'static',
            'slot' => $slot,
            'slot_type_id' => $slotType->id,
            'sort_order' => $sortOrder,
            'settings' => json_encode([
                'variant' => 'grid',
                'columns' => '3',
                'captions_mode' => 'below',
                'lightbox_enabled' => true,
            ], JSON_UNESCAPED_SLASHES),
            'status' => 'published',
            'is_system' => false,
        ]);

        $galleryItem = $gallery->blockAssets()->create([
            'media_id' => $asset->id,
            'role' => 'gallery_item',
            'position' => 0,
        ]);

        $galleryItem->galleryItemTranslations()->create([
            'locale_id' => $defaultLocale->id,
            'alt_text' => 'Gallery alt',
            'caption' => 'Gallery caption',
            'overlay_title' => 'Gallery overlay title',
            'overlay_text' => 'Gallery overlay text',
        ]);
        $galleryItem->galleryItemTranslations()->create([
            'locale_id' => $translatedLocale->id,
            'alt_text' => 'Galeri alternatif metni',
            'caption' => 'Galeri aciklamasi',
            'overlay_title' => 'Galeri katman basligi',
            'overlay_text' => 'Galeri katman metni',
        ]);

        return [$gallery->fresh(['blockAssets.galleryItemTranslations']), $galleryItem->fresh(['galleryItemTranslations'])];
    }
}
