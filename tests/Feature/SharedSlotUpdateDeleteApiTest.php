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
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The API could create a Shared Slot and fill it with blocks, but the Shared
 * Slot itself was create-only: a bad handle or label stayed bad, and nothing
 * the API created could be removed by the API. Both were browser admin work.
 */
class SharedSlotUpdateDeleteApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_renames_a_shared_slot(): void
  {
    $sharedSlot = $this->seedSharedSlot();

    $response = $this->update($sharedSlot, ['label' => 'Global footer', 'handle' => 'global-footer']);

    $this->assertTrue($response->getData(true)['ok']);
    $sharedSlot->refresh();
    $this->assertSame('Global footer', $sharedSlot->name);
    $this->assertSame('global-footer', $sharedSlot->handle);
  }

  #[Test]
  public function it_only_writes_the_fields_the_request_carries(): void
  {
    $sharedSlot = $this->seedSharedSlot();

    $this->update($sharedSlot, ['is_active' => false]);

    $sharedSlot->refresh();
    $this->assertFalse((bool) $sharedSlot->is_active);
    $this->assertSame('Site footer', $sharedSlot->name);
    $this->assertSame('site-footer', $sharedSlot->handle);
  }

  #[Test]
  public function it_rejects_a_handle_another_shared_slot_on_the_site_uses(): void
  {
    $sharedSlot = $this->seedSharedSlot();
    SharedSlot::query()->create([
      'site_id' => $sharedSlot->site_id,
      'name' => 'Header',
      'handle' => 'site-header',
      'slot_name' => 'footer',
      'is_active' => true,
    ]);

    $response = $this->update($sharedSlot, ['handle' => 'site-header']);

    $data = $response->getData(true);
    $this->assertFalse($data['ok']);
    $this->assertSame('handle', $data['errors'][0]['path']);
    $this->assertSame('site-footer', $sharedSlot->refresh()->handle);
  }

  #[Test]
  public function it_rejects_a_slot_type_that_is_not_published(): void
  {
    $sharedSlot = $this->seedSharedSlot();

    $response = $this->update($sharedSlot, ['slot' => 'not-a-slot-type']);

    $this->assertFalse($response->getData(true)['ok']);
    $this->assertSame('footer', $sharedSlot->refresh()->slot_name);
  }

  #[Test]
  public function it_refuses_to_move_a_shared_slot_between_sites(): void
  {
    $sharedSlot = $this->seedSharedSlot();

    $response = $this->update($sharedSlot, ['site_id' => 99, 'label' => 'Renamed']);

    $data = $response->getData(true);
    $this->assertSame('unsupported_shared_slot_fields', $data['code']);
    $this->assertSame(['site_id'], $data['blocked_fields']);
    $this->assertSame('Site footer', $sharedSlot->refresh()->name);
  }

  #[Test]
  public function it_deletes_a_shared_slot_no_page_slot_references(): void
  {
    $sharedSlot = $this->seedSharedSlot();

    $response = $this->app->make(InternalSharedSlotController::class)->destroy($sharedSlot);

    $this->assertTrue($response->getData(true)['ok']);
    $this->assertNull(SharedSlot::query()->find($sharedSlot->id));
  }

  #[Test]
  public function it_refuses_to_delete_a_shared_slot_a_page_still_uses(): void
  {
    $sharedSlot = $this->seedSharedSlot();
    $page = Page::query()->create(['site_id' => $sharedSlot->site_id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => SlotType::query()->where('slug', 'footer')->value('id'),
      'source_type' => 'shared_slot',
      'shared_slot_id' => $sharedSlot->id,
      'sort_order' => 0,
    ]);

    $response = $this->app->make(InternalSharedSlotController::class)->destroy($sharedSlot);

    $data = $response->getData(true);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('shared_slot_in_use', $data['code']);
    $this->assertSame($page->id, $data['usage'][0]['page_id']);
    $this->assertNotNull(SharedSlot::query()->find($sharedSlot->id));
  }

  #[Test]
  public function deleting_requires_its_own_destructive_capability(): void
  {
    $this->assertContains(CmsApiTokenCapabilities::SHARED_SLOTS_DELETE, CmsApiTokenCapabilities::DESTRUCTIVE);
    $this->assertNotContains(CmsApiTokenCapabilities::SHARED_SLOTS_DELETE, CmsApiTokenCapabilities::DEFAULT);
    $this->assertContains(CmsApiTokenCapabilities::SHARED_SLOTS_DELETE, CmsApiTokenCapabilities::ALL);
  }

  #[Test]
  public function the_routes_and_openapi_schema_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::patch('/shared-slots/{sharedSlot}', [InternalSharedSlotController::class, 'update'])->middleware('internal-api.capability:shared-slots.write')",
      $routes
    );
    $this->assertStringContainsString(
      "Route::delete('/shared-slots/{sharedSlot}', [InternalSharedSlotController::class, 'destroy'])->middleware('internal-api.capability:shared-slots.delete')",
      $routes
    );

    $paths = $this->app->make(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $this->assertSame('shared-slots.write', $paths['/shared-slots/{sharedSlot}']['patch']['x-required-capability']);
    $this->assertSame('shared-slots.delete', $paths['/shared-slots/{sharedSlot}']['delete']['x-required-capability']);
  }

  private function seedSharedSlot(): SharedSlot
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    SlotType::query()->firstOrCreate(['slug' => 'footer'], ['name' => 'Footer', 'status' => 'published']);

    return SharedSlot::query()->create([
      'site_id' => $site->id,
      'name' => 'Site footer',
      'handle' => 'site-footer',
      'slot_name' => 'footer',
      'is_active' => true,
    ]);
  }

  private function update(SharedSlot $sharedSlot, array $payload)
  {
    $request = Request::create('/webadmin/api', 'PATCH', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

    return $this->app->make(InternalSharedSlotController::class)->update($request, $sharedSlot);
  }
}
