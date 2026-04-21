<?php

namespace Database\Seeders;

use App\Models\Block;
use App\Models\BlockButtonTranslation;
use App\Models\BlockContactFormTranslation;
use App\Models\BlockTextTranslation;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\Site;
use App\Models\SlotType;
use Illuminate\Database\Seeder;

class WebBlocksLandingContentSeeder extends Seeder
{
    private array $blockTypes = [];

    private array $slotTypes = [];

    public function run(): void
    {
        $this->blockTypes = BlockType::query()->get()->keyBy('slug')->all();
        $this->slotTypes = SlotType::query()->get()->keyBy('slug')->all();

        $english = Locale::query()->where('code', 'en')->firstOrFail();
        $turkish = Locale::query()->where('code', 'tr')->firstOrFail();

        $rootHome = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'root'))->where('slug', 'home')->firstOrFail();
        $rootAbout = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'root'))->where('slug', 'about')->firstOrFail();
        $rootContact = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'root'))->where('slug', 'contact')->firstOrFail();
        $uiHome = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'ui'))->where('slug', 'home')->firstOrFail();
        $uiDocsHome = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'ui-docs'))->where('slug', 'home')->firstOrFail();
        $cmsHome = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'cms'))->where('slug', 'home')->firstOrFail();
        $cmsDocsHome = Page::query()->whereHas('site', fn ($query) => $query->where('handle', 'cms-docs'))->where('slug', 'home')->firstOrFail();

        $this->seedRootHome($rootHome, $english, $turkish);
        $this->seedRootAbout($rootAbout, $english, $turkish);
        $this->seedRootContact($rootContact, $english, $turkish);
        $this->seedUiHome($uiHome, $english, $turkish);
        $this->seedUiDocsHome($uiDocsHome, $english, $turkish);
        $this->seedCmsHome($cmsHome, $english, $turkish);
        $this->seedCmsDocsHome($cmsDocsHome, $english, $turkish);
    }

    private function seedRootHome(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);

        $this->brandHeader($page, $header, 'WebBlocks', 'A calm ecosystem for interfaces, content, and future product sites.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Arayuzler, icerik ve gelecekteki urun siteleri icin sakin bir ekosistem.'],
        ]);

        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Open admin', '/admin');

        $hero = $this->block($page, 'section', $main, 0, [
            'title' => 'WebBlocks ecosystem',
            'content' => 'A family of tools for product teams that want calm systems, reusable UI, and CMS-driven publishing. This foundation starts with the ecosystem landing plus dedicated UI and CMS product sites, ready for real multisite and multilingual growth.',
            'variant' => 'accent',
        ]);
        $this->textTranslation($hero, $english, 'WebBlocks ecosystem', null, 'A family of tools for product teams that want calm systems, reusable UI, and CMS-driven publishing. This foundation starts with the ecosystem landing plus dedicated UI and CMS product sites, ready for real multisite and multilingual growth.');
        $this->textTranslation($hero, $turkish, 'WebBlocks ekosistemi', null, 'Tekrar kullanilabilir UI, CMS odakli yayinlama ve sakin sistemler isteyen urun ekipleri icin urun ailesi. Bu temel kurulum, gercek multisite ve cok dilli buyume icin hazir ekosistem girisi ile UI ve CMS urun sitelerini bir araya getirir.');

        $intro = $this->block($page, 'section', $main, 1, [
            'title' => 'One ecosystem, distinct products',
            'content' => 'WebBlocks keeps products separate where needed and aligned where it matters: design language, editorial structure, and host-aware routing.',
            'variant' => 'muted',
        ]);
        $this->textTranslation($intro, $english, 'One ecosystem, distinct products', null, 'WebBlocks keeps products separate where needed and aligned where it matters: design language, editorial structure, and host-aware routing.');
        $this->textTranslation($intro, $turkish, 'Tek ekosistem, net urunler', null, 'WebBlocks urunleri gereken yerde ayirir, onemli yerde hizalar: tasarim dili, editor yapisi ve host farkinda yonlendirme.');

        $products = $this->block($page, 'columns', $main, 2, [
            'title' => 'Product foundations',
            'subtitle' => 'The first public layer of the platform.',
            'content' => 'Each landing page is a real site in CMS data, not a path-only simulation.',
        ]);
        $this->textTranslation($products, $english, 'Product foundations', 'The first public layer of the platform.', 'Each landing page is a real site in CMS data, not a path-only simulation.');
        $this->textTranslation($products, $turkish, 'Urun temelleri', 'Platformun ilk kamusal katmani.', 'Her acilis sayfasi yalnizca yol simulasyonu degil, CMS verisinde gercek bir sitedir.');
        $uiCard = $this->block($page, 'column_item', $main, 0, [
            'parent_id' => $products->id,
            'title' => 'WebBlocks UI',
            'content' => 'Design system patterns, composable building blocks, and a consistent visual grammar for products and sites.',
            'url' => 'https://ui.webblocksui.com.ddev.site/',
        ]);
        $this->textTranslation($uiCard, $english, 'WebBlocks UI', null, 'Design system patterns, composable building blocks, and a consistent visual grammar for products and sites.');
        $this->textTranslation($uiCard, $turkish, 'WebBlocks UI', null, 'Tasarim sistemi desenleri, birlesebilir yapi taslari ve urunler ile siteler icin tutarli bir gorsel dil.');
        $cmsCard = $this->block($page, 'column_item', $main, 1, [
            'parent_id' => $products->id,
            'title' => 'WebBlocks CMS',
            'content' => 'A block-based CMS with multisite, multilingual content, and editorial structures that stay close to the product model.',
            'url' => 'https://cms.webblocksui.com.ddev.site/',
        ]);
        $this->textTranslation($cmsCard, $english, 'WebBlocks CMS', null, 'A block-based CMS with multisite, multilingual content, and editorial structures that stay close to the product model.');
        $this->textTranslation($cmsCard, $turkish, 'WebBlocks CMS', null, 'Multisite, cok dilli icerik ve urun modeline yakin editor yapilari sunan blok tabanli CMS.');

        $why = $this->block($page, 'rich-text', $main, 3, [
            'content' => "Why this ecosystem\n\nIt lets product, design, and editorial work share the same core language. The UI system shapes presentation. The CMS shapes structure. Multisite keeps product boundaries clear from the start.",
        ]);
        $this->textTranslation($why, $english, null, null, "Why this ecosystem\n\nIt lets product, design, and editorial work share the same core language. The UI system shapes presentation. The CMS shapes structure. Multisite keeps product boundaries clear from the start.");
        $this->textTranslation($why, $turkish, null, null, "Neden bu ekosistem\n\nUrun, tasarim ve editor ekiplerinin ayni cekirdek dili paylasmasini saglar. UI sistemi sunumu bicimlendirir. CMS yapisal katmani kurar. Multisite ise urun sinirlarini en bastan netlestirir.");

        $cta = $this->block($page, 'section', $main, 4, [
            'title' => 'Start with the product sites',
            'content' => 'Explore the UI and CMS landings, then grow this install into docs, showcases, and editorial surfaces.',
        ]);
        $this->textTranslation($cta, $english, 'Start with the product sites', null, 'Explore the UI and CMS landings, then grow this install into docs, showcases, and editorial surfaces.');
        $this->textTranslation($cta, $turkish, 'Urun siteleriyle baslayin', null, 'UI ve CMS acilis sayfalarini inceleyin, sonra bu kurulumu dokumantasyon, showcase ve editor yuzeylerine genisletin.');
        $rootButton = $this->block($page, 'button', $main, 0, [
            'parent_id' => $cta->id,
            'title' => 'Visit WebBlocks UI',
            'url' => 'https://ui.webblocksui.com.ddev.site/',
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);
        $this->buttonTranslation($rootButton, $english, 'Visit WebBlocks UI');
        $this->buttonTranslation($rootButton, $turkish, 'WebBlocks UI sitesine git');

        $this->footerBrand($page, $footer, 'WebBlocks', 'A product ecosystem built from reusable UI and CMS structure.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Tekrar kullanilabilir UI ve CMS yapisindan kurulan urun ekosistemi.'],
        ]);
    }

    private function seedRootAbout(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks', 'Shared foundations for product interfaces and publishing.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Urun arayuzleri ve yayinlama icin ortak temeller.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Admin', '/admin');

        $about = $this->block($page, 'section', $main, 0, [
            'title' => 'About WebBlocks',
            'content' => 'WebBlocks brings interface systems and block-based publishing into one calm product model. This sandbox is the first step toward the public webblocksui.com family.',
        ]);
        $this->textTranslation($about, $english, 'About WebBlocks', null, 'WebBlocks brings interface systems and block-based publishing into one calm product model. This sandbox is the first step toward the public webblocksui.com family.');
        $this->textTranslation($about, $turkish, 'WebBlocks hakkinda', null, 'WebBlocks arayuz sistemleri ile blok tabanli yayinlamayi tek ve sakin bir urun modelinde bir araya getirir. Bu sandbox, kamuya acik webblocksui.com ailesine giden ilk adimdir.');

        $this->footerBrand($page, $footer, 'WebBlocks', 'Foundation pages for the ecosystem root site.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Ekosistem kok sitesi icin temel sayfalar.'],
        ]);
    }

    private function seedRootContact(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks', 'Contact and editorial routing for the ecosystem site.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Ekosistem sitesi icin iletisim ve editor yonlendirmesi.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Admin', '/admin');

        $intro = $this->block($page, 'section', $main, 0, [
            'title' => 'Contact WebBlocks',
            'content' => 'Use this page as the ecosystem-level contact entry while product docs and deeper editorial spaces are added in the next phase.',
        ]);
        $this->textTranslation($intro, $english, 'Contact WebBlocks', null, 'Use this page as the ecosystem-level contact entry while product docs and deeper editorial spaces are added in the next phase.');
        $this->textTranslation($intro, $turkish, 'WebBlocks iletisimi', null, 'Urun dokumantasyonu ve daha derin editor alanlari sonraki fazda eklenirken bu sayfayi ekosistem duzeyi iletisim girisi olarak kullanin.');

        $form = $this->block($page, 'contact_form', $main, 1, [
            'title' => 'Send a message',
            'content' => 'Reach the WebBlocks team with product, partnership, or implementation questions.',
            'settings' => json_encode([
                'submit_label' => 'Send message',
                'success_message' => 'Your message has been received.',
            ], JSON_UNESCAPED_SLASHES),
        ]);
        $this->contactTranslation($form, $english, 'Send a message', 'Reach the WebBlocks team with product, partnership, or implementation questions.', 'Send message', 'Your message has been received.');
        $this->contactTranslation($form, $turkish, 'Mesaj gonderin', 'Urun, is birligi veya uygulama sorulariniz icin WebBlocks ekibine ulasin.', 'Mesaji gonder', 'Mesajiniz alindi.');

        $this->footerBrand($page, $footer, 'WebBlocks', 'Editorial contact entry for the ecosystem root.', [
            'tr' => ['title' => 'WebBlocks', 'content' => 'Ekosistem koku icin editor iletisim girisi.'],
        ]);
    }

    private function seedUiHome(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks UI', 'Design-system patterns and interface primitives for the ecosystem.', [
            'tr' => ['title' => 'WebBlocks UI', 'content' => 'Ekosistem icin tasarim sistemi desenleri ve arayuz primitivelari.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'CMS admin', 'https://webblocksui.com.ddev.site/admin');

        $hero = $this->block($page, 'section', $main, 0, [
            'title' => 'WebBlocks UI',
            'content' => 'A calm interface system for products that need reusable structure without visual noise. It gives WebBlocks products a shared pattern language across shells, landing pages, editorial sections, and future docs surfaces.',
            'variant' => 'accent',
        ]);
        $this->textTranslation($hero, $english, 'WebBlocks UI', null, 'A calm interface system for products that need reusable structure without visual noise. It gives WebBlocks products a shared pattern language across shells, landing pages, editorial sections, and future docs surfaces.');
        $this->textTranslation($hero, $turkish, 'WebBlocks UI', null, 'Gorsel gurultu olmadan tekrar kullanilabilir yapi isteyen urunler icin sakin arayuz sistemi. Shell, acilis sayfasi, editor bolumleri ve gelecekteki dokumantasyon yuzeyleri boyunca WebBlocks urunlerine ortak desen dili saglar.');

        $principles = $this->block($page, 'columns', $main, 1, [
            'title' => 'Principles',
            'subtitle' => 'What shapes the system.',
            'content' => 'UI is treated as a product language: composable, consistent, and practical for editorial use too.',
        ]);
        $this->textTranslation($principles, $english, 'Principles', 'What shapes the system.', 'UI is treated as a product language: composable, consistent, and practical for editorial use too.');
        $this->textTranslation($principles, $turkish, 'Ilkeler', 'Sistemi bicimlendiren seyler.', 'UI, bir urun dili olarak ele alinir: birlesebilir, tutarli ve editor kullanimina da uygun.');
        foreach ([
            0 => ['Clear primitives', 'Base components stay simple and reusable.', 'Temel primitivelar', 'Temel bilesenler sade ve tekrar kullanilabilir kalir.'],
            1 => ['Pattern rhythm', 'Spacing, hierarchy, and affordances stay intentional.', 'Desen ritmi', 'Bosluk, hiyerarsi ve etkilesim ipuclari bilincli kalir.'],
            2 => ['System reuse', 'The same language can serve apps, sites, and docs.', 'Sistem tekrar kullanimi', 'Ayni dil uygulama, site ve dokumantasyon yuzeylerinde calisir.'],
        ] as $index => [$enTitle, $enContent, $trTitle, $trContent]) {
            $item = $this->block($page, 'column_item', $main, $index, [
                'parent_id' => $principles->id,
                'title' => $enTitle,
                'content' => $enContent,
            ]);
            $this->textTranslation($item, $english, $enTitle, null, $enContent);
            $this->textTranslation($item, $turkish, $trTitle, null, $trContent);
        }

        $system = $this->block($page, 'rich-text', $main, 2, [
            'content' => "Pattern system\n\nWebBlocks UI supports public shells, cards, banners, forms, navigation, and editorial composition. It is meant to feel stable enough for products and flexible enough for growth.",
        ]);
        $this->textTranslation($system, $english, null, null, "Pattern system\n\nWebBlocks UI supports public shells, cards, banners, forms, navigation, and editorial composition. It is meant to feel stable enough for products and flexible enough for growth.");
        $this->textTranslation($system, $turkish, null, null, "Desen sistemi\n\nWebBlocks UI kamusal shell, kart, banner, form, navigasyon ve editor kompozisyonunu destekler. Urunler icin yeterince istikrarli, buyume icin yeterince esnek olmasi hedeflenir.");

        $cta = $this->block($page, 'section', $main, 3, [
            'title' => 'Docs next phase',
            'content' => 'The docs site will come later. This phase establishes the product story and the host-aware site boundary first.',
        ]);
        $this->textTranslation($cta, $english, 'Docs next phase', null, 'The docs site will come later. This phase establishes the product story and the host-aware site boundary first.');
        $this->textTranslation($cta, $turkish, 'Dokumantasyon sonraki fazda', null, 'Dokumantasyon sitesi daha sonra gelecek. Bu faz once urun hikayesini ve host farkinda site sinirini kurar.');
        $button = $this->block($page, 'button', $main, 0, [
            'parent_id' => $cta->id,
            'title' => 'Back to ecosystem',
            'url' => 'https://webblocksui.com.ddev.site/',
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);
        $this->buttonTranslation($button, $english, 'Back to ecosystem');
        $this->buttonTranslation($button, $turkish, 'Ekosisteme don');

        $this->footerBrand($page, $footer, 'WebBlocks UI', 'Reusable interface language for the WebBlocks family.', [
            'tr' => ['title' => 'WebBlocks UI', 'content' => 'WebBlocks ailesi icin tekrar kullanilabilir arayuz dili.'],
        ]);
    }

    private function seedCmsHome(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks CMS', 'Block-based publishing with multisite and multilingual structure in core.', [
            'tr' => ['title' => 'WebBlocks CMS', 'content' => 'Cekirdekte multisite ve cok dilli yapi ile blok tabanli yayinlama.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Open admin', 'https://webblocksui.com.ddev.site/admin');

        $hero = $this->block($page, 'section', $main, 0, [
            'title' => 'WebBlocks CMS',
            'content' => 'A CMS that keeps structure in data: pages, slots, blocks, sites, locales, and host-aware public routing. It is built for product teams that need calm editorial workflows, reusable page systems, and a foundation that grows from one site to many.',
            'variant' => 'accent',
        ]);
        $this->textTranslation($hero, $english, 'WebBlocks CMS', null, 'A CMS that keeps structure in data: pages, slots, blocks, sites, locales, and host-aware public routing. It is built for product teams that need calm editorial workflows, reusable page systems, and a foundation that grows from one site to many.');
        $this->textTranslation($hero, $turkish, 'WebBlocks CMS', null, 'Yapiyi veride tutan bir CMS: sayfalar, slotlar, bloklar, siteler, locale kayitlari ve host farkinda kamusal yonlendirme. Sakin editor akislari, tekrar kullanilabilir sayfa sistemleri ve tek siteden coka buyuyen bir temel isteyen urun ekipleri icin uretildi.');

        $capabilities = $this->block($page, 'columns', $main, 1, [
            'title' => 'Key capabilities',
            'subtitle' => 'What the product does today.',
            'content' => 'The system is already using real multisite and multilingual data rather than a parallel demo layer.',
        ]);
        $this->textTranslation($capabilities, $english, 'Key capabilities', 'What the product does today.', 'The system is already using real multisite and multilingual data rather than a parallel demo layer.');
        $this->textTranslation($capabilities, $turkish, 'Temel yetenekler', 'Urunun bugun yaptiklari.', 'Sistem zaten paralel bir demo katmani yerine gercek multisite ve cok dilli veri kullaniyor.');
        foreach ([
            0 => ['Block-based pages', 'Compose public pages from slots and reusable blocks.', 'Blok tabanli sayfalar', 'Kamusal sayfalari slotlar ve tekrar kullanilabilir bloklardan olusturun.'],
            1 => ['Multisite core', 'Resolve distinct sites by host and keep content boundaries explicit.', 'Multisite cekirdek', 'Ayri siteleri host ile cozer ve icerik sinirlarini acik tutar.'],
            2 => ['Multilingual routing', 'Keep English prefixless and add locale prefixes only when needed.', 'Cok dilli yonlendirme', 'Ingilizceyi prefixesiz tutar, locale on eklerini yalnizca gerektiginde ekler.'],
        ] as $index => [$enTitle, $enContent, $trTitle, $trContent]) {
            $item = $this->block($page, 'column_item', $main, $index, [
                'parent_id' => $capabilities->id,
                'title' => $enTitle,
                'content' => $enContent,
            ]);
            $this->textTranslation($item, $english, $enTitle, null, $enContent);
            $this->textTranslation($item, $turkish, $trTitle, null, $trContent);
        }

        $strengths = $this->block($page, 'rich-text', $main, 2, [
            'content' => "Why it fits the ecosystem\n\nWebBlocks CMS keeps public presentation aligned with WebBlocks UI while leaving page content, structure, translation, and site resolution in the database. That makes product growth cleaner over time.",
        ]);
        $this->textTranslation($strengths, $english, null, null, "Why it fits the ecosystem\n\nWebBlocks CMS keeps public presentation aligned with WebBlocks UI while leaving page content, structure, translation, and site resolution in the database. That makes product growth cleaner over time.");
        $this->textTranslation($strengths, $turkish, null, null, "Neden ekosisteme uyuyor\n\nWebBlocks CMS kamusal sunumu WebBlocks UI ile hizali tutarken sayfa icerigi, yapi, ceviri ve site cozumlemesini veritabaninda birakir. Bu da urun buyumesini zamanla daha temiz hale getirir.");

        $cta = $this->block($page, 'section', $main, 3, [
            'title' => 'Use admin as the next entry point',
            'content' => 'From here, editors can manage sites, locales, pages, and translated block content from one CMS install.',
        ]);
        $this->textTranslation($cta, $english, 'Use admin as the next entry point', null, 'From here, editors can manage sites, locales, pages, and translated block content from one CMS install.');
        $this->textTranslation($cta, $turkish, 'Siradaki giris noktasi admin olsun', null, 'Buradan editorler tek bir CMS kurulumundan siteleri, locale kayitlarini, sayfalari ve cevrilmis blok icerigini yonetebilir.');
        $button = $this->block($page, 'button', $main, 0, [
            'parent_id' => $cta->id,
            'title' => 'Open admin',
            'url' => 'https://webblocksui.com.ddev.site/admin',
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);
        $this->buttonTranslation($button, $english, 'Open admin');
        $this->buttonTranslation($button, $turkish, 'Admini ac');

        $this->footerBrand($page, $footer, 'WebBlocks CMS', 'Structured editorial foundation for the ecosystem.', [
            'tr' => ['title' => 'WebBlocks CMS', 'content' => 'Ekosistem icin yapisal editor temeli.'],
        ]);
    }

    private function seedUiDocsHome(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks UI Docs', 'Documentation home for UI patterns, primitives, and integration guidance.', [
            'tr' => ['title' => 'WebBlocks UI Dokumantasyon', 'content' => 'UI desenleri, primitivelar ve entegrasyon rehberi icin dokumantasyon anasayfasi.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Back to UI site', 'https://ui.webblocksui.com.ddev.site/');

        $hero = $this->block($page, 'section', $main, 0, [
            'title' => 'WebBlocks UI documentation',
            'content' => 'This is the documentation home for WebBlocks UI. It sets the first docs boundary for patterns, primitives, integration notes, and future JavaScript guidance.',
            'variant' => 'accent',
        ]);
        $this->textTranslation($hero, $english, 'WebBlocks UI documentation', null, 'This is the documentation home for WebBlocks UI. It sets the first docs boundary for patterns, primitives, integration notes, and future JavaScript guidance.');
        $this->textTranslation($hero, $turkish, 'WebBlocks UI dokumantasyonu', null, 'Bu alan WebBlocks UI icin dokumantasyon anasayfasidir. Desenler, primitivelar, entegrasyon notlari ve gelecekteki JavaScript rehberi icin ilk dokumantasyon sinirini kurar.');

        $topics = $this->block($page, 'columns', $main, 1, [
            'title' => 'Docs topics',
            'subtitle' => 'What this docs site will grow into.',
            'content' => 'Start with high-level orientation now, then expand into full reference structure in the next phase.',
        ]);
        $this->textTranslation($topics, $english, 'Docs topics', 'What this docs site will grow into.', 'Start with high-level orientation now, then expand into full reference structure in the next phase.');
        $this->textTranslation($topics, $turkish, 'Dokumantasyon basliklari', 'Bu dokumantasyon sitesinin buyuyecegi alanlar.', 'Simdilik yuksek seviyeli yonlendirme ile baslayin, sonraki fazda tam referans yapisina genisletin.');
        foreach ([
            0 => ['Patterns', 'Reusable page and shell patterns for product surfaces.', 'Desenler', 'Urun yuzeyleri icin tekrar kullanilabilir sayfa ve shell desenleri.'],
            1 => ['Primitives', 'Base building blocks that keep the UI language consistent.', 'Primitiveler', 'UI dilini tutarli tutan temel yapi taslari.'],
            2 => ['Integration', 'How UI composition fits Laravel, Blade, and CMS surfaces.', 'Entegrasyon', 'UI kompozisyonunun Laravel, Blade ve CMS yuzeyleriyle nasil uyumlu calistigi.'],
            3 => ['JavaScript direction', 'Progressive enhancement and future interactive guidance.', 'JavaScript yonu', 'Asamali gelistirme ve gelecekteki etkilesim rehberi.'],
        ] as $index => [$enTitle, $enContent, $trTitle, $trContent]) {
            $item = $this->block($page, 'column_item', $main, $index, [
                'parent_id' => $topics->id,
                'title' => $enTitle,
                'content' => $enContent,
            ]);
            $this->textTranslation($item, $english, $enTitle, null, $enContent);
            $this->textTranslation($item, $turkish, $trTitle, null, $trContent);
        }

        $cta = $this->block($page, 'section', $main, 2, [
            'title' => 'Docs structure comes next',
            'content' => 'This phase creates the real docs site and host boundary. The next phase can add categories, side navigation, and deeper guides.',
        ]);
        $this->textTranslation($cta, $english, 'Docs structure comes next', null, 'This phase creates the real docs site and host boundary. The next phase can add categories, side navigation, and deeper guides.');
        $this->textTranslation($cta, $turkish, 'Dokumantasyon yapisi sonraki adimda', null, 'Bu faz gercek dokumantasyon sitesini ve host sinirini kurar. Sonraki faz kategoriler, yan navigasyon ve daha derin rehberler ekleyebilir.');
        $button = $this->block($page, 'button', $main, 0, [
            'parent_id' => $cta->id,
            'title' => 'Open UI landing',
            'url' => 'https://ui.webblocksui.com.ddev.site/',
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);
        $this->buttonTranslation($button, $english, 'Open UI landing');
        $this->buttonTranslation($button, $turkish, 'UI acilis sayfasini ac');

        $this->footerBrand($page, $footer, 'WebBlocks UI Docs', 'The first documentation layer for WebBlocks UI.', [
            'tr' => ['title' => 'WebBlocks UI Dokumantasyon', 'content' => 'WebBlocks UI icin ilk dokumantasyon katmani.'],
        ]);
    }

    private function seedCmsDocsHome(Page $page, Locale $english, Locale $turkish): void
    {
        [$header, $main, $footer] = $this->standardSlots($page);
        $this->brandHeader($page, $header, 'WebBlocks CMS Docs', 'Documentation home for install, multisite, multilingual, and editorial system guidance.', [
            'tr' => ['title' => 'WebBlocks CMS Dokumantasyon', 'content' => 'Kurulum, multisite, cok dilli ve editor sistem rehberi icin dokumantasyon anasayfasi.'],
        ]);
        $this->navigationBlocks($page, $header, $footer);
        $this->headerAction($page, $header, 'Back to CMS site', 'https://cms.webblocksui.com.ddev.site/');

        $hero = $this->block($page, 'section', $main, 0, [
            'title' => 'WebBlocks CMS documentation',
            'content' => 'This is the documentation home for WebBlocks CMS. It establishes the docs site for install guidance, multisite, multilingual setup, block modeling, media, navigation, and update workflows.',
            'variant' => 'accent',
        ]);
        $this->textTranslation($hero, $english, 'WebBlocks CMS documentation', null, 'This is the documentation home for WebBlocks CMS. It establishes the docs site for install guidance, multisite, multilingual setup, block modeling, media, navigation, and update workflows.');
        $this->textTranslation($hero, $turkish, 'WebBlocks CMS dokumantasyonu', null, 'Bu alan WebBlocks CMS icin dokumantasyon anasayfasidir. Kurulum rehberi, multisite, cok dilli kurulum, blok modelleme, medya, navigasyon ve guncelleme akislarina ayrilan dokumantasyon sitesini kurar.');

        $topics = $this->block($page, 'columns', $main, 1, [
            'title' => 'Docs topics',
            'subtitle' => 'The first CMS documentation map.',
            'content' => 'This docs site starts as a clear landing page and can grow into a deeper reference system next.',
        ]);
        $this->textTranslation($topics, $english, 'Docs topics', 'The first CMS documentation map.', 'This docs site starts as a clear landing page and can grow into a deeper reference system next.');
        $this->textTranslation($topics, $turkish, 'Dokumantasyon basliklari', 'Ilk CMS dokumantasyon haritasi.', 'Bu dokumantasyon sitesi net bir acilis sayfasi olarak baslar ve sonra daha derin bir referans sistemine buyuyebilir.');
        foreach ([
            0 => ['Install and upgrade', 'Environment setup, install flow, and update direction.', 'Kurulum ve guncelleme', 'Ortam kurulumu, kurulum akisi ve guncelleme yonu.'],
            1 => ['Multisite and multilingual', 'Hosts, locales, translations, and site boundaries.', 'Multisite ve cok dilli', 'Hostlar, locale kayitlari, ceviriler ve site sinirlari.'],
            2 => ['Blocks and layouts', 'Pages, slots, blocks, navigation, and editorial structure.', 'Bloklar ve layoutlar', 'Sayfalar, slotlar, bloklar, navigasyon ve editor yapisi.'],
            3 => ['Media and system tools', 'Media flows, backups, and update system surfaces.', 'Medya ve sistem araclari', 'Medya akislar, yedekler ve guncelleme sistemi yuzeyleri.'],
        ] as $index => [$enTitle, $enContent, $trTitle, $trContent]) {
            $item = $this->block($page, 'column_item', $main, $index, [
                'parent_id' => $topics->id,
                'title' => $enTitle,
                'content' => $enContent,
            ]);
            $this->textTranslation($item, $english, $enTitle, null, $enContent);
            $this->textTranslation($item, $turkish, $trTitle, null, $trContent);
        }

        $cta = $this->block($page, 'section', $main, 2, [
            'title' => 'Deeper docs are the next phase',
            'content' => 'This phase creates the real docs site and multilingual host boundary. The next phase can add structured sections, navigation depth, and operational guides.',
        ]);
        $this->textTranslation($cta, $english, 'Deeper docs are the next phase', null, 'This phase creates the real docs site and multilingual host boundary. The next phase can add structured sections, navigation depth, and operational guides.');
        $this->textTranslation($cta, $turkish, 'Daha derin dokumantasyon sonraki fazda', null, 'Bu faz gercek dokumantasyon sitesini ve cok dilli host sinirini kurar. Sonraki faz yapisal bolumler, daha derin navigasyon ve operasyon rehberleri ekleyebilir.');
        $button = $this->block($page, 'button', $main, 0, [
            'parent_id' => $cta->id,
            'title' => 'Open CMS landing',
            'url' => 'https://cms.webblocksui.com.ddev.site/',
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);
        $this->buttonTranslation($button, $english, 'Open CMS landing');
        $this->buttonTranslation($button, $turkish, 'CMS acilis sayfasini ac');

        $this->footerBrand($page, $footer, 'WebBlocks CMS Docs', 'The first documentation layer for WebBlocks CMS.', [
            'tr' => ['title' => 'WebBlocks CMS Dokumantasyon', 'content' => 'WebBlocks CMS icin ilk dokumantasyon katmani.'],
        ]);
    }

    private function standardSlots(Page $page): array
    {
        return [
            $this->slot($page, 'header', 0),
            $this->slot($page, 'main', 1),
            $this->slot($page, 'footer', 2),
        ];
    }

    private function slot(Page $page, string $slug, int $sortOrder): PageSlot
    {
        return PageSlot::query()->create([
            'page_id' => $page->id,
            'slot_type_id' => $this->slotTypes[$slug]->id,
            'sort_order' => $sortOrder,
        ]);
    }

    private function block(Page $page, string $slug, PageSlot $slot, int $sortOrder, array $attributes = []): Block
    {
        $blockType = $this->blockTypes[$slug];

        return Block::query()->create(array_merge([
            'page_id' => $page->id,
            'parent_id' => null,
            'type' => $blockType->slug,
            'block_type_id' => $blockType->id,
            'source_type' => $blockType->source_type,
            'slot' => $slot->slotType?->slug,
            'slot_type_id' => $slot->slot_type_id,
            'sort_order' => $sortOrder,
            'title' => null,
            'subtitle' => null,
            'content' => null,
            'url' => null,
            'asset_id' => null,
            'variant' => null,
            'meta' => null,
            'settings' => null,
            'status' => 'published',
            'is_system' => in_array($slug, ['navigation-auto'], true),
        ], $attributes));
    }

    private function brandHeader(Page $page, PageSlot $slot, string $title, string $content, array $translations = []): Block
    {
        $block = $this->block($page, 'heading', $slot, 0, [
            'title' => $title,
            'content' => $content,
            'variant' => 'h2',
            'is_system' => true,
        ]);

        $this->textTranslation($block, Locale::query()->where('code', 'en')->firstOrFail(), $title, null, $content);

        if (isset($translations['tr'])) {
            $tr = $translations['tr'];
            $this->textTranslation($block, Locale::query()->where('code', 'tr')->firstOrFail(), $tr['title'] ?? $title, null, $tr['content'] ?? $content);
        }

        return $block;
    }

    private function footerBrand(Page $page, PageSlot $slot, string $title, string $content, array $translations = []): Block
    {
        return $this->brandHeader($page, $slot, $title, $content, $translations);
    }

    private function navigationBlocks(Page $page, PageSlot $header, PageSlot $footer): void
    {
        $this->block($page, 'navigation-auto', $header, 1, [
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_PRIMARY], JSON_UNESCAPED_SLASHES),
        ]);
        $this->block($page, 'navigation-auto', $header, 2, [
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_MOBILE], JSON_UNESCAPED_SLASHES),
        ]);
        $this->block($page, 'navigation-auto', $footer, 1, [
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_FOOTER], JSON_UNESCAPED_SLASHES),
        ]);
        $this->block($page, 'navigation-auto', $footer, 2, [
            'settings' => json_encode(['menu_key' => NavigationItem::MENU_LEGAL], JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function headerAction(Page $page, PageSlot $slot, string $title, string $url): void
    {
        $button = $this->block($page, 'button', $slot, 3, [
            'title' => $title,
            'url' => $url,
            'subtitle' => '_self',
            'variant' => 'primary',
        ]);

        $this->buttonTranslation($button, Locale::query()->where('code', 'en')->firstOrFail(), $title);
        $this->buttonTranslation($button, Locale::query()->where('code', 'tr')->firstOrFail(), $title === 'Open admin' ? 'Admini ac' : ($title === 'CMS admin' ? 'CMS admin' : 'Admin'));
    }

    private function textTranslation(Block $block, Locale $locale, ?string $title, ?string $subtitle, ?string $content): void
    {
        BlockTextTranslation::query()->updateOrCreate(
            ['block_id' => $block->id, 'locale_id' => $locale->id],
            ['title' => $title, 'subtitle' => $subtitle, 'content' => $content],
        );
    }

    private function buttonTranslation(Block $block, Locale $locale, string $title): void
    {
        BlockButtonTranslation::query()->updateOrCreate(
            ['block_id' => $block->id, 'locale_id' => $locale->id],
            ['title' => $title],
        );
    }

    private function contactTranslation(Block $block, Locale $locale, string $title, string $content, string $submitLabel, string $successMessage): void
    {
        BlockContactFormTranslation::query()->updateOrCreate(
            ['block_id' => $block->id, 'locale_id' => $locale->id],
            [
                'title' => $title,
                'content' => $content,
                'submit_label' => $submitLabel,
                'success_message' => $successMessage,
            ],
        );
    }
}
