<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminDashboardRecentContentTest extends TestCase
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
  #[RunInSeparateProcess]
  #[PreserveGlobalState(false)]
  public function recent_pages_show_the_available_translation_when_the_default_title_is_missing(): void
  {
    if (! class_exists(User::class)) {
      class_alias(CmsSqlTestUser::class, User::class);
    }
    $user = new User;
    $user->setTable('users');
    $user->forceFill(['name' => 'Synthetic operator', 'email' => 'dashboard@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true])->save();
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $german = Locale::firstOrCreate(['code' => 'de'], ['name' => 'Deutsch', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'dashboard-studio', 'is_primary' => true]);
    $site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);

    $translatedPage = Page::create(['site_id' => $site->id, 'status' => Page::STATUS_PUBLISHED]);
    $translatedPage->translations()->create(['locale_id' => $german->id, 'name' => 'Startseite', 'slug' => 'startseite']);
    $defaultPage = Page::create(['site_id' => $site->id, 'title' => 'About us', 'slug' => 'about-us']);
    $defaultPage->translations()->create(['locale_id' => $german->id, 'name' => 'Über uns', 'slug' => 'ueber-uns']);
    $untranslatedPage = Page::create(['site_id' => $site->id]);

    $response = $this->withoutMiddleware(RedirectIfNotInstalled::class)
      ->actingAs($user)->get(route('admin.dashboard'))->assertOk();

    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent(), LIBXML_NONET);
    $xpath = new DOMXPath($dom);
    foreach ([
      [$translatedPage, 'Startseite', 'startseite'],
      [$defaultPage, 'About us', 'about-us'],
      [$untranslatedPage, '#'.$untranslatedPage->id, ''],
    ] as [$page, $title, $slug]) {
      $link = '//*[@id="dashboard-recent-pages"]//a[@href="'.route('admin.pages.edit', $page).'"]';
      $this->assertSame($title, $xpath->evaluate('string('.$link.'//*[@class="wb-link-list-title"])'));
      $this->assertSame($slug, $xpath->evaluate('string('.$link.'//*[@class="wb-link-list-meta"])'));
    }
  }
}
