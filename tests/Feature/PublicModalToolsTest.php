<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\TestCase;

class PublicModalToolsTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('cms.multisite.unknown_host_fallback', true);
  }

  #[Test]
  public function legacy_entries_redirect_and_the_ordinary_home_owns_modal_and_native_fallbacks(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic modal studio', 'handle' => 'modal-studio', 'is_primary' => true]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Synthetic homepage', 'slug' => 'home', 'status' => 'published']);
    $main = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $page->id, 'slot_type_id' => $main->id]);
    foreach (['header-actions', 'search-form', 'plain_text'] as $type) {
      Block::create(['page_id' => $page->id, 'slot_type_id' => $main->id, 'type' => $type, 'title' => $type === 'plain_text' ? 'Synthetic searchable article' : null, 'content' => $type === 'plain_text' ? 'Searchable nebula content' : null, 'status' => 'published']);
    }
    app(PublicSearchIndexer::class)->rebuildPage($page);
    $this->get('/search?q=nebula')->assertRedirect('/?cms_search=1&q=nebula#site-search');
    $this->get('/privacy-consent/preferences')->assertRedirect('/#cookie-settings')->assertCookieMissing('webblocks_consent_'.$site->id);
    $response = $this->get('/?cms_search=1&q=nebula')->assertOk()->assertSee('Synthetic homepage')->assertSee('Searchable nebula content')->assertSee('data-wb-public-search-inline', false)->assertSee('id="cookie-preferences-form"', false);
    $this->assertSame(1, substr_count($response->getContent(), 'id="wb-public-search-modal"'));
    $this->assertSame(1, substr_count($response->getContent(), 'id="wbCookiePreferences"'));
    $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
    $this->get('/?cms_search=1&q[]=invalid')->assertUnprocessable();
    if ($path = getenv('WEBBLOCKS_MODAL_TOOLS_FIXTURE')) {
      file_put_contents($path, str_replace('http://localhost/', '/', $response->getContent()));
    }
  }
}
