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
use WebBlocks\Cms\Queries\ListingViews\ListingViewQuery;
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

  #[Test]
  public function column_sort_links_keep_saved_view_identity_columns_and_filter_selection(): void
  {
    $id = $this->createView();
    Page::create(['site_id' => $this->site->id, 'title' => 'Synthetic page', 'slug' => 'synthetic-page', 'status' => 'draft']);
    $response = $this->followingRedirects()->get($this->applyUrl($id))->assertOk();
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new \DOMXPath($dom);
    $link = $xpath->query('//th[@data-wb-sort="status"]/a')->item(0);
    $this->assertNotNull($link);
    parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    $this->assertSame((string) $id, $query['saved_view']);
    $this->assertSame('updated,status', $query['columns']);
    $this->assertSame('draft', $query['status']);
    $response = $this->get($link->getAttribute('href'))->assertOk();
    $response->assertViewHas('listingView', fn ($view) => $view['changed'] && ! $view['blocked'] && $view['selected']->id === $id);
    $response->assertViewHas('filters', fn ($filters) => $filters['sort'] === 'status' && $filters['direction'] === 'asc');
    $this->assertSame('title', app(ListingViewQuery::class)->owned($this->actor, 'pages', $id)->filters['sort']);
  }

  private function index(array $query = [], string $listing = 'pages')
  {
    return $this->get(route('admin.'.$listing.'.index', $query));
  }

  #[Test]
  public function page_views_retain_id_and_block_count_sorting(): void
  {
    foreach (['id', 'blocks_count'] as $sort) {
      $payload = [...$this->payload('pages', ['sort' => $sort]), 'name' => 'Sorted by '.$sort];
      $id = $this->createView('pages', $payload);
      $this->app['session']->flush();
      $response = $this->followingRedirects()->get($this->applyUrl($id))->assertOk();
      $response->assertViewHas('filters', fn ($filters) => $filters['sort'] === $sort && $filters['direction'] === 'asc');
      $response->assertViewHas('listingView', fn ($view) => ! $view['blocked'] && ! $view['changed']);
    }
  }

  #[Test]
  public function page_list_shows_the_combined_edit_link_and_keeps_legacy_views_usable(): void
  {
    $page = Page::create(['site_id' => $this->site->id, 'title' => 'Synthetic page', 'slug' => 'synthetic-page', 'status' => 'draft']);
    $id = $this->createView('pages', $this->payload('pages', ['sort' => 'id'], 'status,view,blocks,updated'));
    DB::table('wbcms_listing_view_columns')->insert(['view_id' => $id, 'position' => 4, 'column_name' => 'id']);
    DB::table('wbcms_listing_view_columns')->where('view_id', $id)->where('column_name', 'view')->update(['position' => 5]);

    $response = $this->followingRedirects()->get($this->applyUrl($id))->assertOk()
      ->assertViewHas('listingView', fn ($view) => ! $view['blocked'] && ! $view['changed'])
      ->assertViewHas('listingColumns', ['view', 'status', 'blocks', 'updated']);
    $dom = new \DOMDocument;
    @$dom->loadHTML($response->getContent(), LIBXML_NONET);
    $xpath = new \DOMXPath($dom);
    $table = '//table[contains(@class, "wb-admin-pages-table")]';
    $row = $table.'/tbody/tr[1]';
    $heading = '//div[contains(@class, "wb-page-header-main")]';

    $this->assertSame('Pages: Synthetic studio', trim($xpath->evaluate('string('.$heading.'//h1)')));
    $this->assertSame('1', trim($xpath->evaluate('string('.$heading.'//*[@data-admin-page-count])')));
    $this->assertSame(1, $xpath->query($table.'/thead/tr/th[1]//input[@data-wb-admin-select-all-visible]')->length);
    $this->assertSame(1, $xpath->query($row.'/td[1]//input[@data-wb-admin-row-select]')->length);
    $this->assertSame('View', trim($xpath->evaluate('string('.$table.'/thead/tr/th[2])')));
    $this->assertSame(route('admin.pages.preview', $page), $xpath->evaluate('string('.$row.'/td[2]/a/@href)'));
    $this->assertSame('Title – ID', trim($xpath->evaluate('string('.$table.'/thead//th[@data-wb-sort="title"]/a)')));
    $this->assertSame('Blocks', trim($xpath->evaluate('string('.$table.'/thead//th[@data-wb-sort="blocks_count"]/a)')));
    $this->assertSame(0, $xpath->query($table.'/thead//th[@data-wb-sort="id"]')->length);
    $title = $xpath->query($row.'//div[contains(@class, "wb-admin-pages-title-row")]/strong/a')->item(0);
    $this->assertSame('Synthetic page – '.$page->id, $title->textContent);
    $editUrl = route('admin.pages.edit', ['page' => $page, 'return_url' => $response->viewData('pageReturnUrl')]);
    $this->assertSame($editUrl, $title->getAttribute('href'));
    $this->assertSame($editUrl, $xpath->evaluate('string('.$row.'//a[contains(@class, "wb-action-btn-edit")]/@href)'));
    $this->assertSame(3, $xpath->query('//div[contains(@class, "wb-card-header") and contains(@class, "wb-justify-end")]/a')->length);

    $this->index(['site' => $this->site->id, 'search' => 'Missing'])->assertOk()
      ->assertSee('No pages found')
      ->assertSee('wb-card-header wb-flex-wrap wb-gap-2 wb-justify-end', false);
  }

  #[Test]
  public function top_bar_site_choices_stay_the_same_across_admin_screens(): void
  {
    $this->site->update(['domain' => 'first.example.test']);
    $other = Site::create(['name' => 'Other site', 'handle' => 'other', 'domain' => 'other.example.test']);
    $third = Site::create(['name' => 'Third site', 'handle' => 'third', 'domain' => 'third.example.test']);

    $pages = $this->index(['site' => $this->site->id])->assertOk();
    $dashboard = $this->get(route('admin.dashboard'))->assertOk();
    $sites = $this->get(route('admin.sites.index'))->assertOk();
    $settings = $this->get(route('admin.sites.edit', $other))->assertOk();
    $system = $this->get(route('admin.system.settings.edit', ['tab' => 'appearance']))->assertOk();
    $system->assertSee('id="system-settings-appearance-panel"', false)
      ->assertSee('data-wb-admin-panel-preset', false)
      ->assertSee('data-wb-admin-panel-accent', false)
      ->assertSee('wb-icon-chevron-down wb-user-menu-chevron', false)
      ->assertDontSee('id="admin-theme-menu"', false);

    $dom = new \DOMDocument;
    foreach ([$pages, $dashboard, $sites, $settings, $system] as $response) {
      @$dom->loadHTML($response->getContent(), LIBXML_NONET);
      $xpath = new \DOMXPath($dom);
      $this->assertSame(0, $xpath->query('//a[@data-admin-public-site]')->length);
      $trigger = $xpath->query('//button[@data-wb-toggle="dropdown" and @aria-controls="admin-public-sites"]')->item(0);
      $this->assertSame(1, $xpath->query('./i[contains(@class, "wb-icon-globe")]', $trigger)->length);
      $this->assertSame(1, $xpath->query('./i[contains(@class, "wb-icon-chevron-down")]', $trigger)->length);

      $links = $xpath->query('//*[@id="admin-public-sites"]/a[@target="_blank"]');
      $this->assertSame(3, $links->length);
      foreach ([$this->site, $other, $third] as $index => $site) {
        $this->assertSame($site->name, trim($links->item($index)->textContent));
        $this->assertSame($site->publicHomeUrl(), $links->item($index)->getAttribute('href'));
      }
    }

    @$dom->loadHTML($system->getContent(), LIBXML_NONET);
    $xpath = new \DOMXPath($dom);
    $this->assertSame('true', $xpath->evaluate('string(//button[@data-wb-tab="system-settings-appearance-panel"]/@aria-selected)'));
    @$dom->loadHTML($settings->getContent(), LIBXML_NONET);
    $xpath = new \DOMXPath($dom);
    $this->assertSame(1, $xpath->query('//*[@data-admin-form-actions-danger and contains(@class, "wb-ms-auto")]')->length);

    $this->actingAs($this->cmsActor($this->user(), [$this->site->id]));
    $all = $this->index(['site' => 'all'])->assertOk();
    @$dom->loadHTML($all->getContent(), LIBXML_NONET);
    $xpath = new \DOMXPath($dom);
    $this->assertSame(0, $xpath->query('//*[@id="admin-public-sites"]')->length);
    $link = $xpath->query('//a[@data-admin-public-site and @target="_blank"]')->item(0);
    $this->assertSame($this->site->name, trim($link->textContent));
    $this->assertSame($this->site->publicHomeUrl(), $link->getAttribute('href'));
    $this->assertSame(0, $xpath->query('./i[contains(@class, "wb-icon-chevron-down")]', $link)->length);
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
    $this->assertDatabaseCount('wbcms_listing_view_filters', 6);
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
  public function media_search_is_literal_by_default_and_legacy_views_keep_pattern_search(): void
  {
    $folder = MediaFolder::create(['name' => 'Offers', 'slug' => 'offers']);
    $titles = ['Offer %_2026', 'Offer winterX2026'];
    $ids = [];
    foreach ($titles as $index => $title) {
      $file = 'offer-'.$index.'.png';
      $ids[] = Media::create(['disk' => 'public', 'path' => $file, 'filename' => $file, 'title' => $title, 'kind' => 'image', 'folder_id' => $folder->id, 'uploaded_by' => $this->actor->id])->id;
    }
    $search = ['search' => 'Offer %_2026'];
    $literal = $this->get(route('admin.media.index', $search))->assertOk()->assertSee('name="search_mode"', false);
    $this->assertSame([$ids[0]], $literal->viewData('media')->modelKeys());

    $pattern = $this->get(route('admin.media.index', $search + ['search_mode' => 'pattern']))->assertOk();
    $this->assertEqualsCanonicalizing($ids, $pattern->viewData('media')->modelKeys());
    $pattern->assertSee(e(route('admin.media.index', $search + ['search_mode' => 'pattern', 'folder_id' => $folder->id])), false);

    $id = $this->createView('media', $this->payload('media', $search, 'preview'));
    $newView = $this->followingRedirects()->get($this->applyUrl($id, 'media'))->assertOk();
    $this->assertSame([$ids[0]], $newView->viewData('media')->modelKeys());

    DB::table('wbcms_listing_view_filters')->where('view_id', $id)->where('filter_name', 'search_mode')->delete();
    $legacy = $this->get($this->applyUrl($id, 'media'))->assertRedirect();
    $this->assertStringContainsString('search_mode=pattern', $legacy->headers->get('Location'));
    $legacyView = $this->get($legacy->headers->get('Location'))->assertOk();
    $this->assertEqualsCanonicalizing($ids, $legacyView->viewData('media')->modelKeys());
    $this->assertFalse($legacyView->viewData('listingView')['changed']);
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
    $this->assertDatabaseCount('wbcms_listing_view_filters', 6);
  }

  #[Test]
  public function clear_filters_standard_view_and_column_forms_keep_native_navigation(): void
  {
    $id = $this->createView();
    $redirect = $this->get($this->applyUrl($id))->assertRedirect();
    $this->get($redirect->headers->get('Location'))->assertOk();
    $this->index(['reset' => 1])->assertRedirect(route('admin.pages.index'));
    $this->index()->assertOk()->assertViewHas('listingColumns', ['view', 'blocks', 'status', 'updated']);
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
