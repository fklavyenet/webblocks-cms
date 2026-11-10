<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalPagePublishController;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The Internal Content API could publish a page but never take one down:
 * archiving lived only in the admin panel's session-authenticated workflow
 * route, so an API tool that noticed a page should come offline had no way
 * to act. POST /pages/{page}/archive closes the loop under the same
 * content.publish capability that gates the opposite transition.
 */
class InternalPageArchiveApiTest extends TestCase
{
  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
    $app['config']->set('auth.providers.users.model', User::class);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_archives_a_published_page_and_captures_a_revision(): void
  {
    $page = $this->seedPage(Page::STATUS_PUBLISHED);

    $data = $this->invokeArchive($page)->getData(true);

    $this->assertTrue($data['ok']);
    $this->assertSame(Page::STATUS_PUBLISHED, $data['from_status']);
    $this->assertSame(Page::STATUS_ARCHIVED, $page->fresh()->status);
    $this->assertSame('workflow_changed', PageRevision::query()->findOrFail($data['revision_id'])->event);
  }

  #[Test]
  public function it_archives_an_in_review_page(): void
  {
    $page = $this->seedPage(Page::STATUS_IN_REVIEW);
    $page->update(['review_requested_at' => now()]);

    $this->assertTrue($this->invokeArchive($page)->getData(true)['ok']);
    $this->assertSame(Page::STATUS_ARCHIVED, $page->fresh()->status);
    $this->assertNull($page->fresh()->review_requested_at);
  }

  #[Test]
  public function panel_and_api_keep_the_last_publication_date_until_the_page_is_published_again(): void
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $this->withoutMiddleware(EnsureInstalled::class);
    $this->freezeSecond();
    $page = $this->seedPage(Page::STATUS_PUBLISHED);
    $firstPublication = $page->published_at;
    $actor = $this->cmsActor((new User)->forceFill(['role' => 'super_admin']));
    $workflow = route('admin.pages.workflow', $page);
    $api = '/webadmin/api/pages/'.$page->id;

    $this->actingAs($actor)->post($workflow, ['action' => 'archive'])->assertRedirect();
    $this->assertSame(Page::STATUS_ARCHIVED, $page->fresh()->status);
    $this->assertEquals($firstPublication, $page->fresh()->published_at);
    $this->postJson($workflow, ['action' => 'archive'])->assertUnprocessable()->assertJsonValidationErrors('action');

    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic workflow', capabilities: ['content.publish'])->plainToken;
    $this->withToken($token)->postJson($api.'/archive')->assertOk()->assertJsonPath('from_status', 'archived');
    $this->assertEquals($firstPublication, $page->fresh()->published_at);

    $this->travel(1)->days();
    $this->postJson($api.'/publish')->assertOk()->assertJsonPath('from_status', 'archived');
    $this->assertSame(Page::STATUS_PUBLISHED, $page->fresh()->status);
    $secondPublication = $page->fresh()->published_at;
    $this->assertTrue($secondPublication->greaterThan($firstPublication));
    $this->travel(1)->days();
    $this->postJson($api.'/publish')->assertOk()->assertJsonPath('from_status', 'published');
    $this->postJson($api.'/archive')->assertOk()->assertJsonPath('from_status', 'published');
    $this->assertEquals($secondPublication, $page->fresh()->published_at);
  }

  #[Test]
  public function re_archiving_an_archived_page_is_a_no_op_success(): void
  {
    $page = $this->seedPage(Page::STATUS_ARCHIVED);

    $data = $this->invokeArchive($page)->getData(true);

    $this->assertTrue($data['ok']);
    $this->assertSame(Page::STATUS_ARCHIVED, $data['from_status']);
    $this->assertSame(Page::STATUS_ARCHIVED, $page->fresh()->status);
  }

  #[Test]
  public function draft_pages_are_not_archivable(): void
  {
    $page = $this->seedPage(Page::STATUS_DRAFT);

    $this->expectException(ValidationException::class);

    $this->invokeArchive($page);
  }

  #[Test]
  public function staged_update_pages_are_rejected(): void
  {
    $page = $this->seedPage(Page::STATUS_DRAFT, [
      'staged_update' => ['type' => 'published_page_update', 'source_page_id' => 1],
    ]);

    $this->expectException(ValidationException::class);

    $this->invokeArchive($page);
  }

  #[Test]
  public function the_route_and_openapi_schema_advertise_it(): void
  {
    $routes = (string) file_get_contents(dirname(__DIR__, 2).'/routes/admin.php');
    $this->assertStringContainsString(
      "Route::post('/pages/{page}/archive', [InternalPagePublishController::class, 'archive'])->middleware('internal-api.capability:content.publish')",
      $routes
    );

    $discovery = $this->app->make(InternalApiDiscoveryController::class);
    $links = (new \ReflectionMethod($discovery, 'links'))->invoke($discovery);
    $this->assertSame('/webadmin/api/pages/{page}/archive', $links['page_archive']);

    $paths = $discovery->openapi()->getData(true)['paths'];
    $this->assertSame('content.publish', $paths['/pages/{page}/archive']['post']['x-required-capability']);
  }

  private function seedPage(string $status, array $settings = []): Page
  {
    $site = Site::query()->firstOrCreate(['handle' => 'test'], ['name' => 'Test', 'is_primary' => true]);
    Locale::query()->firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);

    return Page::query()->create([
      'site_id' => $site->id,
      'slug' => 'archive-me',
      'status' => $status,
      'published_at' => $status === Page::STATUS_PUBLISHED ? now() : null,
      'settings' => $settings === [] ? null : $settings,
    ]);
  }

  private function invokeArchive(Page $page)
  {
    $request = Request::create('/webadmin/api/pages/'.$page->id.'/archive', 'POST');

    return $this->app->make(InternalPagePublishController::class)->archive($request, $page);
  }
}
