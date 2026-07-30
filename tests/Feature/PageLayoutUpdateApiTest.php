<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The admin edit form could always change an existing page's Page Layout.
 * The Internal Content API could only set it at page creation -- exactly the
 * kind of gap PUT .../slots/{slot}/source closed for Shared Slot detachment:
 * a field writable through the session-authenticated admin route only, with
 * no way back out through the API that could set it in the first place.
 */
class PageLayoutUpdateApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_writes_public_shell_without_touching_other_settings(): void
  {
    $page = $this->seedPage(['existing_key' => 'kept']);

    $response = $this->invokeUpdate($page, 'article');

    $this->assertTrue($response->getData(true)['ok']);
    $page->refresh();
    $this->assertSame('article', $page->settings['public_shell']);
    $this->assertSame('kept', $page->settings['existing_key']);
  }

  #[Test]
  public function it_rejects_a_handle_no_active_layout_defines(): void
  {
    $page = $this->seedPage();

    $response = $this->invokeUpdate($page, 'not-a-real-layout');

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('page.layout', $data['errors'][0]['path']);
    $page->refresh();
    $this->assertSame('default', $page->settings['public_shell'] ?? 'default');
  }

  #[Test]
  public function it_normalizes_the_legacy_dashboard_alias_to_docs(): void
  {
    $page = $this->seedPage();

    $response = $this->invokeUpdate($page, 'dashboard');

    $this->assertTrue($response->getData(true)['ok']);
    $this->assertSame('docs', $page->fresh()->settings['public_shell']);
  }

  #[Test]
  public function the_route_and_openapi_schema_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::patch('/pages/{page}/layout', [InternalContentResourceController::class, 'updatePageLayout'])->middleware('internal-api.capability:content.apply')",
      $routes
    );

    $discovery = $this->app->make(InternalApiDiscoveryController::class);
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/pages/{page}/layout', $links['page_layout_update']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertSame('content.apply', $paths['/pages/{page}/layout']['patch']['x-required-capability']);
  }

  private function seedPage(array $extraSettings = []): Page
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    // activeHandles() reads the wbcms_page_layouts table once it exists rather
    // than falling back to the static catalog, so the table needs the real
    // rows -- the same sync that runs on install and on every System Update.
    $this->app->make(CoreLayoutCatalogSyncer::class)->sync();

    return Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'article',
      'status' => Page::STATUS_DRAFT,
      'settings' => $extraSettings === [] ? null : $extraSettings,
    ]);
  }

  private function invokeUpdate(Page $page, string $layout)
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/layout', 'PATCH', ['layout' => $layout]);

    return $this->app->make(InternalContentResourceController::class)->updatePageLayout($request, $page);
  }
}
