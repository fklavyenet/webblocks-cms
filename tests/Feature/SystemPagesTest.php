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
use WebBlocks\Cms\Support\Blocks\CoreBlockTypeCatalogSyncer;
use WebBlocks\Cms\Support\Catalog\CoreLayoutCatalogSyncer;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\Search\SearchablePageResolver;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Support\Sites\ExportImport\ImportDataMapper;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteExportDataBuilder;
use WebBlocks\Cms\Support\Sites\ExportImport\SiteImportOptions;
use WebBlocks\Cms\Support\SystemPages\SystemPageDefinition;
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
    app(CoreBlockTypeCatalogSyncer::class)->sync();
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
    $page->blocks()->update(['status' => 'published']);
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
    $page->blocks()->update(['status' => 'published']);
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

  #[Test]
  public function defaults_are_editable_native_blocks_and_preview_uses_the_same_content_without_writes(): void
  {
    $page = $this->prepare('core:error-404');
    $this->assertSame(['hero', 'button_link'], $page->blocks()->orderBy('id')->pluck('type')->all());
    $this->assertSame(['draft'], $page->blocks()->distinct()->pluck('status')->all());
    $hero = $page->blocks()->where('type', 'hero')->firstOrFail();
    $button = $page->blocks()->where('type', 'button_link')->firstOrFail();
    $this->assertSame($hero->id, $button->parent_id);
    $this->assertSame('/', $button->buttonLinkUrl());
    $this->assertNull($hero->getRawOriginal('content'));
    $main = $page->slots()->where('slot_type_id', $hero->slot_type_id)->firstOrFail();
    $edit = route('admin.pages.slots.blocks', [$page, $main, 'edit' => $hero->id, 'locale' => 'en']);
    $this->get($edit)->assertOk()->assertSee('value="This page does not exist"', false)
      ->assertSee('The page you are looking for may have moved or never existed.');
    $count = Block::count();
    $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()
      ->assertSee('This page does not exist')->assertSee('Back to homepage')
      ->assertDontSee('The feature’s form or result appears here');
    $this->assertSame($count, Block::count());
    $this->put(route('admin.blocks.update', $hero), [
      'page_id' => $page->id, 'block_type_id' => $hero->block_type_id, 'slot_type_id' => $hero->slot_type_id,
      'sort_order' => 0, 'status' => 'draft', 'locale' => 'en',
      'title' => 'Our edited error title', 'content' => 'Our edited explanation', 'layout' => 'left', 'title_tag' => 'h1',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $this->get($edit)->assertOk()->assertSee('value="Our edited error title"', false)->assertSee('Our edited explanation');
    $button->textTranslations()->firstOrFail()->update(['title' => 'Return to our home']);
    $page->blocks()->update(['status' => 'published']);
    $page->update(['status' => 'published']);
    $response = $this->get('/missing-edited-error')->assertNotFound()->assertSee('Our edited error title')
      ->assertSee('Our edited explanation')->assertSee('Return to our home')->assertDontSee('This page does not exist');
    $this->assertSame(1, substr_count($response->getContent(), 'Our edited error title</h1>'));
  }

  #[Test]
  public function preview_reflects_saved_block_removal_without_creating_replacement_content(): void
  {
    $page = $this->prepare('core:error-404');
    $page->blocks()->delete();
    $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()
      ->assertDontSee('This page does not exist')->assertDontSee('Back to homepage');
    $this->assertSame(0, $page->blocks()->count());
  }

  #[Test]
  public function public_errors_use_published_blocks_and_keep_the_builtin_error_until_they_are_published(): void
  {
    $page = $this->prepare('core:error-404');
    $page->blocks()->where('type', 'hero')->firstOrFail()->textTranslations()->firstOrFail()->update(['title' => 'Draft error content']);
    $page->update(['status' => 'published']);
    $this->get('/missing-with-draft-blocks')->assertNotFound()->assertSee('wb-error-shell', false)->assertDontSee('Draft error content');
    $page->blocks()->update(['status' => 'published']);
    $this->get('/missing-with-published-blocks')->assertNotFound()->assertSee('Draft error content')->assertDontSee('wb-error-shell', false);
  }

  #[Test]
  public function maintenance_preview_has_editable_default_copy_without_an_unrelated_form_placeholder(): void
  {
    $page = $this->prepare('core:error-503');
    $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()
      ->assertSee('We are carrying out a brief update')->assertSee('We are updating our website.')
      ->assertDontSee('The feature’s form or result appears here');
    $this->assertFalse(app()->isDownForMaintenance());
    $page->blocks()->where('type', 'hero')->firstOrFail()->textTranslations()->firstOrFail()->update(['title' => 'Our maintenance announcement']);
    $page->blocks()->update(['status' => 'published']);
    $page->update(['status' => 'published']);
    CmsUpdateMaintenance::arguments();
    $html = file_get_contents(SystemPageRenderer::maintenancePath());
    $this->assertStringContainsString('Our maintenance announcement', $html);
    $this->assertStringNotContainsString('We are carrying out a brief update', $html);
  }

  #[Test]
  public function default_home_links_respect_the_public_mount_and_reject_non_native_recipes(): void
  {
    config()->set('webblocks-cms.public.mount', 'content');
    $page = $this->prepare('core:error-404');
    $this->assertSame('/content', $page->blocks()->where('type', 'button_link')->firstOrFail()->buttonLinkUrl());
    $this->get('/webadmin/pages/'.$page->id.'/preview')->assertOk()->assertSee('href="/content"', false);
    $this->expectException(\InvalidArgumentException::class);
    new SystemPageDefinition('synthetic:screen', 'synthetic::screen.title', blocks: [
      ['type' => 'html', 'translations' => ['content' => 'synthetic::screen.body']],
    ]);
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
