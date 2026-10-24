<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\ContactMessage;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Queries\AdminBlockPageOptions;
use WebBlocks\Cms\Queries\SiteDeletionAvailabilityQuery;
use WebBlocks\Cms\Support\Sites\SiteDeleteService;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminBoundedListingsTest extends TestCase
{
  private User $admin;

  private Locale $locale;

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
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->admin = $this->cmsActor(User::forceCreate(['name' => 'Synthetic operator', 'email' => 'admin@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]));
    $this->actingAs($this->admin);
    $this->locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $this->site = Site::create(['name' => 'Northstar', 'handle' => 'northstar', 'domain' => 'northstar.example.test']);
  }

  public static function sizes(): array
  {
    return [[20], [200], [2000]];
  }

  private function pages(int $count): array
  {
    $type = BlockType::create(['name' => 'Text', 'slug' => 'text', 'status' => 'published']);
    $first = (int) DB::table('wbcms_pages')->max('id') + 1;
    $pages = $translations = $blocks = [];
    for ($i = 0; $i < $count; $i++) {
      $id = $first + $i;
      $pages[] = ['id' => $id, 'site_id' => $this->site->id, 'page_type' => 'default', 'status' => 'draft'];
      $translations[] = ['page_id' => $id, 'site_id' => $this->site->id, 'locale_id' => $this->locale->id, 'name' => 'Synthetic item '.$i, 'slug' => 'synthetic-'.$i, 'path' => '/synthetic-'.$i];
      $blocks[] = ['page_id' => $id, 'block_type_id' => $type->id, 'type' => 'text', 'status' => 'draft'];
    }
    foreach (array_chunk($pages, 200) as $chunk) {
      DB::table('wbcms_pages')->insert($chunk);
    }
    foreach (array_chunk($translations, 200) as $chunk) {
      DB::table('wbcms_page_translations')->insert($chunk);
    }
    foreach (array_chunk($blocks, 200) as $chunk) {
      DB::table('wbcms_blocks')->insert($chunk);
    }

    return range($first, $first + $count - 1);
  }

  #[Test]
  #[DataProvider('sizes')]
  public function menu_lists_keep_query_and_page_option_counts_bounded_as_content_grows(int $size): void
  {
    $this->pages($size);
    DB::flushQueryLog();
    DB::enableQueryLog();
    $blocks = $this->get(route('admin.blocks.index'))->assertOk();
    $blockQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $this->assertLessThanOrEqual(120, $blockQueries);
    $this->assertCount(min(AdminBlockPageOptions::LIMIT, $size), $blocks->viewData('filterPages'));
    $this->assertCount(15, $blocks->viewData('blocks')->items());
    DB::flushQueryLog();
    DB::enableQueryLog();
    $sites = $this->get(route('admin.sites.index'))->assertOk();
    $siteQueries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $this->assertLessThanOrEqual(80, $siteQueries);
    $this->assertSame([], $sites->viewData('exportablePages'));
    $sites->assertDontSee('id="siteIndexExportModal"', false)->assertDontSee('Synthetic item 0');
  }

  #[Test]
  public function page_option_search_finds_old_translated_pages_and_retains_selection_sort_and_pagination(): void
  {
    $ids = $this->pages(80);
    $first = $ids[0];
    $selected = $this->get(route('admin.blocks.index', ['site' => $this->site->id, 'page_id' => $first, 'sort' => 'id', 'direction' => 'asc']))->assertOk();
    $this->assertArrayHasKey($first, $selected->viewData('filterPages'));
    $this->assertCount(51, $selected->viewData('filterPages'));
    $this->assertSame($first, $selected->viewData('blocks')->sole()->page_id);
    $byId = $this->get(route('admin.blocks.index', ['page_search' => '#'.$first, 'sort' => 'id', 'direction' => 'asc']))->assertOk();
    $this->assertSame([$first], array_keys($byId->viewData('filterPages')));
    $nextOptions = $this->get(route('admin.blocks.index', ['page_options_page' => 2, 'page' => 2, 'sort' => 'id', 'direction' => 'asc']))->assertOk();
    $this->assertCount(30, $nextOptions->viewData('filterPages'));
    $this->assertArrayHasKey($first, $nextOptions->viewData('filterPages'));
    $this->assertSame(2, $nextOptions->viewData('blocks')->currentPage());
    $this->assertSame(2, $nextOptions->viewData('pageOptionsPaginator')->currentPage());
    $this->get(route('admin.blocks.index', ['page_options_page' => 99, 'sort' => 'id', 'direction' => 'asc']))
      ->assertRedirect(route('admin.blocks.index', ['page_options_page' => 2, 'sort' => 'id', 'direction' => 'asc']));
    $tr = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $this->site->locales()->attach($tr, ['is_enabled' => true]);
    $page = Page::findOrFail($first);
    $page->translations()->create(['site_id' => $this->site->id, 'locale_id' => $tr->id, 'name' => 'Özel rehber', 'slug' => 'ozel-rehber', 'path' => '/ozel-rehber']);
    $this->admin->forceFill(['admin_locale' => 'tr'])->save();
    $translated = $this->get(route('admin.blocks.index', ['page_search' => 'Özel rehber', 'page' => 2, 'sort' => 'id', 'direction' => 'asc']))->assertOk();
    $this->assertSame([$first], array_keys($translated->viewData('filterPages')));
    $this->assertSame(2, $translated->viewData('blocks')->currentPage());
    $translated->assertSee('Sayfa seçeneklerini ara')->assertSee('name="page_search"', false);
    $this->getJson(route('admin.blocks.index', ['page_search' => str_repeat('x', 201)]))->assertUnprocessable();
    $this->getJson(route('admin.blocks.index', ['page_search' => ['invalid']]))->assertUnprocessable();
  }

  #[Test]
  public function page_choices_are_site_and_actor_scoped_including_the_retained_selection(): void
  {
    $ids = $this->pages(2);
    $other = Site::create(['name' => 'Harbor', 'handle' => 'harbor']);
    $foreign = Page::create(['site_id' => $other->id, 'title' => 'Private foreign page', 'slug' => 'foreign', 'status' => 'draft']);
    Block::create(['page_id' => $foreign->id, 'type' => 'text', 'status' => 'draft']);
    $actor = $this->cmsActor(User::forceCreate(['name' => 'Reader', 'email' => 'reader@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]), []);
    $role = CmsRole::create(['name' => 'Reader', 'handle' => 'custom-reader']);
    foreach (['cms.panel.access', 'cms.pages.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $actor->sites()->attach($this->site, ['role_id' => $role->id]);
    $query = app(AdminBlockPageOptions::class);
    $options = $query->options($actor, ['site' => '', 'page_search' => '', 'page_id' => (string) $foreign->id]);
    $this->assertEqualsCanonicalizing($ids, array_keys($options));
    $this->assertSame([], $query->options($actor, ['site' => (string) $other->id, 'page_search' => '', 'page_id' => '']));
    $this->actingAs($actor)->get(route('admin.blocks.index'))->assertForbidden();
  }

  #[Test]
  public function export_picker_is_loaded_only_for_the_selected_site_and_delete_affordances_match_full_guards(): void
  {
    $this->pages(3);
    $other = Site::create(['name' => 'Harbor', 'handle' => 'harbor']);
    $foreign = Page::create(['site_id' => $other->id, 'title' => 'Foreign export sentinel', 'slug' => 'foreign', 'status' => 'draft']);
    $response = $this->get(route('admin.sites.index', ['modal' => 'export-site', 'export_site' => $this->site->id]))->assertOk();
    $this->assertSame([$this->site->id], array_keys($response->viewData('exportablePages')));
    $this->assertCount(3, $response->viewData('exportablePages')[$this->site->id]);
    $response->assertSee('id="siteIndexExportModal"', false)->assertDontSee('Foreign export sentinel');
    $query = app(SiteDeletionAvailabilityQuery::class);
    $this->assertSame([$other->id], $query->deletableIds(Site::all(), 2));
    $this->assertTrue(app(SiteDeleteService::class)->inspect($other)->canDelete);
    $block = Block::create(['page_id' => $foreign->id, 'type' => 'text', 'status' => 'draft']);
    // A message may reference another page or no page: block ownership must also block deletion.
    ContactMessage::create(['block_id' => $block->id, 'page_id' => Page::where('site_id', $this->site->id)->value('id'), 'name' => 'Synthetic visitor', 'email' => 'visitor@example.test', 'message' => 'Synthetic message']);
    $this->assertSame([], $query->deletableIds(Site::all(), 2));
    $this->assertFalse(app(SiteDeleteService::class)->inspect($other)->canDelete);
    $this->get(route('admin.sites.delete', $other))->assertOk()->assertSee('Harbor');
  }
}
