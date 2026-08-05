<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Uploads could always be filed into a folder, but only one an operator had
 * already made by hand: folder_id resolved against existing rows and nothing
 * created them. A tool could fill the Media Library and never organize it.
 */
class MediaFolderApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_creates_a_folder_and_derives_the_slug(): void
  {
    $response = $this->store(['name' => 'Client Logos']);

    $this->assertSame(201, $response->getStatusCode());
    $payload = $response->getData(true);
    $this->assertSame('Client Logos', $payload['media_folder']['name']);
    $this->assertSame('client-logos', $payload['media_folder']['slug']);
    $this->assertNull($payload['media_folder']['parent_id']);
  }

  #[Test]
  public function it_nests_a_folder_under_a_parent(): void
  {
    $parent = MediaFolder::query()->create(['name' => 'Brand', 'slug' => 'brand']);

    $payload = $this->store(['name' => 'Logos', 'parent_id' => $parent->id])->getData(true);

    $this->assertSame($parent->id, $payload['media_folder']['parent_id']);
  }

  #[Test]
  public function it_points_a_duplicate_at_the_folder_that_already_exists(): void
  {
    // A retrying tool would otherwise create a copy per attempt.
    $existing = MediaFolder::query()->create(['name' => 'Logos', 'slug' => 'logos']);

    $response = $this->store(['name' => 'logos']);

    $data = $response->getData(true);
    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('media_folder_exists', $data['code']);
    $this->assertSame($existing->id, $data['media_folder']['id']);
    $this->assertSame(1, MediaFolder::query()->count());
  }

  #[Test]
  public function the_same_name_is_allowed_under_a_different_parent(): void
  {
    $parent = MediaFolder::query()->create(['name' => 'Brand', 'slug' => 'brand']);
    MediaFolder::query()->create(['name' => 'Logos', 'slug' => 'logos']);

    $response = $this->store(['name' => 'Logos', 'parent_id' => $parent->id]);

    $this->assertSame(201, $response->getStatusCode());
    $this->assertSame(3, MediaFolder::query()->count());
  }

  #[Test]
  public function it_requires_a_name(): void
  {
    $response = $this->store(['slug' => 'orphan']);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame(0, MediaFolder::query()->count());
  }

  #[Test]
  public function it_lists_folders_with_their_media_counts(): void
  {
    MediaFolder::query()->create(['name' => 'Brand', 'slug' => 'brand']);

    $request = Request::create('/webadmin/api/media/folders', 'GET');
    $request->attributes->set('cms_api_token', $this->tokenWith([CmsApiTokenCapabilities::MEDIA_READ]));

    $payload = $this->app->make(InternalContentResourceController::class)->mediaFolders($request)->getData(true);

    $this->assertTrue($payload['ok']);
    $this->assertSame('Brand', $payload['media_folders'][0]['name']);
    $this->assertSame(0, $payload['media_folders'][0]['media_count']);
  }

  #[Test]
  public function the_routes_are_registered_before_the_media_id_route(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');

    $this->assertStringContainsString(
      "Route::post('/media/folders', [InternalContentResourceController::class, 'storeMediaFolder'])->middleware('internal-api.capability:media.write')",
      $routes
    );

    // /media/folders must win over /media/{media}, which only ordering gives it.
    $this->assertLessThan(
      strpos($routes, "Route::get('/media/{media}'"),
      strpos($routes, "Route::get('/media/folders'"),
    );
  }

  private function store(array $payload)
  {
    $request = Request::create('/webadmin/api/media/folders', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));

    return $this->app->make(InternalContentResourceController::class)->storeMediaFolder($request);
  }

  private function tokenWith(array $capabilities): CmsApiToken
  {
    return new CmsApiToken(['capabilities' => $capabilities]);
  }
}
