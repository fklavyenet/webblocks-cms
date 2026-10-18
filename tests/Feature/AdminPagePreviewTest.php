<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\PageController;
use WebBlocks\Cms\Http\Requests\Admin\PagePreviewRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PageLayoutSlotComparison;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
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
    $this->assertStringNotContainsString('Türkçe başlık', $html);
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
