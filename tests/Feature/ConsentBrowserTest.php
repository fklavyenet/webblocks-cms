<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Consent\SaveConsentService;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Consent\ConsentPresenter;
use WebBlocks\Cms\Tests\TestCase;

class ConsentBrowserTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.public', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function consent_browser_fixture(): void
  {
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::create(['name' => 'Synthetic consent fixture', 'handle' => 'consent-fixture', 'is_primary' => true]);
    $page = Page::create(['site_id' => $site->id, 'slug' => 'consent-fixture', 'status' => 'published']);
    $block = Block::create(['page_id' => $page->id, 'type' => 'external-content', 'url' => 'https://youtu.be/dQw4w9WgXcQ', 'title' => 'Synthetic local video', 'status' => 'published']);
    app(SaveConsentService::class)->execute($site, 'synthetic-script', ['name' => 'Synthetic local script', 'category' => 'analytics', 'script_url' => 'https://assets.example.test/synthetic.js']);
    $this->app->instance('request', Request::create('/'));
    $embed = view('webblocks-cms::pages.partials.blocks.external-content', ['block' => $block])->render();
    $consent = app(ConsentPresenter::class)->forSite($site, 'en');
    $consent['payload']['services']['synthetic-script']['script_url'] = '/synthetic-script.js';
    $html = '<!doctype html><html lang="en" data-wb-theme="light"><body><main class="wb-container wb-stack-4"><h1>Synthetic consent fixture</h1><a id="settings-link" href="#cookie-settings">Cookie settings</a>'.$embed.'</main><div id="wb-overlay-root" class="wb-overlay-root">'.view('webblocks-cms::partials.cookie-consent', compact('consent'))->render().'</div><script src="/cms/js/privacy-consent-sync.js" defer></script></body></html>';
    $html = str_replace('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ', '/synthetic-embed', $html);
    $this->assertStringContainsString('data-cms-consent-embed="youtube"', $html);
    $this->assertDoesNotMatchRegularExpression('/<iframe[^>]*\ssrc=/', $html);
    $this->assertStringContainsString('data-src="/synthetic-embed"', $html);
    $this->assertStringContainsString('id="wbCookiePreferences"', $html);
    $this->assertStringNotContainsString('data-wb-cookie-consent=', $html);
    if ($path = getenv('WEBBLOCKS_CONSENT_FIXTURE')) {
      file_put_contents($path, $html);
    }
  }
}
