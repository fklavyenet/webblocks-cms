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
use App\Support\Blocks\BlockTranslationWriter;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;

class StarterContentSeeder extends Seeder
{
    public function __construct(private readonly BlockTranslationWriter $blockTranslationWriter) {}

    public function run(): void
    {
        throw new \RuntimeException('StarterContentSeeder is quarantined while the CMS foundation is limited to header and plain_text blocks. Rebuild starter demo content deliberately before re-enabling this seeder.');
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
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');

        Block::query()
            ->whereIn('page_id', [$homePage->id, $aboutPage->id, $contactPage->id])
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->get()
            ->each(fn (Block $block) => $this->blockTranslationWriter->normalizeCanonicalStorage($block));

        Block::query()
            ->whereIn('page_id', [$homePage->id, $aboutPage->id, $contactPage->id])
            ->with(['textTranslations', 'buttonTranslations', 'imageTranslations', 'contactFormTranslations'])
            ->get()
            ->each(function (Block $block) use ($locale, $registry, $defaultLocaleId): void {
                $family = $registry->familyFor($block);
                $defaultTextTranslation = $block->textTranslations->firstWhere('locale_id', $defaultLocaleId);
                $defaultButtonTranslation = $block->buttonTranslations->firstWhere('locale_id', $defaultLocaleId);
                $defaultImageTranslation = $block->imageTranslations->firstWhere('locale_id', $defaultLocaleId);
                $defaultContactTranslation = $block->contactFormTranslations->firstWhere('locale_id', $defaultLocaleId);

                if (! $family) {
                    return;
                }

                if ($family === 'text') {
                    $payload = match ($defaultTextTranslation?->title) {
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
                    $payload = match ($defaultButtonTranslation?->title) {
                        'Get Started' => ['title' => 'Baslayin'],
                        default => null,
                    };

                    if ($payload) {
                        $block->buttonTranslations()->updateOrCreate(['locale_id' => $locale->id], $payload);
                    }

                    return;
                }

                if ($family === 'image' && $defaultImageTranslation?->caption === 'Starter media preview') {
                    $block->imageTranslations()->updateOrCreate(
                        ['locale_id' => $locale->id],
                        [
                            'caption' => 'Baslangic medya onizlemesi',
                            'alt_text' => 'Kutuphanelerinizden kendi gorsellerinizle degistirebileceginiz medya blogu.',
                        ],
                    );

                    return;
                }

                if ($family === 'contact_form' && $defaultContactTranslation?->title === 'Contact us') {
                    $block->contactFormTranslations()->updateOrCreate(
                        ['locale_id' => $defaultLocaleId],
                        [
                            'title' => 'Contact us',
                            'content' => 'Tell us what you are planning and we will route your message to the right editorial or implementation contact.',
                            'submit_label' => 'Send message',
                            'success_message' => 'Thanks for your message. We will get back to you soon.',
                        ],
                    );

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
        $siteId = $homePage->site_id;
        $primaryItems = [
            ['site_id' => $siteId, 'menu_key' => 'primary', 'title' => 'Home', 'link_type' => 'page', 'page_id' => $homePage->id, 'position' => 1, 'visibility' => 'visible'],
            ['site_id' => $siteId, 'menu_key' => 'primary', 'title' => 'About', 'link_type' => 'page', 'page_id' => $aboutPage->id, 'position' => 2, 'visibility' => 'visible'],
            ['site_id' => $siteId, 'menu_key' => 'primary', 'title' => 'Contact', 'link_type' => 'page', 'page_id' => $contactPage->id, 'position' => 3, 'visibility' => 'visible'],
        ];

        foreach ($primaryItems as $item) {
            NavigationItem::query()->updateOrCreate(
                ['site_id' => $item['site_id'], 'menu_key' => $item['menu_key'], 'title' => $item['title'], 'parent_id' => null],
                $item + ['url' => null, 'target' => null],
            );
        }

        $footerItems = [
            ['site_id' => $siteId, 'menu_key' => 'footer', 'title' => 'About', 'link_type' => 'page', 'page_id' => $aboutPage->id, 'position' => 1, 'visibility' => 'visible'],
            ['site_id' => $siteId, 'menu_key' => 'footer', 'title' => 'Contact', 'link_type' => 'page', 'page_id' => $contactPage->id, 'position' => 2, 'visibility' => 'visible'],
        ];

        foreach ($footerItems as $item) {
            NavigationItem::query()->updateOrCreate(
                ['site_id' => $item['site_id'], 'menu_key' => $item['menu_key'], 'title' => $item['title'], 'parent_id' => null],
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

    private function upsertPage(Site $site, string $slug, string $title): Page
    {
        $page = Page::query()
            ->where('site_id', $site->id)
            ->whereHas('translations', fn ($query) => $query
                ->where('locale_id', Locale::query()->where('is_default', true)->value('id'))
                ->where('slug', $slug))
            ->first();

        if (! $page) {
            $page = Page::query()->create([
                'site_id' => $site->id,
                'title' => $title,
                'slug' => $slug,
                'page_type' => 'default',
                'status' => 'published',
            ]);
        }

        $page->update([
            'title' => $title,
            'slug' => $slug,
            'page_type' => 'default',
            'status' => 'published',
        ]);

        return $page->fresh();
    }
}
