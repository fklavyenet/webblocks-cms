<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPageRenderController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The admin preview route already took a Bearer token, so this endpoint is not
 * what made rendering reachable. It renders a chosen locale rather than always
 * the default translation, and it lives in discovery with the rest of the API.
 */
class PageRenderApiTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);

    // The response links back to the browser admin preview route.
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_renders_draft_content_so_a_tool_can_check_its_own_work(): void
  {
    $page = $this->seedPage();

    $payload = $this->render($page)->getData(true);

    $this->assertTrue($payload['ok']);
    $this->assertSame(Page::STATUS_DRAFT, $payload['page']['status']);
    // The block is a draft: the public route would not show it, the preview does.
    $this->assertStringContainsString('Draft heading', $payload['html']);
  }

  #[Test]
  public function it_returns_raw_markup_when_asked_for_html(): void
  {
    $page = $this->seedPage();

    $response = $this->render($page, ['format' => 'html']);

    $this->assertSame('text/html; charset=UTF-8', $response->headers->get('Content-Type'));
    $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    $this->assertStringContainsString('Draft heading', $response->getContent());
  }

  #[Test]
  public function it_renders_the_requested_locale(): void
  {
    $page = $this->seedPage();
    $german = Locale::query()->create(['code' => 'de', 'name' => 'German', 'is_default' => false, 'is_enabled' => true]);
    $page->site->locales()->syncWithoutDetaching([$german->id => ['is_enabled' => true]]);
    $page->translations()->create(['locale_id' => $german->id, 'name' => 'Uber uns', 'slug' => 'uber-uns', 'path' => '/uber-uns']);

    $payload = $this->render($page->fresh(), ['locale' => 'de'])->getData(true);

    $this->assertSame('de', $payload['page']['locale']);
    $this->assertSame('/uber-uns', $payload['page']['path']);
  }

  #[Test]
  public function it_names_the_available_locales_when_the_requested_one_is_missing(): void
  {
    $page = $this->seedPage();

    $response = $this->render($page, ['locale' => 'de']);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('page_translation_missing', $payload['code']);
    $this->assertSame(['en'], $payload['available_locales']);
  }

  #[Test]
  public function it_refuses_to_render_a_shared_slot_source_page(): void
  {
    $page = $this->seedPage();
    $page->forceFill(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE])->save();

    $response = $this->render($page->fresh());

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('page_not_renderable', $response->getData(true)['code']);
  }

  #[Test]
  public function the_route_and_openapi_schema_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::get('/pages/{page}/render', [InternalPageRenderController::class, 'show'])->middleware('internal-api.capability:content.read')",
      $routes
    );

    $discovery = $this->app->make(InternalApiDiscoveryController::class);
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/pages/{page}/render', $links['page_render']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertSame('content.read', $paths['/pages/{page}/render']['get']['x-required-capability']);
  }

  private function seedPage(): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $mainSlot = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);

    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'about', 'status' => Page::STATUS_DRAFT]);
    $page->translations()->create(['locale_id' => $locale->id, 'name' => 'About', 'slug' => 'about', 'path' => '/about']);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $mainSlot->id, 'sort_order' => 0]);

    Block::create([
      'page_id' => $page->id,
      'type' => 'header',
      'slot_type_id' => $mainSlot->id,
      'sort_order' => 0,
      'title' => 'Draft heading',
      'variant' => 'h2',
      'status' => 'draft',
    ]);

    return $page->fresh();
  }

  private function render(Page $page, array $query = [])
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/render', 'GET', $query);

    return $this->app->make(InternalPageRenderController::class)->show($request, $page);
  }
}
