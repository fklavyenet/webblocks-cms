<?php

namespace WebBlocks\Cms\Tests\Feature;

use DOMDocument;
use DOMXPath;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\Admin\PageController as AdminPageController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Http\Controllers\Public\PageController;
use WebBlocks\Cms\Http\Requests\Admin\PagePreviewRequest;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Seo\PageSeoReport;
use WebBlocks\Cms\Tests\TestCase;

class PublicPageTitleTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('app.name', 'Unrelated host application');
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  public static function titles(): array
  {
    return [
      'explicit SEO without brand' => ['About', 'Complete editorial title', 'Northstar', 'Complete editorial title'],
      'explicit SEO with punctuation and spacing' => ['About', '  A & B | Editorial · choice  ', 'Northstar', '  A & B | Editorial · choice  '],
      'long explicit SEO remains complete' => ['About', str_repeat('Editorial title ', 30), 'Northstar', str_repeat('Editorial title ', 30)],
      'explicit SEO containing CMS name' => ['About', 'WebBlocks CMS tutorials', 'Northstar', 'WebBlocks CMS tutorials'],
      'blank SEO' => ['About', null, 'Northstar', 'About | Northstar'],
      'whitespace SEO' => ['Hakkımızda', '  ', 'Northstar', 'Hakkımızda | Northstar'],
      'same identity' => ['Northstar', '', 'Northstar', 'Northstar'],
      'brand already at start' => ['Northstar — About', '', 'Northstar', 'Northstar — About'],
      'brand already at end' => ['About | NORTHSTAR', '', 'Northstar', 'About | NORTHSTAR'],
      'brand with repeated whitespace' => ['About Northstar  Studio', '', 'Northstar Studio', 'About Northstar  Studio'],
      'brand is not a word fragment' => ['Articles', '', 'Art', 'Articles | Art'],
      'unicode brand' => ['Über KÜSTE', '', 'Küste', 'Über KÜSTE'],
      'missing page title' => ['', '', 'Northstar', 'Northstar'],
      'missing brand' => ['About', '', '', 'About'],
      'both missing' => ['', '', '', 'Unrelated host application'],
    ];
  }

  #[Test]
  #[DataProvider('titles')]
  public function localized_metadata_and_head_preserve_the_title_contract(string $name, ?string $seo, string $brand, string $expected): void
  {
    $page = $this->fixture();
    $page->site->update(['display_name' => $brand, 'name' => '', 'seo_title' => '']);
    $translation = $page->translations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->first();
    $translation->update(['name' => $name, 'seo_title' => $seo]);
    $page->setRelation('currentTranslation', $translation->load('locale'));
    $meta = app(PublicPagePresenter::class)->publicMeta($page);
    $this->assertSame($expected, $meta['title']);
    $this->assertSame($expected, $meta['og_title']);
    $head = $this->document(view('webblocks-cms::partials.head-meta', ['title' => $meta['title'], 'ogTitle' => $meta['og_title']])->render());
    $this->assertSame($expected, $head->evaluate('string(//title)'));
    $this->assertSame($expected, $head->evaluate('string(//meta[@property="og:title"]/@content)'));
    $this->assertSame($expected, $head->evaluate('string(//meta[@name="twitter:title"]/@content)'));
  }

  #[Test]
  public function brand_fallbacks_use_the_owning_site_and_explicit_og_wins(): void
  {
    $page = $this->fixture();
    $page->site->update(['display_name' => ' ', 'seo_title' => 'Studio brand', 'name' => 'Internal site']);
    $page->setRelation('currentTranslation', $page->translations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->first());
    $page->currentTranslation->update(['og_title' => 'Independent social title']);
    $presenter = app(PublicPagePresenter::class);
    $this->assertSame('Türkçe sayfa | Studio brand', $presenter->publicMeta($page)['title']);
    $this->assertSame('Independent social title', $presenter->publicMeta($page)['og_title']);
    $page->site->update(['seo_title' => ' ']);
    $this->assertSame('Türkçe sayfa | Internal site', $presenter->publicMeta($page)['title']);
  }

  #[Test]
  public function public_preview_api_and_seo_report_agree_without_rewriting_heading_or_breadcrumb(): void
  {
    $page = $this->fixture();
    foreach ([null, '  Independent SEO & editorial title  '] as $seoTitle) {
      $page->translations()->whereHas('locale', fn ($query) => $query->where('code', 'tr'))->update(['seo_title' => $seoTitle]);
      $expected = $seoTitle ?? 'Türkçe sayfa | Northstar Studio';
      $request = Request::create('https://studio.example.test/tr/sayfa');
      $route = new Route('GET', '{locale}/{path}', []);
      $route->bind($request)->setParameter('locale', 'tr');
      $request->setRouteResolver(fn () => $route);
      app()->instance('request', $request);
      $public = app(PageController::class)->show($request, 'tr', 'sayfa')->render();

      $request = PagePreviewRequest::create('/webadmin/pages/'.$page->id.'/preview', 'GET', ['locale' => 'tr']);
      $route = new Route('GET', 'webadmin/pages/{page}/preview', []);
      $route->bind($request)->setParameter('page', $page);
      $request->setRouteResolver(fn () => $route);
      $request->attributes->set('cms_internal_preview', true);
      $request->setContainer($this->app)->setRedirector($this->app['redirect']);
      app()->instance('request', $request);
      $request->validateResolved();
      $preview = app(AdminPageController::class)->preview($page->fresh(), app(PublicPagePresenter::class))->getContent();
      $api = app(InternalPageRenderController::class)->show(Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', ['locale' => 'tr']), $page->fresh())->getData(true);
      foreach ([$public, $preview, $api['html']] as $html) {
        $document = $this->document($html);
        $this->assertSame($expected, $document->evaluate('string(//title)'));
        $this->assertSame($expected, $document->evaluate('string(//meta[@property="og:title"]/@content)'));
        $this->assertSame('Bağımsız ana başlık', $document->evaluate('string(//h1)'));
        $this->assertStringContainsString('Türkçe sayfa', $document->evaluate('string(//nav[contains(@class,"wb-breadcrumb")])'));
      }
      $this->assertSame('Türkçe sayfa', $api['page']['title']);
      $report = app(PageSeoReport::class)->run($page, 'tr');
      $this->assertSame($expected, $report['metadata']['title']['value']);
      $this->assertSame($seoTitle === null ? 'translation_name' : 'translation_seo_title', $report['metadata']['title']['source']);
      $this->assertSame($seoTitle === null, $report['metadata']['title']['site_label']);
    }
    $this->assertSame('published', $page->fresh()->status);
  }

  private function fixture(): Page
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Internal site', 'display_name' => 'Northstar Studio', 'handle' => 'northstar', 'domain' => 'studio.example.test', 'is_primary' => true]);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'English page', 'slug' => 'page', 'status' => 'published', 'published_at' => now()]);
    $page->translations()->create(['locale_id' => $tr->id, 'name' => 'Türkçe sayfa', 'slug' => 'sayfa', 'path' => '/sayfa']);
    $main = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $page->slots()->create(['slot_type_id' => $main->id]);
    $heading = $page->blocks()->create(['slot_type_id' => $main->id, 'type' => 'header', 'variant' => 'h1', 'status' => 'published']);
    $heading->textTranslations()->create(['locale_id' => $tr->id, 'title' => 'Bağımsız ana başlık']);
    $page->blocks()->create(['slot_type_id' => $main->id, 'type' => 'breadcrumb', 'status' => 'published', 'sort_order' => 1]);

    return $page->fresh();
  }

  private function document(string $html): DOMXPath
  {
    $document = new DOMDocument;
    @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

    return new DOMXPath($document);
  }
}
