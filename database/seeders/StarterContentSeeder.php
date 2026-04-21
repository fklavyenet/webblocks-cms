<?php

namespace Database\Seeders;

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
use App\Models\User;
use App\Support\Blocks\BlockTranslationRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class StarterContentSeeder extends Seeder
{
    public function run(): void
    {
        $defaultSite = Site::query()->where('is_primary', true)->firstOrFail();
        $defaultSite->update(['domain' => 'primary.ddev.site']);
        $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
        $turkishLocale = Locale::query()->updateOrCreate(
            ['code' => 'tr'],
            [
                'name' => 'Turkish',
                'is_default' => false,
                'is_enabled' => true,
            ],
        );

        $defaultSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
            $turkishLocale->id => ['is_enabled' => true],
        ]);

        $campaignSite = Site::query()->updateOrCreate(
            ['handle' => 'campaign'],
            [
                'name' => 'Campaign Site',
                'domain' => 'campaign.ddev.site',
                'is_primary' => false,
            ],
        );

        $campaignSite->locales()->syncWithoutDetaching([
            $defaultLocale->id => ['is_enabled' => true],
        ]);

        $homePage = Page::query()->updateOrCreate(
            ['site_id' => $defaultSite->id, 'slug' => 'home'],
            [
                'title' => 'Home',
                'page_type' => 'default',
                'status' => 'published',
            ],
        );

        $aboutPage = Page::query()->updateOrCreate(
            ['site_id' => $defaultSite->id, 'slug' => 'about'],
            [
                'title' => 'About',
                'page_type' => 'default',
                'status' => 'published',
            ],
        );

        $contactPage = Page::query()->updateOrCreate(
            ['site_id' => $defaultSite->id, 'slug' => 'contact'],
            [
                'title' => 'Contact',
                'page_type' => 'default',
                'status' => 'published',
            ],
        );

        $campaignHomePage = Page::query()->updateOrCreate(
            ['site_id' => $campaignSite->id, 'slug' => 'home'],
            [
                'title' => 'Campaign Home',
                'page_type' => 'default',
                'status' => 'published',
            ],
        );

        $campaignAboutPage = Page::query()->updateOrCreate(
            ['site_id' => $campaignSite->id, 'slug' => 'about'],
            [
                'title' => 'Campaign About',
                'page_type' => 'default',
                'status' => 'published',
            ],
        );

        foreach ([$homePage, $aboutPage, $contactPage, $campaignHomePage, $campaignAboutPage] as $page) {
            PageTranslation::query()->updateOrCreate(
                ['page_id' => $page->id, 'locale_id' => $defaultLocale->id],
                [
                    'name' => $page->title,
                    'slug' => $page->slug,
                    'path' => PageTranslation::pathFromSlug($page->slug),
                ],
            );
        }

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $homePage->id, 'locale_id' => $turkishLocale->id],
            [
                'name' => 'Ana Sayfa',
                'slug' => 'anasayfa',
                'path' => PageTranslation::pathFromSlug('anasayfa'),
            ],
        );

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $aboutPage->id, 'locale_id' => $turkishLocale->id],
            [
                'name' => 'Hakkinda',
                'slug' => 'hakkinda',
                'path' => PageTranslation::pathFromSlug('hakkinda'),
            ],
        );

        PageTranslation::query()->updateOrCreate(
            ['page_id' => $contactPage->id, 'locale_id' => $turkishLocale->id],
            [
                'name' => 'Iletisim',
                'slug' => 'iletisim',
                'path' => PageTranslation::pathFromSlug('iletisim'),
            ],
        );

        $campaignSlotTypes = SlotType::query()
            ->whereIn('slug', ['main'])
            ->get()
            ->keyBy('slug');

        $campaignMainSlotType = $campaignSlotTypes->get('main');

        if ($campaignMainSlotType) {
            PageSlot::query()->updateOrCreate(
                ['page_id' => $campaignHomePage->id, 'slot_type_id' => $campaignMainSlotType->id],
                ['sort_order' => 0],
            );

            PageSlot::query()->updateOrCreate(
                ['page_id' => $campaignAboutPage->id, 'slot_type_id' => $campaignMainSlotType->id],
                ['sort_order' => 0],
            );
        }

        $slotTypes = SlotType::query()
            ->whereIn('slug', ['header', 'main', 'footer'])
            ->get()
            ->keyBy('slug');

        $pageSlots = collect(['header', 'main', 'footer'])
            ->mapWithKeys(function (string $slug, int $index) use ($homePage, $slotTypes) {
                $slotType = $slotTypes->get($slug);

                if (! $slotType) {
                    return [];
                }

                $pageSlot = PageSlot::query()->updateOrCreate(
                    ['page_id' => $homePage->id, 'slot_type_id' => $slotType->id],
                    ['sort_order' => $index],
                );

                return [$slug => $pageSlot];
            });

        $contactSlotType = $slotTypes->get('main');

        if ($contactSlotType) {
            PageSlot::query()->updateOrCreate(
                ['page_id' => $contactPage->id, 'slot_type_id' => $contactSlotType->id],
                ['sort_order' => 0],
            );
        }

        $blockTypes = BlockType::query()
            ->whereIn('slug', ['navigation-auto', 'heading', 'section', 'columns', 'column_item', 'rich-text', 'image', 'button', 'page-title', 'contact_form'])
            ->get()
            ->keyBy('slug');

        $heroImage = Asset::query()->where('kind', Asset::KIND_IMAGE)->orderBy('id')->first();
        $seedUser = User::query()->where('email', 'test@example.com')->first();

        $this->seedNavigation($homePage, $aboutPage, $contactPage);

        $headerSlotTypeId = $pageSlots->get('header')?->slot_type_id;
        $mainSlotTypeId = $pageSlots->get('main')?->slot_type_id;
        $footerSlotTypeId = $pageSlots->get('footer')?->slot_type_id;

        if ($headerSlotTypeId) {
            $this->upsertBlock($homePage, $blockTypes->get('heading'), $headerSlotTypeId, [
                'sort_order' => 0,
                'title' => 'WebBlocks CMS',
                'subtitle' => null,
                'content' => null,
                'url' => null,
                'asset_id' => null,
                'variant' => 'h2',
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
            ]);

            $this->upsertBlock($homePage, $blockTypes->get('navigation-auto'), $headerSlotTypeId, [
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
            ]);
        }

        $heroSection = null;

        if ($mainSlotTypeId) {
            $heroSection = $this->upsertBlock($homePage, $blockTypes->get('section'), $mainSlotTypeId, [
                'sort_order' => 0,
                'title' => 'Build faster with WebBlocks CMS',
                'subtitle' => null,
                'content' => 'A modern block-based CMS for structured pages, reusable content, and flexible layouts.',
                'url' => null,
                'asset_id' => null,
                'variant' => 'accent',
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
            ]);

            if ($heroSection) {
                $this->upsertBlock($homePage, $blockTypes->get('button'), $mainSlotTypeId, [
                    'parent_id' => $heroSection->id,
                    'sort_order' => 0,
                    'title' => 'Get Started',
                    'subtitle' => '_self',
                    'content' => null,
                    'url' => route('login', [], false),
                    'asset_id' => null,
                    'variant' => 'primary',
                    'meta' => null,
                    'settings' => null,
                    'status' => 'published',
                    'is_system' => false,
                ]);
            }

            $columnsBlock = $this->upsertBlock($homePage, $blockTypes->get('columns'), $mainSlotTypeId, [
                'sort_order' => 1,
                'title' => 'Starter features',
                'subtitle' => 'Three examples of how slots and blocks can shape a page.',
                'content' => 'Edit, reorder, or replace any of these blocks from the admin builder.',
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
            ]);

            if ($columnsBlock) {
                $features = [
                    ['title' => 'Fast setup', 'content' => 'Start with meaningful defaults instead of an empty canvas.'],
                    ['title' => 'Flexible content', 'content' => 'Build pages from reusable slots and blocks.'],
                    ['title' => 'Editor friendly', 'content' => 'Update structure and content without touching templates.'],
                ];

                foreach ($features as $index => $feature) {
                    $this->upsertBlock($homePage, $blockTypes->get('text'), $mainSlotTypeId, [
                        'parent_id' => $columnsBlock->id,
                        'sort_order' => $index,
                        'title' => $feature['title'],
                        'subtitle' => null,
                        'content' => $feature['content'],
                        'url' => null,
                        'asset_id' => null,
                        'variant' => null,
                        'meta' => null,
                        'settings' => null,
                        'status' => 'published',
                        'is_system' => false,
                    ], $blockTypes->get('column_item'));
                }
            }

            $this->upsertBlock($homePage, $blockTypes->get('rich-text'), $mainSlotTypeId, [
                'sort_order' => 2,
                'title' => null,
                'subtitle' => null,
                'content' => "Why this starter exists\n\nThis page demonstrates how pages, slots, and blocks work together in WebBlocks CMS. You can edit every part of it from the admin builder and reshape the structure as your project evolves.",
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => false,
            ]);

            $this->upsertBlock($homePage, $blockTypes->get('image'), $mainSlotTypeId, [
                'sort_order' => 3,
                'title' => 'Starter media preview',
                'subtitle' => 'Replace this media block with your own visuals from the library.',
                'content' => null,
                'url' => null,
                'asset_id' => $heroImage?->id,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => false,
            ]);

            $ctaSection = $this->upsertBlock($homePage, $blockTypes->get('section'), $mainSlotTypeId, [
                'sort_order' => 4,
                'title' => 'Start building your site',
                'subtitle' => null,
                'content' => 'Replace these starter blocks, add your own content, and shape the page as you need.',
                'url' => null,
                'asset_id' => null,
                'variant' => 'muted',
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
            ]);

            if ($ctaSection) {
                $this->upsertBlock($homePage, $blockTypes->get('button'), $mainSlotTypeId, [
                    'parent_id' => $ctaSection->id,
                    'sort_order' => 0,
                    'title' => 'Create content',
                    'subtitle' => '_self',
                    'content' => null,
                    'url' => route('login', [], false),
                    'asset_id' => null,
                    'variant' => 'secondary',
                    'meta' => null,
                    'settings' => null,
                    'status' => 'published',
                    'is_system' => false,
                ]);
            }
        }

        if ($footerSlotTypeId) {
            $this->upsertBlock($homePage, $blockTypes->get('navigation-auto'), $footerSlotTypeId, [
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
            ]);

            $this->upsertBlock($homePage, $blockTypes->get('rich-text'), $footerSlotTypeId, [
                'sort_order' => 1,
                'title' => null,
                'subtitle' => null,
                'content' => 'WebBlocks CMS - A modern block-based CMS.',
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => false,
            ]);
        }

        if ($contactSlotType) {
            $this->upsertBlock($contactPage, $blockTypes->get('page-title'), $contactSlotType->id, [
                'sort_order' => 0,
                'title' => null,
                'subtitle' => null,
                'content' => null,
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => true,
            ]);

            $this->upsertBlock($contactPage, $blockTypes->get('contact_form'), $contactSlotType->id, [
                'sort_order' => 1,
                'title' => 'Contact us',
                'subtitle' => null,
                'content' => 'Tell us what you are planning and we will route your message to the right editorial or implementation contact.',
                'url' => null,
                'asset_id' => null,
                'variant' => null,
                'meta' => null,
                'settings' => json_encode([
                    'submit_label' => 'Send message',
                    'success_message' => 'Thanks for your message. We will get back to you soon.',
                    'recipient_email' => null,
                    'send_email_notification' => true,
                    'store_submissions' => true,
                ], JSON_UNESCAPED_SLASHES),
                'status' => 'published',
                'is_system' => false,
            ]);

            Block::query()
                ->where('page_id', $contactPage->id)
                ->whereIn('type', ['form', 'input', 'select', 'checkbox-group', 'radio-group', 'textarea', 'submit'])
                ->delete();
        }

        if ($seedUser) {
            Asset::query()->whereNull('uploaded_by')->update(['uploaded_by' => $seedUser->id]);
        }

        $this->seedBlockTranslations($homePage, $aboutPage, $contactPage, $turkishLocale);
        $this->seedCampaignSiteContent($campaignSite, $campaignHomePage, $campaignAboutPage, $blockTypes);
    }

    private function seedCampaignSiteContent(Site $campaignSite, Page $campaignHomePage, Page $campaignAboutPage, $blockTypes): void
    {
        $mainSlotType = SlotType::query()->where('slug', 'main')->first();

        if (! $mainSlotType) {
            return;
        }

        $campaignHero = $this->upsertBlock($campaignHomePage, $blockTypes->get('section'), $mainSlotType->id, [
            'sort_order' => 0,
            'title' => 'Campaign launch site',
            'subtitle' => null,
            'content' => 'This second site uses its own domain, keeps English only, and intentionally overlaps the about slug with the primary site.',
            'url' => null,
            'asset_id' => null,
            'variant' => 'accent',
            'meta' => null,
            'settings' => null,
            'status' => 'published',
            'is_system' => false,
        ]);

        if ($campaignHero) {
            $this->upsertBlock($campaignHomePage, $blockTypes->get('button'), $mainSlotType->id, [
                'parent_id' => $campaignHero->id,
                'sort_order' => 0,
                'title' => 'Read campaign details',
                'subtitle' => '_self',
                'content' => null,
                'url' => '/p/about',
                'asset_id' => null,
                'variant' => 'primary',
                'meta' => null,
                'settings' => null,
                'status' => 'published',
                'is_system' => false,
            ]);
        }

        $this->upsertBlock($campaignAboutPage, $blockTypes->get('section'), $mainSlotType->id, [
            'sort_order' => 0,
            'title' => 'About this campaign',
            'subtitle' => null,
            'content' => 'The campaign site shares the about slug with the primary site, but routing stays isolated by host.',
            'url' => null,
            'asset_id' => null,
            'variant' => 'muted',
            'meta' => null,
            'settings' => null,
            'status' => 'published',
            'is_system' => false,
        ]);
    }

    private function seedBlockTranslations(Page $homePage, Page $aboutPage, Page $contactPage, Locale $locale): void
    {
        $registry = app(BlockTranslationRegistry::class);

        Block::query()
            ->whereIn('page_id', [$homePage->id, $aboutPage->id, $contactPage->id])
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->get()
            ->each(function (Block $block) use ($locale, $registry): void {
                $family = $registry->familyFor($block);

                if (! $family) {
                    return;
                }

                if ($family === 'text') {
                    $payload = match ($block->getRawOriginal('title')) {
                        'Build faster with WebBlocks CMS' => [
                            'title' => 'WebBlocks CMS ile daha hizli olusturun',
                            'subtitle' => null,
                            'content' => 'Yapilandirilmis sayfalar, tekrar kullanilabilir icerik ve esnek yerlesimler icin modern bir blok tabanli CMS.',
                        ],
                        'Starter features' => [
                            'title' => 'Baslangic ozellikleri',
                            'subtitle' => 'Yuvalarin ve bloklarin bir sayfayi nasil sekillendirebilecegine dair uc ornek.',
                            'content' => 'Bu bloklarin tamamini yonetim arayuzunden duzenleyebilir, siralayabilir veya degistirebilirsiniz.',
                        ],
                        'Fast setup' => [
                            'title' => 'Hizli kurulum',
                            'subtitle' => null,
                            'content' => 'Bos bir tuval yerine anlamli varsayilanlarla baslayin.',
                        ],
                        'Flexible content' => [
                            'title' => 'Esnek icerik',
                            'subtitle' => null,
                            'content' => 'Sayfalari tekrar kullanilabilir yuvalar ve bloklarla kurun.',
                        ],
                        'Contact us' => [
                            'title' => 'Bize ulasin',
                            'subtitle' => null,
                            'content' => 'Planladiginiz isi bize anlatin, mesajinizi dogru editor veya uygulama sorumlusuna yonlendirelim.',
                        ],
                        default => null,
                    };

                    if ($payload) {
                        $block->textTranslations()->updateOrCreate(['locale_id' => $locale->id], $payload);
                    }

                    return;
                }

                if ($family === 'button') {
                    $payload = match ($block->getRawOriginal('title')) {
                        'Get Started' => ['title' => 'Baslayin'],
                        default => null,
                    };

                    if ($payload) {
                        $block->buttonTranslations()->updateOrCreate(['locale_id' => $locale->id], $payload);
                    }

                    return;
                }

                if ($family === 'image' && $block->getRawOriginal('title') === 'Starter media preview') {
                    $block->imageTranslations()->updateOrCreate(
                        ['locale_id' => $locale->id],
                        [
                            'caption' => 'Baslangic medya onizlemesi',
                            'alt_text' => 'Kutuphanelerinizden kendi gorsellerinizle degistirebileceginiz medya blogu.',
                        ],
                    );

                    return;
                }

                if ($family === 'contact_form' && $block->getRawOriginal('title') === 'Contact us') {
                    $block->contactFormTranslations()->updateOrCreate(
                        ['locale_id' => $locale->id],
                        [
                            'title' => 'Bize ulasin',
                            'content' => 'Planladiginiz isi bize anlatin, mesajinizi dogru editor veya uygulama sorumlusuna yonlendirelim.',
                            'submit_label' => 'Mesaj gonder',
                            'success_message' => 'Mesajiniz icin tesekkurler. En kisa surede size donus yapacagiz.',
                        ],
                    );
                }
            });
    }

    private function seedNavigation(Page $homePage, Page $aboutPage, Page $contactPage): void
    {
        $primaryItems = [
            ['menu_key' => 'primary', 'title' => 'Home', 'link_type' => 'page', 'page_id' => $homePage->id, 'position' => 1, 'visibility' => 'visible'],
            ['menu_key' => 'primary', 'title' => 'About', 'link_type' => 'page', 'page_id' => $aboutPage->id, 'position' => 2, 'visibility' => 'visible'],
            ['menu_key' => 'primary', 'title' => 'Contact', 'link_type' => 'page', 'page_id' => $contactPage->id, 'position' => 3, 'visibility' => 'visible'],
        ];

        foreach ($primaryItems as $item) {
            NavigationItem::query()->updateOrCreate(
                ['menu_key' => $item['menu_key'], 'title' => $item['title'], 'parent_id' => null],
                $item + ['url' => null, 'target' => null],
            );
        }

        $footerItems = [
            ['menu_key' => 'footer', 'title' => 'About', 'link_type' => 'page', 'page_id' => $aboutPage->id, 'position' => 1, 'visibility' => 'visible'],
            ['menu_key' => 'footer', 'title' => 'Contact', 'link_type' => 'page', 'page_id' => $contactPage->id, 'position' => 2, 'visibility' => 'visible'],
        ];

        foreach ($footerItems as $item) {
            NavigationItem::query()->updateOrCreate(
                ['menu_key' => $item['menu_key'], 'title' => $item['title'], 'parent_id' => null],
                $item + ['url' => null, 'target' => null],
            );
        }
    }

    private function upsertBlock(Page $page, ?BlockType $blockType, int $slotTypeId, array $attributes, ?BlockType $fallbackBlockType = null): ?Block
    {
        $resolvedBlockType = $blockType ?? $fallbackBlockType;

        if (! $resolvedBlockType) {
            return null;
        }

        $identity = [
            'page_id' => $page->id,
            'slot_type_id' => $slotTypeId,
            'parent_id' => Arr::get($attributes, 'parent_id'),
            'sort_order' => $attributes['sort_order'] ?? 0,
        ];

        $payload = array_merge($attributes, [
            'page_id' => $page->id,
            'block_type_id' => $resolvedBlockType->id,
            'type' => $resolvedBlockType->slug,
            'source_type' => $resolvedBlockType->source_type ?? 'static',
            'slot_type_id' => $slotTypeId,
            'slot' => SlotType::query()->whereKey($slotTypeId)->value('slug') ?? 'main',
            'status' => $attributes['status'] ?? 'published',
            'is_system' => $attributes['is_system'] ?? $resolvedBlockType->is_system,
        ]);

        return Block::query()->updateOrCreate($identity, $payload);
    }
}
