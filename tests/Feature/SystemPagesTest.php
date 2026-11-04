<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\SystemPages\PrepareSystemPage;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemPage;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Search\SearchablePageResolver;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Sites\ExportImport\ImportDataMapper;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportDataBuilder;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;
use WebBlocks\Cms\Support\SystemPages\SystemPageRegistry;
use WebBlocks\Cms\Support\SystemPages\SystemPageRenderer;
use WebBlocks\Cms\Support\Updates\CmsUpdateMaintenance;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class SystemPagesTest extends TestCase
{
  private Site $site;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('cms.multisite.unknown_host_fallback', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    $this->withoutMiddleware(EnsureInstalled::class);
    app(CoreLayoutCatalogSyncer::class)->sync();
    Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $this->site = Site::create(['name' => 'Synthetic system site', 'handle' => 'system-test', 'is_primary' => true]);
    if (! class_exists('App\\Models\\User')) {
      class_alias(SystemPageActor::class, 'App\\Models\\User');
    }
    $this->actingAs(User::forceCreate(['name' => 'System editor', 'email' => 'system-editor@example.test', 'password' => bcrypt('test-password'), 'role' => 'super_admin']));
  }

  protected function tearDown(): void
  {
    @unlink(SystemPageRenderer::maintenancePath());
    parent::tearDown();
  }

  private function prepare(string $key): Page
  {
    return app(PrepareSystemPage::class)->execute($this->site, app(SystemPageRegistry::class)->find($key), auth()->user());
  }

  private function marker(Page $page, string $text): void
  {
    $main = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::firstOrCreate(['page_id' => $page->id, 'slot_type_id' => $main->id]);
    Block::create(['page_id' => $page->id, 'slot_type_id' => $main->id, 'type' => 'plain_text', 'title' => $text, 'content' => $text, 'status' => 'published']);
  }

  #[Test]
  public function preparing_is_explicit_idempotent_scoped_and_does_not_publish(): void
  {
    $this->get('/webadmin/pages?site='.$this->site->id)->assertOk()->assertSee('System pages');
    $this->assertSame(0, SystemPage::count());
    $this->post(route('admin.pages.system.store'), ['site_id' => $this->site->id, 'key' => 'core:error-404'])->assertRedirect();
    $page = SystemPage::firstOrFail()->page;
    $this->assertSame(Page::TYPE_SYSTEM, $page->page_type);
    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertSame($page->id, $this->prepare('core:error-404')->id);
    $this->assertNull($page->publicPath());
    $this->get('/webadmin/pages?site='.$this->site->id.'&type=system')->assertOk()->assertSee($page->title)->assertSee('Edit design');
    $this->get('/webadmin/pages/'.$page->id.'/edit')->assertOk()->assertSee('This is a system page');
    $this->get('/missing-before-publish')->assertNotFound()->assertSee('wb-error-shell', false);
    $this->post(route('admin.pages.system.store'), ['site_id' => $this->site->id, 'key' => 'unknown:screen'])->assertSessionHasErrors('key');
  }

  #[Test]
  public function a_published_404_uses_the_layout_without_gaining_a_normal_url_or_search_entry(): void
  {
    $page = $this->prepare('core:error-404');
    $this->marker($page, 'Custom error introduction');
    $page->update(['status' => Page::STATUS_PUBLISHED]);
    $this->get('/missing-after-publish')->assertNotFound()->assertSee('Custom error introduction')->assertSee('This page does not exist')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->get('/'.$page->slug)->assertNotFound();
    $this->assertFalse(app(SearchablePageResolver::class)->shouldIndex($page));
    $this->assertFalse(app(SearchablePageResolver::class)->query($this->site)->whereKey($page->id)->exists());
    $this->getJson('/missing-json')->assertNotFound()->assertDontSee('Custom error introduction');
    $this->get('/sitemap.xml')->assertOk()->assertDontSee($page->slug);
  }

  #[Test]
  public function a_system_design_uses_shared_slots_and_never_changes_its_identity_on_save(): void
  {
    $page = $this->prepare('core:error-404');
    $header = SlotType::firstOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published']);
    $shared = SharedSlot::create(['site_id' => $this->site->id, 'name' => 'Synthetic header', 'handle' => 'synthetic-header', 'slot_name' => 'header', 'public_shell' => 'docs', 'is_active' => true]);
    $source = app(SharedSlotSourcePageManager::class)->ensureFor($shared);
    $block = Block::create(['page_id' => $source->id, 'slot_type_id' => $header->id, 'type' => 'plain_text', 'title' => 'Shared system header', 'content' => 'Shared system header', 'status' => 'published']);
    $shared->slotBlocks()->create(['block_id' => $block->id, 'sort_order' => 0]);
    PageSlot::updateOrCreate(['page_id' => $page->id, 'slot_type_id' => $header->id], ['source_type' => 'shared_slot', 'shared_slot_id' => $shared->id]);
    $this->put('/webadmin/pages/'.$page->id, ['site_id' => $this->site->id, 'title' => 'Edited system title', 'slug' => $page->slug, 'public_shell' => 'docs'])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertSame(Page::TYPE_SYSTEM, $page->fresh()->page_type);
    $page = $page->fresh();
    $this->assertSame('docs', $page->publicShellPreset());
    $page->update(['status' => 'published']);
    $this->get('/missing-with-shared-header')->assertNotFound()->assertSee('Shared system header')->assertSee('wb-dashboard-shell', false);
  }

  #[Test]
  public function plugins_declare_screens_and_keep_runtime_forms_status_and_site_boundaries(): void
  {
    $plugin = PluginDefinition::make('synthetic')->label('Synthetic')->version('1.0.0')->systemPages([
      'result' => ['label' => 'webblocks-cms::system_pages.not_found', 'routes' => ['result']],
    ]);
    $registry = new PluginRegistry(['synthetic' => true]);
    $registry->register($plugin);
    app()->instance(PluginRegistry::class, $registry);
    $directory = sys_get_temp_dir().'/cms-system-page-fixture-'.getmypid();
    if (! is_dir($directory)) {
      mkdir($directory);
    }
    file_put_contents($directory.'/screen.blade.php', "@extends('webblocks-cms::layouts.public')\n@section('content')<main><form method=\"POST\" action=\"/synthetic-action\"><button>Runtime action</button></form></main>@endsection");
    View::addNamespace('system-fixture', $directory);
    Route::middleware('web')->get('/plugins/synthetic/result', fn () => response()->view('system-fixture::screen', ['site' => $this->site], 202)->header('X-Synthetic', 'retained'))->name('webblocks.plugins.synthetic.public.result');
    Route::getRoutes()->refreshNameLookups();
    try {
      $page = $this->prepare('synthetic:result');
      $this->marker($page, 'Plugin design introduction');
      $this->get('/plugins/synthetic/result')->assertStatus(202)->assertDontSee('Plugin design introduction');
      $page->update(['status' => 'published']);
      $this->get('/plugins/synthetic/result')->assertStatus(202)->assertHeader('X-Synthetic', 'retained')->assertSee('Plugin design introduction')->assertSee('Runtime action')->assertSee('action="/synthetic-action"', false);
      $page->slots()->whereHas('slotType', fn ($query) => $query->where('slug', 'main'))->delete();
      $runtime = $this->get('/plugins/synthetic/result')->assertStatus(202)->assertSee('Runtime action');
      $dom = new \DOMDocument;
      @$dom->loadHTML($runtime->getContent());
      $this->assertSame(0, (new \DOMXPath($dom))->query('//main//main')->length);
      $other = Site::create(['name' => 'Other synthetic', 'handle' => 'other-synthetic']);
      $this->assertNull(app(SystemPageRenderer::class)->design($other, 'synthetic:result'));
      $disabled = new PluginRegistry(['synthetic' => false]);
      $disabled->register($plugin);
      app()->instance(PluginRegistry::class, $disabled);
      $this->assertNull(app(SystemPageRenderer::class)->design($this->site, 'synthetic:result'));
    } finally {
      unlink($directory.'/screen.blade.php');
      rmdir($directory);
    }
  }

  #[Test]
  public function site_transfer_retains_the_screen_identity_but_does_not_publish_the_import(): void
  {
    $page = $this->prepare('core:error-404');
    $page->update(['status' => 'published']);
    $payload = app(SiteExportDataBuilder::class)->build($this->site, false);
    $this->assertSame('core:error-404', $payload['pages'][0]['system_page_key']);
    $path = tempnam(sys_get_temp_dir(), 'cms-system-transfer-');
    $archive = new \ZipArchive;
    $archive->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
    $archive->addFromString('synthetic.txt', 'Synthetic transfer');
    $archive->close();
    $archive->open($path);
    try {
      $import = SiteImport::create(['status' => 'validated', 'source_archive_name' => 'synthetic.zip', 'archive_disk' => 'local', 'archive_path' => 'synthetic.zip']);
      $options = new SiteImportOptions('Imported synthetic studio', 'imported-system-studio', null);
      $output = [];
      $target = app(ImportDataMapper::class)->import($import, $options, $archive, $payload, $output);
      $binding = SystemPage::query()->where('site_id', $target->id)->firstOrFail();
      $this->assertSame('core:error-404', $binding->key);
      $this->assertSame(Page::TYPE_SYSTEM, $binding->page->page_type);
      $this->assertSame(Page::STATUS_DRAFT, $binding->page->status);
      $this->assertNull($binding->page->publicPath());
    } finally {
      $archive->close();
      unlink($path);
    }
  }

  #[Test]
  public function a_user_without_site_access_cannot_prepare_a_system_design(): void
  {
    $user = User::forceCreate(['name' => 'Unassigned editor', 'email' => 'unassigned@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $this->actingAs($user)->post(route('admin.pages.system.store'), ['site_id' => $this->site->id, 'key' => 'core:error-404'])->assertForbidden();
    $this->assertSame(0, SystemPage::count());
  }

  #[Test]
  public function maintenance_is_rendered_before_shutdown_and_read_without_database_access(): void
  {
    $page = $this->prepare('core:error-503');
    $this->marker($page, 'Synthetic maintenance announcement');
    $page->update(['status' => 'published']);
    CmsUpdateMaintenance::arguments();
    $this->assertFileExists(SystemPageRenderer::maintenancePath());
    DB::enableQueryLog();
    $html = view(CmsUpdateMaintenance::VIEW)->render();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();
    $this->assertSame([], $queries);
    $this->assertStringContainsString('Synthetic maintenance announcement', $html);
    $this->assertStringContainsString('503', $html);
    $this->assertStringNotContainsString('wbCookiePreferences', $html);
    $page->update(['status' => 'archived']);
    CmsUpdateMaintenance::arguments();
    $this->assertFileDoesNotExist(SystemPageRenderer::maintenancePath());
  }
}

class SystemPageActor extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
    return 'user_id';
  }
}
