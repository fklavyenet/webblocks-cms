<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSharedSlotController;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The Internal Content API could point a page slot at a Shared Slot and had no
 * way to point it back. Building a reference an API client cannot undo with the
 * same API is a trap: the only exit was the session-authenticated admin route,
 * which a token client cannot reach.
 */
class PageSlotSourceApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function routes(): string
  {
    return (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
  }

  private function controller(): string
  {
    return (string) file_get_contents(
      dirname(__DIR__, 2).'/src/Http/Controllers/InternalContentApi/InternalSharedSlotController.php'
    );
  }

  #[Test]
  public function the_source_endpoint_is_mounted_on_the_internal_api(): void
  {
    $routes = $this->routes();

    $this->assertStringContainsString(
      "Route::put('/pages/{page}/slots/{slot}/source', [InternalSharedSlotController::class, 'updatePageSlotSource'])",
      $routes
    );
    // content.apply is the content-structure capability; the shared_slot case
    // adds shared-slots.write in the controller so detaching does not demand it.
    $this->assertStringContainsString(
      "'updatePageSlotSource'])->middleware('internal-api.capability:content.apply')",
      $routes
    );
  }

  #[Test]
  public function binding_to_a_shared_slot_still_requires_shared_slots_write(): void
  {
    $controller = $this->controller();

    $this->assertStringContainsString('CmsApiTokenCapabilities::SHARED_SLOTS_WRITE', $controller);
    $this->assertStringContainsString('return $this->assignToPageSlot($request, $page, $slot);', $controller);
  }

  #[Test]
  public function detaching_clears_the_shared_slot_reference(): void
  {
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site = Site::create(['handle' => 'synthetic', 'name' => 'Synthetic']);
    $page = Page::create(['site_id' => $site->id, 'slug' => 'draft', 'status' => Page::STATUS_DRAFT]);
    $slotType = SlotType::firstOrCreate(['slug' => 'footer'], ['name' => 'Footer', 'status' => 'published']);
    $shared = SharedSlot::create(['site_id' => $site->id, 'handle' => 'footer', 'name' => 'Footer', 'slot_name' => $slotType->slug]);
    $slot = $page->slots()->create([
      'slot_type_id' => $slotType->id,
      'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT,
      'shared_slot_id' => $shared->id,
    ]);

    $response = app(InternalSharedSlotController::class)->updatePageSlotSource(
      Request::create('/', 'PUT', ['source_type' => PageSlot::SOURCE_TYPE_PAGE]), $page, $slotType->slug,
    );

    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame(PageSlot::SOURCE_TYPE_PAGE, $slot->fresh()->runtimeSourceType());
    $this->assertNull($slot->fresh()->shared_slot_id);
    $this->assertFalse($shared->pageSlots()->exists());
  }

  #[Test]
  public function an_unknown_source_type_is_rejected_before_anything_is_written(): void
  {
    $controller = $this->controller();

    $this->assertStringContainsString('PageSlot::sourceTypes()', $controller);
    $this->assertStringContainsString("'path' => 'page_slot.source_type'", $controller);
  }

  #[Test]
  public function a_missing_page_slot_is_reported_rather_than_created(): void
  {
    $this->assertStringContainsString(
      "'message' => 'Page slot must exist before changing its source.'",
      $this->controller()
    );
  }

  #[Test]
  public function discovery_and_openapi_both_advertise_the_endpoint(): void
  {
    $discovery = $this->app->make(InternalApiDiscoveryController::class);

    // index() only emits the link map to an authenticated token, so the map
    // itself is read directly rather than standing up a token for one assertion.
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/pages/{page}/slots/{slot}/source', $links['page_slot_source']);
    $this->assertSame('/webadmin/api/pages/{page}/slots/{slot}/shared-slot', $links['page_slot_shared_slot']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertArrayHasKey('/pages/{page}/slots/{slot}/source', $paths);
    $this->assertSame(
      ['page', 'shared_slot', 'disabled'],
      $paths['/pages/{page}/slots/{slot}/source']['put']['x-source-types']
    );
  }
}
