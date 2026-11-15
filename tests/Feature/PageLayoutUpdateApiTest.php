<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
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

    $response = $this->invokeUpdate($page, 'columns');

    $this->assertTrue($response->getData(true)['ok']);
    $page->refresh();
    $this->assertSame('columns', $page->settings['public_shell']);
    $this->assertSame('kept', $page->settings['existing_key']);
  }

  #[Test]
  public function columns_sidebars_are_independent_and_a_populated_side_cannot_be_removed(): void
  {
    $page = $this->seedPage();
    $response = $this->invokeUpdate($page, 'columns', ['column_sidebars' => ['sidebar-left', 'sidebar-right']]);
    $this->assertSame(200, $response->getStatusCode());
    $slots = $page->slots()->with('slotType')->get()->keyBy(fn ($slot) => $slot->slotSlug());
    $this->assertEqualsCanonicalizing(['sidebar-left', 'sidebar-right'], $slots->keys()->all());
    Block::create(['page_id' => $page->id, 'slot_type_id' => $slots['sidebar-right']->slot_type_id, 'type' => 'plain_text', 'status' => 'draft']);

    $this->assertSame(200, $this->invokeUpdate($page->fresh(), 'columns')->getStatusCode());
    $this->assertSame(2, $page->slots()->count());
    $response = $this->invokeUpdate($page->fresh(), 'columns', ['column_sidebars' => []]);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('layout_slot_in_use', $response->getData(true)['code']);
    $this->assertSame(2, $page->slots()->count(), 'All changes are rolled back when one side is still in use.');
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
  public function it_retains_an_assigned_inactive_layout_but_cannot_select_it_again(): void
  {
    $page = $this->seedPage(['public_shell' => 'docs']);
    PageLayout::query()->where('handle', 'docs')->firstOrFail()->update(['is_active' => false]);

    $response = $this->invokeUpdate($page, 'dashboard');

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame([], $response->getData(true)['writes']);
    $this->assertSame('docs', $page->fresh()->publicShellPreset());
    $this->assertSame(0, $page->revisions()->count());

    $this->assertSame(200, $this->invokeUpdate($page->fresh(), 'columns')->getStatusCode());
    $response = $this->invokeUpdate($page->fresh(), 'docs');

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('invalid_page_layout', $response->getData(true)['code']);
    $this->assertSame('columns', $page->fresh()->publicShellPreset());
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

  private function invokeUpdate(Page $page, string $layout, array $options = [])
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/layout', 'PATCH', ['layout' => $layout] + $options);

    return $this->app->make(InternalContentResourceController::class)->updatePageLayout($request, $page);
  }
}
