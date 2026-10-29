<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Consent\ConsentPreferencesLink;
use WebBlocks\Cms\Tests\TestCase;

class CookieSettingsMountedPreferencesTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.public', true);
    $app['config']->set('webblocks-cms.public.mount', 'knowledge');
    $app['config']->set('cms.multisite.unknown_host_fallback', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function permanent_preferences_urls_and_html_posts_follow_the_public_mount_and_language(): void
  {
    $this->withoutMiddleware(EnsureInstalled::class);
    $site = Site::create(['name' => 'Synthetic mounted site', 'handle' => 'mounted-preferences', 'is_primary' => true]);
    foreach (['en', 'tr'] as $code) {
      $locale = Locale::firstOrCreate(['code' => $code], ['name' => $code, 'is_enabled' => true, 'is_default' => $code === 'en']);
      $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
      $url = app(ConsentPreferencesLink::class)->url($site, $code);
      $this->assertSame('/knowledge'.($code === 'en' ? '' : '/tr').'/privacy-consent/preferences', $url);
      $this->get($url)->assertOk()->assertSee('action="'.$url.'"', false)->assertCookieMissing('webblocks_consent_'.$site->id);
      $this->post($url, ['status' => 'rejected'])->assertRedirect($url)->assertCookie('webblocks_consent_'.$site->id);
      $this->withHeader('Referer', 'https://example.test/')->post($url, ['status' => 'invalid'])->assertRedirect($url)->assertSessionHasErrors('status');
    }
  }
}
