<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Http\Requests\Admin\PagePreviewRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotComparison;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminPagePreviewTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function browser_preview_renders_the_selected_page_block_and_image_language_without_saving(): void
  {
    [$page] = $this->fixture();
    $before = [$page->getAttributes(), Block::all()->map->getAttributes()->all()];
    $response = $this->preview($page, ['locale' => 'TR']);
    $html = $response->getContent();

    $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    $this->assertStringContainsString('Türkçe sayfa', $html);
    $this->assertStringContainsString('Türkçe başlık', $html);
    $this->assertStringContainsString('alt="Türkçe açıklama"', $html);
    $this->assertStringContainsString('<figcaption>Türkçe altyazı</figcaption>', $html);
    $this->assertStringContainsString('data-search-path="/tr"', $html);
    $this->assertStringContainsString('data-search-json-path="/tr/search.json"', $html);
    $this->assertStringNotContainsString('English heading', $html);
    $this->assertSame($before, [$page->fresh()->getAttributes(), Block::all()->map->getAttributes()->all()]);
  }

  #[Test]
  public function an_unspecified_preview_language_keeps_the_default_translation(): void
  {
    [$page] = $this->fixture();
    $html = $this->preview($page)->getContent();
    $this->assertStringContainsString('English heading', $html);
    $this->assertStringContainsString('alt="English description"', $html);
    $this->assertStringContainsString('data-search-path="/"', $html);
    $this->assertStringContainsString('data-search-json-path="/search.json"', $html);
    $this->assertStringNotContainsString('Türkçe başlık', $html);
  }

  #[Test]
  public function private_preview_menu_labels_and_links_use_the_rendered_site_and_locale(): void
  {
    config()->set('app.url', 'https://admin.example.test');
    [$page, $turkish] = $this->fixture();
    $site = $page->site;
    $site->update(['domain' => 'cms.example.test']);
    $site->siteDomains()->create(['domain' => 'alias.example.test', 'status' => 'active']);
    Site::create(['name' => 'Admin host', 'handle' => 'admin-host', 'domain' => 'admin.example.test', 'is_primary' => true]);
    $german = Locale::create(['code' => 'de', 'name' => 'German', 'is_enabled' => true]);
    $site->locales()->attach($german->id, ['is_enabled' => true]);
    $page->translations()->create(['locale_id' => $german->id, 'name' => 'Deutsche Seite', 'slug' => 'vorschau', 'path' => '/vorschau']);

    $blogs = Page::create(['site_id' => $site->id, 'title' => 'Blogs', 'slug' => 'blogs', 'status' => Page::STATUS_PUBLISHED, 'published_at' => now()]);
    $blogs->translations()->create(['locale_id' => $turkish->id, 'name' => 'Türkçe blog', 'slug' => 'yazilar', 'path' => '/yazilar']);
    $blogs->translations()->create(['locale_id' => $german->id, 'name' => 'Deutscher Blog', 'slug' => 'beitraege', 'path' => '/beitraege']);
    $englishOnly = Page::create(['site_id' => $site->id, 'title' => 'News', 'slug' => 'news', 'status' => Page::STATUS_PUBLISHED, 'published_at' => now()]);
    $group = $site->navigationItems()->create(['title' => 'Articles', 'menu_key' => 'primary', 'link_type' => NavigationItem::LINK_GROUP]);
    $blogLink = $site->navigationItems()->create(['title' => 'Blogs', 'menu_key' => 'primary', 'parent_id' => $group->id, 'link_type' => NavigationItem::LINK_PAGE, 'page_id' => $blogs->id]);
    $group->translations()->create(['locale_id' => $turkish->id, 'title' => 'Makaleler']);
    $group->translations()->create(['locale_id' => $german->id, 'title' => 'Artikel']);
    $blogLink->translations()->create(['locale_id' => $turkish->id, 'title' => 'Yazılar']);
    $blogLink->translations()->create(['locale_id' => $german->id, 'title' => 'Beiträge']);
    $site->navigationItems()->create(['title' => 'News', 'menu_key' => 'primary', 'link_type' => NavigationItem::LINK_PAGE, 'page_id' => $englishOnly->id]);
    $relativeLink = $site->navigationItems()->create(['title' => 'Relative URL', 'menu_key' => 'primary', 'link_type' => NavigationItem::LINK_CUSTOM_URL, 'url' => '/blogs?view=all#top']);
    $relativeLink->translations()->create(['locale_id' => $german->id, 'title' => '   ']);
    $externalLink = $site->navigationItems()->create(['title' => 'External URL', 'menu_key' => 'primary', 'link_type' => NavigationItem::LINK_CUSTOM_URL, 'url' => 'https://external.example.test/blogs']);
    $externalLink->translations()->create(['locale_id' => $german->id, 'title' => 'Externer Link']);

    $header = SlotType::create(['name' => 'Header', 'slug' => 'header', 'status' => 'published']);
    $sharedHeader = SharedSlot::create(['site_id' => $site->id, 'name' => 'Site Header', 'handle' => 'site-header', 'slot_name' => 'header', 'is_active' => true]);
    $sharedSlots = app(SharedSlotSourcePageManager::class);
    $headerSource = $sharedSlots->ensureFor($sharedHeader);
    foreach (['navbar-navigation', 'header-actions'] as $type) {
      $headerSource->blocks()->create(['type' => $type, 'slot_type_id' => $header->id, 'status' => 'published', 'settings' => ['menu_key' => 'primary']]);
    }
    $sharedSlots->rebuildAssignments($sharedHeader);
    $page->slots()->create(['slot_type_id' => $header->id, 'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT, 'shared_slot_id' => $sharedHeader->id]);

    foreach (['sidebar' => 'sidebar-navigation', 'footer' => 'navigation-auto'] as $slug => $type) {
      $slot = SlotType::create(['name' => $slug, 'slug' => $slug, 'status' => 'published']);
      $page->slots()->create(['slot_type_id' => $slot->id]);
      $page->blocks()->create(['type' => $type, 'slot_type_id' => $slot->id, 'status' => 'published', 'settings' => ['menu_key' => 'primary']]);
    }

    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $preview = '/webadmin/pages/'.$page->id.'/preview';
    $this->get('https://admin.example.test'.$preview.'?locale=tr')->assertRedirect();
    if (! class_exists(User::class)) {
      class_alias(CmsSqlTestUser::class, User::class);
    }
    $this->actingAs($this->cmsActor(new User(['role' => 'super_admin'])));
    foreach (['admin.example.test' => 'https://cms.example.test', 'cms.example.test' => '', 'alias.example.test' => ''] as $host => $origin) {
      $html = $this->get('https://'.$host.$preview.'?locale=tr')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->getContent();
      $this->assertMenu($html, 'Makaleler', [
        'Yazılar' => $origin.'/tr/yazilar',
        'News' => $origin.'/news',
        'Relative URL' => '/blogs?view=all#top',
        'External URL' => 'https://external.example.test/blogs',
      ]);
      $this->assertStringContainsString('href="/preview"', $html);
      $this->assertStringContainsString('href="/tr/onizleme"', $html);
      $this->assertStringContainsString('aria-current="true"', $html);
    }

    $germanLinks = ['Beiträge' => '/de/beitraege', 'Relative URL' => '/blogs?view=all#top', 'Externer Link' => 'https://external.example.test/blogs'];
    $defaultLinks = ['Blogs' => '/blogs', 'News' => '/news', 'Relative URL' => '/blogs?view=all#top', 'External URL' => 'https://external.example.test/blogs'];
    $germanPreview = $this->get('https://cms.example.test'.$preview.'?locale=de')->assertOk()->getContent();
    $this->assertMenu($germanPreview, 'Artikel', $germanLinks + ['News' => '/news']);
    $defaultPreview = $this->get('https://cms.example.test'.$preview)->assertOk()->getContent();
    $this->assertMenu($defaultPreview, 'Articles', $defaultLinks);
    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);

    $page->update(['status' => Page::STATUS_PUBLISHED, 'published_at' => now()]);
    $public = $this->get('https://cms.example.test/tr/onizleme')->assertOk()->getContent();
    $this->assertMenu($public, 'Makaleler', [
      'Yazılar' => '/tr/yazilar',
      'News' => null,
      'Relative URL' => '/blogs?view=all#top',
      'External URL' => 'https://external.example.test/blogs',
    ]);
    $this->assertMenu($this->get('https://cms.example.test/de/vorschau')->assertOk()->getContent(), 'Artikel', $germanLinks + ['News' => null]);
    $this->assertMenu($this->get('https://cms.example.test/preview?locale=de')->assertOk()->getContent(), 'Articles', $defaultLinks);
  }

  private function assertMenu(string $html, string $groupLabel, array $links): void
  {
    $document = new DOMDocument;
    $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    foreach (['wb-navbar-links', 'wb-navbar-drawer', 'wb-sidebar-nav', 'wb-slot-footer'] as $region) {
      $regionPath = '//*[contains(concat(" ", normalize-space(@class), " "), " '.$region.' ")]';
      foreach ($links as $label => $href) {
        $anchors = $xpath->query($regionPath.'//a[normalize-space(.)="'.$label.'"]');
        $this->assertSame($href === null ? 0 : 1, $anchors->length, $region.' '.$label);
        $this->assertSame($href, $anchors->item(0)?->getAttribute('href'), $region.' '.$label);
      }
      $groups = $xpath->query($regionPath.'//*[self::span or self::button][normalize-space(text())="'.$groupLabel.'"]');
      $this->assertSame(1, $groups->length, $region.' '.$groupLabel);
    }
  }

  #[Test]
  public function a_missing_translation_does_not_silently_preview_the_default_language(): void
  {
    [$page] = $this->fixture();
    $german = Locale::create(['code' => 'de', 'name' => 'German', 'is_enabled' => true]);
    $page->site->locales()->attach($german->id, ['is_enabled' => true]);
    $this->expectException(ValidationException::class);
    $this->preview($page, ['locale' => 'de']);
  }

  #[Test]
  public function a_globally_disabled_language_cannot_be_previewed(): void
  {
    [$page, $turkish] = $this->fixture();
    $turkish->forceFill(['is_enabled' => false])->save();
    $this->expectException(ValidationException::class);
    $this->preview($page->fresh(), ['locale' => 'tr']);
  }

  #[Test]
  public function a_disabled_site_language_cannot_be_previewed_even_with_a_translation(): void
  {
    [$page, $turkish] = $this->fixture();
    $page->site->locales()->updateExistingPivot($turkish->id, ['is_enabled' => false]);
    $this->expectException(ValidationException::class);
    $this->preview($page->fresh(), ['locale' => 'tr']);
  }

  #[Test]
  public function a_malformed_language_is_rejected_instead_of_becoming_the_default(): void
  {
    [$page] = $this->fixture();
    $this->expectException(ValidationException::class);
    $this->preview($page, ['locale' => ['tr']]);
  }

  #[Test]
  public function the_translation_table_describes_records_without_claiming_block_translation_completeness(): void
  {
    [$page] = $this->fixture();
    $page->blocks()->where('type', 'header')->first()->textTranslations()->where('locale_id', Locale::where('code', 'tr')->value('id'))->delete();
    $request = Request::create('/webadmin/pages/'.$page->id.'/edit');
    $request->setLaravelSession($this->app['session']->driver());
    $this->app->instance('request', $request);
    $this->app['router']->get('/test-login', fn () => '')->name('webblocks.auth.login');
    $this->app['view']->share('errors', new ViewErrorBag);
    $page->load(['site', 'translations.locale', 'pageAssets']);
    $page->setRelation('slots', collect());

    foreach (AdminLocaleResolver::SUPPORTED_LOCALES as $locale) {
      $this->app['config']->set('app.locale', $locale);
      $html = view('webblocks-cms::admin.pages.edit', [
        'page' => $page, 'translationStatuses' => $page->translationStatusForSite(),
        'canViewRevisions' => false, 'canDuplicatePage' => false, 'canMoveToAnotherSite' => false,
        'canManagePageAssets' => false, 'canEditContent' => false, 'canCreateSharedSlots' => false,
        'workflowActions' => [], 'slotTypes' => collect(), 'slotBlockPreviews' => collect(),
        'slotSharedSlotOptions' => collect(), 'sharedSlotSourcesAvailable' => true,
        'layoutSlotComparison' => app(PageLayoutSlotComparison::class)->compare($page),
      ])->render();
      $translator = app(CmsTranslator::class);
      $this->assertStringContainsString(e($translator->admin('page_edit.translation_record_present', $locale)), $html);
      $this->assertStringContainsString(e($translator->admin('page_edit.translations_help', $locale)), $html);
      $this->assertStringContainsString(e(route('admin.pages.preview', ['page' => $page, 'locale' => 'tr'])), $html);
    }
  }

  private function preview(Page $page, array $query = [])
  {
    $request = PagePreviewRequest::create('/webadmin/pages/'.$page->id.'/preview', 'GET', $query);
    $route = new Route('GET', 'webadmin/pages/{page}/preview', []);
    $route->bind($request);
    $route->setParameter('page', $page);
    $request->setRouteResolver(fn () => $route);
    $request->attributes->set('cms_internal_preview', true);
    $request->setContainer($this->app)->setRedirector($this->app['redirect']);
    $this->app->instance('request', $request);
    $request->validateResolved();

    return app(PageController::class)->preview($page, app(PublicPagePresenter::class));
  }

  private function fixture(): array
  {
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $site->locales()->sync([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'English page', 'slug' => 'preview', 'status' => 'draft']);
    $page->translations()->create(['locale_id' => $turkish->id, 'name' => 'Türkçe sayfa', 'slug' => 'onizleme', 'path' => '/onizleme']);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'sort_order' => 0]);
    $heading = $page->blocks()->create(['type' => 'header', 'slot_type_id' => $slot->id, 'sort_order' => 0, 'status' => 'draft']);
    $image = $page->blocks()->create(['type' => 'image', 'slot_type_id' => $slot->id, 'sort_order' => 1, 'status' => 'draft', 'media_id' => Media::create(['disk' => 'public', 'path' => 'synthetic/image.jpg', 'filename' => 'image.jpg', 'mime_type' => 'image/jpeg', 'kind' => 'image', 'visibility' => 'public'])->id]);
    foreach ([[$english, 'English heading', 'English description', 'English caption'], [$turkish, 'Türkçe başlık', 'Türkçe açıklama', 'Türkçe altyazı']] as [$locale, $title, $alt, $caption]) {
      $heading->textTranslations()->create(['locale_id' => $locale->id, 'title' => $title]);
      $image->imageTranslations()->create(['locale_id' => $locale->id, 'alt_text' => $alt, 'caption' => $caption]);
    }

    return [$page->fresh(), $turkish];
  }
}
