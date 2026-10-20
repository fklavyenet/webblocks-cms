<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\ListingViews\ListingViewSchema;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class PersonalListingViewsTest extends TestCase
{
  private User $actor;

  private Site $site;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    if (! class_exists(User::class)) {
      class_alias(PersonalListingViewActor::class, User::class);
    }
    $this->actor = $this->user('super_admin');
    $this->actingAs($this->actor);
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $this->site = Site::create(['name' => 'Synthetic studio', 'handle' => 'listing-studio', 'is_primary' => true]);
  }

  private function user(string $role = 'editor'): User
  {
    return User::forceCreate(['name' => 'Synthetic operator', 'email' => uniqid('view-').'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
  }

  private function payload(string $listing = 'pages', array $filters = [], string $columns = 'updated,status'): array
  {
    return ['name' => 'My view', 'operation' => 'save', 'columns' => $columns, 'filters' => $listing === 'pages'
      ? ['site' => (string) $this->site->id, 'search' => '', 'status' => 'draft', 'sort' => 'title', 'direction' => 'asc', ...$filters]
      : ['folder_id' => '', 'search' => '', 'kind' => 'image', 'usage' => 'unused', 'sort' => 'filename', 'direction' => 'asc', 'view' => 'grid', ...$filters]];
  }

  private function createView(string $listing = 'pages', ?array $payload = null): int
  {
    $this->post(route('admin.listing-views.store', ['listing' => $listing]), $payload ?? $this->payload())->assertRedirect();

    return (int) DB::table('wbcms_listing_views')->where('user_id', $this->actor->id)->where('listing', $listing)->orderByDesc('id')->value('id');
  }

  private function applyUrl(int $id, string $listing = 'pages'): string
  {
    return route('admin.listing-views.apply', ['listing' => $listing, 'listingView' => $id]);
  }

  private function index(array $query = [], string $listing = 'pages')
  {
    return $this->get(route('admin.'.$listing.'.index', $query));
  }

  #[Test]
  public function page_views_survive_sessions_reset_pagination_and_preserve_results_and_permissions(): void
  {
    foreach (['Beta', 'Alpha'] as $title) {
      Page::create(['site_id' => $this->site->id, 'title' => $title, 'slug' => strtolower($title), 'status' => 'draft']);
    }
    Page::create(['site_id' => $this->site->id, 'title' => 'Published', 'slug' => 'published', 'status' => 'published']);
    $before = DB::table('wbcms_page_revisions')->count();
    $payload = $this->payload();
    $baseline = $this->index($payload['filters'])->assertOk();
    $ids = $baseline->viewData('pages')->modelKeys();
    $id = $this->createView();
    $this->app['session']->flush();
    $this->actingAs($this->actor->fresh());
    $redirect = $this->get($this->applyUrl($id).'?page=99')->assertRedirect();
    $this->assertStringNotContainsString('page=99', $redirect->headers->get('Location'));
    $response = $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('The applied configuration matches this view.');
    $this->assertSame($ids, $response->viewData('pages')->modelKeys());
    $this->assertSame($baseline->viewData('deletablePageIds'), $response->viewData('deletablePageIds'));
    $this->assertSame(['updated', 'status'], $response->viewData('listingColumns'));
    $this->assertSame(1, $response->viewData('pages')->currentPage());
    $this->assertSame($before, DB::table('wbcms_page_revisions')->count());
    $this->assertDatabaseCount('wbcms_pages', 3);
    $this->assertDatabaseCount('wbcms_listing_view_filters', 5);
    $this->assertDatabaseHas('wbcms_listing_view_columns', ['view_id' => $id, 'position' => 0, 'column_name' => 'updated']);
    $this->index(['site' => $this->site->id, 'saved_view' => $id, 'columns' => 'status', 'status' => 'published'])->assertOk()->assertSee('The applied configuration differs');
  }

  #[Test]
  public function media_views_preserve_grid_folder_usage_and_return_navigation(): void
  {
    $folder = MediaFolder::create(['name' => 'Synthetic images', 'slug' => 'synthetic-images']);
    Media::create(['filename' => 'example.png', 'original_name' => 'example.png', 'title' => 'Example', 'kind' => 'image', 'folder_id' => $folder->id, 'disk' => 'public', 'path' => 'synthetic/example.png', 'mime_type' => 'image/png', 'size' => 24, 'uploaded_by' => $this->actor->id]);
    $payload = $this->payload('media', ['folder_id' => (string) $folder->id], 'usage,preview');
    $baseline = $this->index($payload['filters'], 'media')->assertOk();
    $id = $this->createView('media', $payload);
    $redirect = $this->get($this->applyUrl($id, 'media'))->assertRedirect();
    $response = $this->get($redirect->headers->get('Location'))->assertOk();
    $this->assertSame($baseline->viewData('media')->modelKeys(), $response->viewData('media')->modelKeys());
    $this->assertSame('grid', $response->viewData('viewMode'));
    $this->assertSame(['usage', 'preview'], $response->viewData('listingColumns'));
    $response = $this->index([...$payload['filters'], 'view' => 'list', 'columns' => 'usage,preview', 'saved_view' => $id], 'media')->assertOk()->assertSee('The applied configuration differs');
    $html = $response->getContent();
    $this->assertStringContainsString('saved_view%3D'.$id, $html);
    $this->assertDatabaseCount('wbcms_media', 1);
  }

  #[Test]
  public function views_are_private_and_listing_bound_in_every_endpoint(): void
  {
    $id = $this->createView();
    $other = $this->user('super_admin');
    $this->actingAs($other);
    $this->get($this->applyUrl($id))->assertNotFound();
    $this->index(['site' => 'all', 'saved_view' => $id])->assertNotFound();
    $this->put(route('admin.listing-views.update', ['listing' => 'pages', 'listingView' => $id]), $this->payload())->assertNotFound();
    $this->delete(route('admin.listing-views.destroy', ['listing' => 'pages', 'listingView' => $id]))->assertNotFound();
    $this->index(['site' => 'all'])->assertOk()->assertDontSee('>My view</a>', false);
    $this->actingAs($this->actor);
    $this->get($this->applyUrl($id, 'media'))->assertNotFound();
    $this->post('/webadmin/listing-views/users', $this->payload())->assertNotFound();
  }

  #[Test]
  public function rename_update_and_delete_change_only_the_owned_view(): void
  {
    $id = $this->createView();
    $url = route('admin.listing-views.update', ['listing' => 'pages', 'listingView' => $id]);
    $filters = DB::table('wbcms_listing_view_filters')->get()->all();
    $this->put($url, ['name' => 'Renamed view', 'operation' => 'rename'])->assertRedirect();
    $this->assertEquals($filters, DB::table('wbcms_listing_view_filters')->get()->all());
    $this->put($url, [...$this->payload('pages', ['status' => 'archived'], ''), 'name' => 'Renamed view'])->assertRedirect();
    $this->assertDatabaseHas('wbcms_listing_view_filters', ['view_id' => $id, 'filter_name' => 'status', 'filter_value' => 'archived']);
    $this->assertDatabaseCount('wbcms_listing_view_columns', 0);
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk()->assertViewHas('listingColumns', []);
    $this->delete(route('admin.listing-views.destroy', ['listing' => 'pages', 'listingView' => $id]))->assertRedirect();
    $this->assertDatabaseCount('wbcms_listing_views', 0);
    $this->assertDatabaseCount('wbcms_listing_view_filters', 0);
  }

  #[Test]
  public function deleted_sites_and_folders_block_application_until_explicit_correction(): void
  {
    $id = $this->createView();
    $this->site->delete();
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $response = $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('No results are shown.');
    $this->assertTrue($response->viewData('listingView')['blocked']);
    $this->assertSame(0, $response->viewData('pages')->total());
    $folder = MediaFolder::create(['name' => 'Removed folder', 'slug' => 'removed']);
    $mediaId = $this->createView('media', $this->payload('media', ['folder_id' => (string) $folder->id], 'preview'));
    $folder->delete();
    $redirect = $this->get($this->applyUrl($mediaId, 'media'))->assertRedirect();
    $response = $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('No results are shown.');
    $this->assertTrue($response->viewData('listingView')['blocked']);
    $this->assertSame(0, $response->viewData('media')->total());
    $this->put(route('admin.listing-views.update', ['listing' => 'media', 'listingView' => $mediaId]), $this->payload('media', [], 'usage'))->assertRedirect();
    $redirect = $this->get($this->applyUrl($mediaId, 'media'))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('The applied configuration matches this view.');
  }

  #[Test]
  public function removed_filter_and_column_definitions_never_silently_broaden_a_saved_query(): void
  {
    $id = $this->createView();
    DB::table('wbcms_listing_view_filters')->where('view_id', $id)->where('filter_name', 'status')->update(['filter_name' => 'retired_status']);
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('No results are shown.');
    DB::table('wbcms_listing_view_filters')->where('view_id', $id)->where('filter_name', 'retired_status')->update(['filter_name' => 'status']);
    DB::table('wbcms_listing_view_columns')->where('view_id', $id)->where('position', 0)->update(['column_name' => 'retired_column']);
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('No results are shown.');
    $this->getJson(route('admin.pages.index', ['site' => 'all', 'columns' => '../pages/edit']))->assertUnprocessable();
  }

  #[Test]
  public function read_only_roles_can_manage_views_but_revoked_site_access_blocks_saved_scope(): void
  {
    $operator = $this->user();
    $otherSite = Site::create(['name' => 'Other studio', 'handle' => 'other-studio']);
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Listing reader', 'permissions' => ['cms.panel.access', 'cms.pages.read', 'cms.media.read']]);
    $operator->sites()->attach([$this->site->id => ['role_id' => $role->id], $otherSite->id => ['role_id' => $role->id]]);
    $this->actor = $operator;
    $this->actingAs($operator);
    $id = $this->createView();
    $operator->sites()->detach($this->site->id);
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $response = $this->get($redirect->headers->get('Location'))->assertOk()->assertSee('No results are shown.');
    $this->assertTrue($response->viewData('listingView')['blocked']);
    $operator->sites()->detach();
    $this->get($this->applyUrl($id))->assertForbidden();
    $this->post(route('admin.listing-views.store', ['listing' => 'pages']), $this->payload())->assertForbidden();
    $this->assertDatabaseCount('wbcms_listing_views', 1);
  }

  #[Test]
  public function unsupported_parameters_duplicate_columns_names_and_limits_are_rejected(): void
  {
    $url = route('admin.listing-views.store', ['listing' => 'pages']);
    $this->postJson($url, $this->payload('pages', ['sql' => 'untrusted']))->assertUnprocessable();
    $this->postJson($url, $this->payload('pages', ['sort' => 'secret']))->assertUnprocessable();
    $this->postJson($url, $this->payload('pages', ['site' => '999999']))->assertUnprocessable();
    $this->postJson($url, $this->payload('pages', ['search' => str_repeat('x', 256)]))->assertUnprocessable();
    $this->postJson($url, $this->payload('pages', [], 'status,status'))->assertUnprocessable();
    $this->postJson($url, [...$this->payload(), 'name' => str_repeat('x', 81)])->assertUnprocessable();
    $this->createView();
    $this->postJson($url, $this->payload())->assertUnprocessable();
    for ($i = 1; $i < 50; $i++) {
      DB::table('wbcms_listing_views')->insert(['user_id' => $this->actor->id, 'listing' => 'pages', 'name' => 'View '.$i, 'created_at' => now(), 'updated_at' => now()]);
    }
    $this->postJson($url, [...$this->payload(), 'name' => 'Too many'])->assertUnprocessable();
    $this->assertDatabaseCount('wbcms_listing_views', 50);
    $this->assertDatabaseCount('wbcms_listing_view_filters', 5);
  }

  #[Test]
  public function clear_filters_standard_view_and_column_forms_keep_native_navigation(): void
  {
    $id = $this->createView();
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk();
    $this->index(['reset' => 1])->assertRedirect(route('admin.pages.index'));
    $this->index()->assertOk()->assertViewHas('listingColumns', ['id', 'view', 'blocks', 'status', 'updated']);
    $response = $this->index(['site' => 'all', 'columns' => ['status', 'updated', '', '', '']])->assertOk();
    $this->assertSame(['status', 'updated'], $response->viewData('listingColumns'));
    $this->assertStringContainsString('name="columns" value="status,updated"', $response->getContent());
    $this->index(['site' => 'all', 'columns' => 'none'])->assertOk()->assertViewHas('listingColumns', []);
    $this->index(['columns' => 'none'], 'media')->assertOk()->assertViewHas('listingColumns', []);
  }

  #[Test]
  public function update_migrations_are_idempotent_and_user_deletion_cascades_without_json_storage(): void
  {
    $id = $this->createView();
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_10_20_150000_create_listing_views.php';
    $migration->up();
    ListingViewSchema::migrate();
    $this->assertDatabaseHas('wbcms_listing_views', ['id' => $id]);
    $this->assertSame(['view_id', 'filter_name', 'filter_value'], Schema::getColumnListing('wbcms_listing_view_filters'));
    $this->assertSame(['view_id', 'position', 'column_name'], Schema::getColumnListing('wbcms_listing_view_columns'));
    $this->actor->delete();
    $this->assertDatabaseCount('wbcms_listing_views', 0);
    $this->assertDatabaseCount('wbcms_listing_view_filters', 0);
    $this->assertDatabaseCount('wbcms_listing_view_columns', 0);
  }
}

class PersonalListingViewActor extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
    return 'user_id';
  }
}
