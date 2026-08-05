<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalNavigationController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Navigation item titles used to live in a single locale-less column, so a
 * fully translated site still rendered its menu labels in the default
 * language on every locale path. Titles now resolve through locale-keyed
 * translation rows with fallback to the canonical default-locale title,
 * and the navigation write endpoints accept a locale the same way block
 * writes do.
 */
class NavigationItemTranslationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function resolved_title_prefers_the_requested_locale_and_falls_back_to_the_canonical_title(): void
  {
    [, $turkish, $item] = $this->seedSiteWithItem();
    $item->translations()->create(['locale_id' => $turkish->id, 'title' => 'Anasayfa']);
    $item = $item->fresh(['translations.locale']);

    $this->assertSame('Home', $item->resolvedTitle());
    $this->assertSame('Anasayfa', $item->resolvedTitle($turkish));

    $this->setRequestRouteLocale('tr');
    $this->assertSame('Anasayfa', $item->resolvedTitle());
  }

  #[Test]
  public function resolved_title_falls_back_when_no_translation_row_exists_for_the_locale(): void
  {
    [, $turkish, $item] = $this->seedSiteWithItem();

    $this->assertSame('Home', $item->resolvedTitle($turkish));

    $this->setRequestRouteLocale('tr');
    $this->assertSame('Home', $item->fresh(['translations.locale'])->resolvedTitle());
  }

  #[Test]
  public function the_api_writes_a_translation_row_for_a_non_default_locale_and_keeps_the_canonical_title(): void
  {
    [, $turkish, $item] = $this->seedSiteWithItem();

    $response = $this->updateItem($item, ['locale' => 'tr', 'label' => 'Anasayfa', 'visibility' => 'hidden']);

    $data = $response->getData(true);
    $this->assertTrue($data['ok']);

    $item->refresh();
    $this->assertSame('Home', $item->getRawOriginal('title'));
    $this->assertSame('hidden', $item->visibility);

    $translation = $item->translations()->where('locale_id', $turkish->id)->first();
    $this->assertNotNull($translation);
    $this->assertSame('Anasayfa', $translation->title);

    $this->assertSame(['tr' => 'Anasayfa'], $data['navigation_item']['title_translations']);
  }

  #[Test]
  public function the_api_writes_the_canonical_title_for_the_default_locale(): void
  {
    [, , $item] = $this->seedSiteWithItem();

    $response = $this->updateItem($item, ['locale' => 'en', 'label' => 'Start']);

    $this->assertTrue($response->getData(true)['ok']);
    $item->refresh();
    $this->assertSame('Start', $item->getRawOriginal('title'));
    $this->assertSame(0, $item->translations()->count());
  }

  #[Test]
  public function the_api_rejects_a_locale_the_site_has_not_enabled(): void
  {
    [, , $item] = $this->seedSiteWithItem();
    Locale::query()->create(['code' => 'fr', 'name' => 'French', 'is_default' => false, 'is_enabled' => true]);

    $response = $this->updateItem($item, ['locale' => 'fr', 'label' => 'Accueil']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('navigation_item.locale', $data['errors'][0]['path']);
    $this->assertSame('Home', $item->refresh()->getRawOriginal('title'));
  }

  /**
   * @return array{0: Site, 1: Locale, 2: NavigationItem}
   */
  private function seedSiteWithItem(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $english = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::query()->create(['code' => 'tr', 'name' => 'Türkçe', 'is_default' => false, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([
      $english->id => ['is_enabled' => true],
      $turkish->id => ['is_enabled' => true],
    ]);

    $item = NavigationItem::query()->create([
      'site_id' => $site->id,
      'menu_key' => NavigationItem::MENU_PRIMARY,
      'title' => 'Home',
      'link_type' => NavigationItem::LINK_CUSTOM_URL,
      'url' => '/',
      'position' => 1,
      'visibility' => NavigationItem::VISIBILITY_VISIBLE,
    ]);

    return [$site, $turkish, $item];
  }

  private function updateItem(NavigationItem $item, array $payload)
  {
    $request = Request::create('/webadmin/api', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

    return $this->app->make(InternalNavigationController::class)
      ->updateItem($request, $item->menu_key, $item);
  }

  private function setRequestRouteLocale(string $code): void
  {
    $request = Request::create('/'.$code);
    $route = (new Route(['GET'], '/{locale}', fn () => null))->bind($request);
    $route->setParameter('locale', $code);
    $request->setRouteResolver(fn () => $route);
    $this->app->instance('request', $request);
  }
}
