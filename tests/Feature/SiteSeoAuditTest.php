<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Actions\Seo\ManageSeoAudit;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\AutomaticPageRule;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\SeoAudit;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Queries\SiteSeoAuditQuery;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Seo\PageSeoReport;
use WebBlocks\Cms\Support\Seo\SeoLinkResolver;
use WebBlocks\Cms\Support\Seo\SeoSourceVersion;
use WebBlocks\Cms\Support\Seo\SiteSeoAuditWorker;
use WebBlocks\Cms\Support\SharedSlots\SharedSlotSourcePageManager;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class SiteSeoAuditTest extends TestCase
{
  private Site $site;

  private Locale $en;

  private Locale $tr;

  private SlotType $slot;

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

  protected function setUp(): void
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    parent::setUp();
    $this->withoutMiddleware(EnsureInstalled::class);
    Http::preventStrayRequests();
    $this->en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $this->tr = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_enabled' => true]);
    $this->site = Site::create(['name' => 'Synthetic studio', 'handle' => 'synthetic', 'domain' => 'example.test', 'is_primary' => true, 'seo_description' => 'Studio fallback']);
    $this->site->locales()->sync([$this->en->id => ['is_enabled' => true], $this->tr->id => ['is_enabled' => true]]);
    $this->slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
  }

  private function page(string $slug, string $status = 'published', ?string $html = null): Page
  {
    $page = Page::create(['site_id' => $this->site->id, 'title' => 'Page '.$slug, 'slug' => $slug, 'status' => $status]);
    $page->slots()->create(['slot_type_id' => $this->slot->id]);
    $block = $page->blocks()->create(['slot_type_id' => $this->slot->id, 'type' => 'html', 'status' => 'published']);
    $block->textTranslations()->create(['locale_id' => $this->en->id, 'content' => $html ?? '<h1>Saved heading</h1>']);

    return $page;
  }

  private function actor(string $role = 'editor'): User
  {
    $user = (new User)->setTable('users')->forceFill(['name' => 'Synthetic editor', 'email' => uniqid().'@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
    $user->save();
    $this->cmsActor($user, [$this->site->id]);

    return $user;
  }

  private function token(array $capabilities = ['content.read'], ?array $sites = null): string
  {
    return app(CmsApiTokenIssuer::class)->issue('Synthetic audit', capabilities: $capabilities, allowedSiteIds: $sites)->plainToken;
  }

  private function finish(SeoAudit $audit): SeoAudit
  {
    for ($i = 0; $i < 30 && in_array($audit->fresh()->state, ['queued', 'running'], true); $i++) {
      app(SiteSeoAuditWorker::class)->advance($audit->id, 20);
    }
    $audit->refresh();
    $this->assertContains($audit->state, ['complete', 'partial'], 'Audit failed: '.$audit->state.' / '.$audit->phase);

    return $audit;
  }

  #[Test]
  public function requests_only_enqueue_and_panel_and_api_share_paginated_results_without_content_mutations(): void
  {
    $this->page('one');
    $user = $this->actor();
    $before = $this->content();
    $this->withToken($this->token());
    $url = '/webadmin/api/sites/'.$this->site->id.'/seo-audits';
    $this->getJson($url)->assertOk()->assertJsonPath('audit', null);
    $id = $this->postJson($url)->assertStatus(202)->assertJsonPath('audit.state', 'queued')->json('audit.id');
    $this->assertDatabaseCount('wbcms_seo_audit_pages', 0);
    $this->postJson($url)->assertStatus(202)->assertJsonPath('audit.id', $id);
    $audit = $this->finish(SeoAudit::findOrFail($id));
    $api = $this->getJson($url.'?locale=en')->assertOk()->assertJsonPath('audit.complete', true)->assertJsonPath('audit.publication_blocked', false)
      ->assertHeader('Cache-Control', 'no-store, private');
    $this->assertGreaterThan(0, $api->json('findings.total'));
    $this->flushHeaders();
    $panel = $this->actingAs($user)->get('/webadmin/sites/'.$this->site->id.'/seo?locale=en')->assertOk()
      ->assertSee('Site SEO audit')->assertSee('seo-findings', false)->assertViewHas('audit', fn ($value) => json_decode(json_encode($value), true) === $api->json('audit'));
    $this->assertSame($before, $this->content());
    $this->assertSame($audit->total, $audit->processed);
    Http::assertNothingSent();
  }

  #[Test]
  public function audit_access_enforces_capabilities_site_scope_and_parent_child_binding(): void
  {
    $this->page('one');
    $url = '/webadmin/api/sites/'.$this->site->id.'/seo-audits';
    $this->getJson($url)->assertUnauthorized();
    $this->withToken($this->token(['media.read']))->postJson($url)->assertForbidden();
    $this->withToken($this->token(sites: [$this->site->id + 50]))->getJson($url)->assertForbidden();
    $this->withToken($this->token(sites: [$this->site->id]));
    $id = $this->postJson($url)->assertStatus(202)->json('audit.id');
    $other = Site::create(['name' => 'Other', 'handle' => 'other']);
    $this->withToken($this->token());
    $this->postJson('/webadmin/api/sites/'.$other->id.'/seo-audits/'.$id.'/pause')->assertNotFound();
    $this->getJson('/webadmin/api/sites/'.$other->id.'/seo-audits?audit='.$id)->assertNotFound();
    $this->flushHeaders();
    $user = $this->actor();
    $this->actingAs($user)->get('/webadmin/sites/'.$other->id.'/seo')->assertForbidden();
    $personal = app(CmsApiTokenIssuer::class)->issue('Audit personal', creator: $user, capabilities: ['content.read'], tokenType: 'personal', allowedSiteIds: [$this->site->id])->plainToken;
    $this->withToken($personal)->getJson($url)->assertOk();
    $this->postJson($url.'/'.$id.'/pause')->assertStatus(202)->assertJsonPath('audit.state', 'paused');
    $this->getJson('/webadmin/api/sites/'.$other->id.'/seo-audits')->assertForbidden();
  }

  #[Test]
  public function pause_resume_and_worker_leases_preserve_progress_and_exclude_concurrent_workers(): void
  {
    $this->page('one');
    $manage = app(ManageSeoAudit::class);
    $audit = $manage->start($this->site);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $this->assertSame('inspect', $audit->fresh()->phase);
    $manage->transition($audit, 'pause');
    app(SiteSeoAuditWorker::class)->advance($audit->id);
    $this->assertSame(0, $audit->fresh()->processed);
    $manage->transition($audit, 'resume');
    $audit->refresh()->update(['worker_token' => 'synthetic-held-worker', 'worker_expires_at' => now()->addMinute()]);
    app(SiteSeoAuditWorker::class)->advance($audit->id);
    $this->assertSame(0, $audit->fresh()->processed);
    $audit->refresh()->update(['worker_expires_at' => now()->subMinute()]);
    $this->finish($audit);
    $this->assertNull($audit->worker_token);
  }

  #[Test]
  public function changed_sources_stop_a_run_and_old_completed_reports_are_marked_stale(): void
  {
    $page = $this->page('one');
    $audit = app(ManageSeoAudit::class)->start($this->site);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $page->translations()->first()->update(['seo_title' => 'Changed']);
    app(SiteSeoAuditWorker::class)->advance($audit->id);
    $this->assertSame('stale', $audit->fresh()->state);
    $complete = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $this->site->update(['seo_description' => 'Another fallback']);
    $report = app(SiteSeoAuditQuery::class)->run($this->site, ['audit' => $complete->id]);
    $this->assertTrue($report['audit']['stale']);
    $this->assertFalse($report['audit']['complete']);
  }

  #[Test]
  public function source_versions_include_raw_shared_navigation_and_block_writes_and_roll_back_with_content(): void
  {
    $page = $this->page('one');
    $version = app(SeoSourceVersion::class);
    $before = $version->current();
    DB::beginTransaction();
    DB::table('wbcms_block_text_translations')->update(['content' => '<h1>Rollback</h1>']);
    $this->assertNotSame($before, $version->current());
    DB::rollBack();
    $this->assertSame($before, $version->current());
    NavigationItem::create(['site_id' => $this->site->id, 'title' => 'A', 'link_type' => 'page', 'page_id' => $page->id, 'visibility' => 'visible']);
    $this->assertNotSame($before, $version->current());
  }

  #[Test]
  public function same_language_duplicates_missing_translations_and_fallbacks_are_separate_suggestions(): void
  {
    $one = $this->page('one');
    $two = $this->page('two', 'draft');
    $one->translations()->first()->update(['seo_title' => 'Same title', 'seo_description' => 'Same description']);
    $two->translations()->first()->update(['seo_title' => ' Same  TITLE ', 'seo_description' => 'Same description']);
    $one->translations()->create(['locale_id' => $this->tr->id, 'name' => 'Türkçe', 'path' => '/bir', 'slug' => 'bir']);
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $this->assertDatabaseHas('wbcms_seo_audit_findings', ['audit_id' => $audit->id, 'code' => 'translation_missing']);
    $report = app(SiteSeoAuditQuery::class)->run($this->site, ['locale' => 'en', 'code' => 'duplicate_description']);
    $this->assertSame(2, $report['findings']->total());
    $this->assertSame(2, $report['pages']->total());
    $this->assertSame(2, app(SiteSeoAuditQuery::class)->run($this->site, ['locale' => 'en', 'code' => 'duplicate_title'])['findings']->total());
    $this->assertStringContainsString('#translation_seo_description', $report['findings']->first()->action['href']);
    $tr = app(SiteSeoAuditQuery::class)->run($this->site, ['locale' => 'tr']);
    $codes = $tr['findings']->getCollection()->pluck('code');
    $this->assertContains('metadata_fallback', $codes);
    $this->assertContains('block_translation_fallback', $codes);
    $this->assertNotContains('duplicate_title', $codes);
  }

  #[Test]
  public function links_preserve_source_block_text_target_and_detect_native_missing_draft_language_and_legacy_paths(): void
  {
    $target = $this->page('target');
    $target->translations()->create(['locale_id' => $this->tr->id, 'name' => 'Hedef', 'slug' => 'hedef', 'path' => '/hedef']);
    $draft = $this->page('draft', 'draft');
    $source = $this->page('source', html: '<h1>Source</h1><a href="/target?private=omitted">Target text</a><a href="/missing">Missing</a><a href="/draft">Draft</a><a href="/tr/hedef">Other language</a><a href="/p/target">Old link</a>');
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $row = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('page_id', $source->id)->where('locale', 'en')->first();
    $links = DB::table('wbcms_seo_audit_links')->where('audit_page_id', $row->id)->orderBy('id')->get();
    $this->assertSame(['ok', 'missing_page', 'unpublished_target', 'wrong_locale', 'old_url'], $links->pluck('state')->all());
    $this->assertSame($source->blocks()->first()->id, $links->first()->block_id);
    $this->assertSame('Target text', $links->first()->text);
    $this->assertSame('/target', $links->first()->href);
    $this->assertSame($target->id, $links->first()->target_page_id);
    $this->assertStringContainsString('/target', $links->last()->suggested_url);
    $targetRow = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('page_id', $target->id)->where('locale', 'en')->first();
    $this->assertDatabaseMissing('wbcms_seo_audit_findings', ['audit_page_id' => $targetRow->id, 'code' => 'no_incoming_links']);
  }

  #[Test]
  public function mounted_relative_localized_urls_and_automatic_prefixes_are_not_false_missing_targets(): void
  {
    config(['webblocks-cms.public.mount' => 'content']);
    $target = $this->page('target');
    $target->translations()->create(['locale_id' => $this->tr->id, 'name' => 'Hedef', 'slug' => 'hedef', 'path' => '/hedef']);
    $resolver = app(SeoLinkResolver::class);
    $this->assertSame('ok', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', 'target')['state']);
    $this->assertSame('ok', $resolver->resolve($this->site, 'https://example.test/content/tr/kaynak', 'tr', 'hedef')['state']);
    $this->assertSame('outside_scope', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', '/target')['state']);
    $this->assertSame('external', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', 'https://foreign.test/content/target')['state']);
    $this->assertSame('outside_scope', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', '/content/search')['state']);
    $rule = AutomaticPageRule::create(['name' => 'Synthetic', 'site_id' => $this->site->id, 'source_handle' => 'synthetic', 'base_path' => '/records', 'template_page_id' => $target->id]);
    $rule->forceFill(['status' => 'active'])->save();
    $this->assertSame('automatic_not_checked', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', '/content/records/synthetic')['state']);
    $this->assertSame('ok', $resolver->resolve($this->site, 'https://example.test/content/source', 'en', '/content/target')['state']);
    Http::assertNothingSent();
  }

  #[Test]
  public function navigation_definitions_are_traceable_and_do_not_hide_an_orphan_or_count_draft_and_self_sources(): void
  {
    $target = $this->page('target', html: '<h1>Target</h1><a href="/target">Self</a>');
    $this->page('draft-source', 'draft', '<h1>Draft</h1><a href="/target">Draft link</a>');
    $draft = $this->page('draft-target', 'draft');
    $nav = NavigationItem::create(['site_id' => $this->site->id, 'title' => 'Unplaced navigation', 'link_type' => 'page', 'page_id' => $target->id, 'visibility' => 'visible']);
    NavigationItem::create(['site_id' => $this->site->id, 'title' => 'Draft definition', 'link_type' => 'page', 'page_id' => $draft->id, 'visibility' => 'visible']);
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $row = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('page_id', $target->id)->where('locale', 'en')->first();
    $this->assertDatabaseHas('wbcms_seo_audit_findings', ['audit_page_id' => $row->id, 'code' => 'no_incoming_links']);
    $this->assertDatabaseHas('wbcms_seo_audit_links', ['audit_id' => $audit->id, 'navigation_item_id' => $nav->id, 'source' => 'navigation_definition', 'target_page_id' => $target->id]);
    $this->assertDatabaseHas('wbcms_seo_audit_links', ['audit_id' => $audit->id, 'source' => 'navigation_definition', 'target_page_id' => $draft->id, 'state' => 'unpublished_target']);
    $this->assertDatabaseHas('wbcms_seo_audit_links', ['audit_id' => $audit->id, 'navigation_item_id' => $nav->id, 'target_locale' => 'tr', 'state' => 'missing_target_translation']);
  }

  #[Test]
  public function failed_renders_are_unavailable_retryable_and_never_leak_exception_text(): void
  {
    $this->page('one');
    $presenter = app(PublicPagePresenter::class);
    $this->mock(PublicPagePresenter::class)->shouldReceive('present')->andThrow(new \RuntimeException('synthetic-private-detail'));
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $this->assertSame('partial', $audit->state);
    $this->assertDatabaseHas('wbcms_seo_audit_pages', ['audit_id' => $audit->id, 'state' => 'unavailable']);
    $report = app(SiteSeoAuditQuery::class)->run($this->site, []);
    $this->assertStringNotContainsString('synthetic-private-detail', json_encode($report));
    $this->app->instance(PublicPagePresenter::class, $presenter);
    app(ManageSeoAudit::class)->transition($audit, 'retry');
    $this->finish($audit);
    $this->assertSame('complete', $audit->state);
    $this->assertDatabaseMissing('wbcms_seo_audit_findings', ['audit_id' => $audit->id, 'code' => 'render_failed']);
  }

  #[Test]
  public function inventory_and_processing_are_bounded_and_scheduler_command_resumes_work(): void
  {
    for ($i = 0; $i < 51; $i++) {
      Page::create(['site_id' => $this->site->id, 'title' => 'Page '.$i, 'slug' => 'page-'.$i, 'status' => 'draft']);
    }
    $audit = app(ManageSeoAudit::class)->start($this->site);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $audit->refresh();
    $this->assertSame('inventory', $audit->phase);
    $this->assertSame(102, $audit->total);
    $this->assertSame(0, $audit->processed);
    $this->artisan('webblocks:seo:work', ['--limit' => 1])->assertSuccessful();
    $audit->refresh();
    $this->assertSame('inspect', $audit->phase);
    $this->assertSame(104, $audit->total);
  }

  #[Test]
  public function single_page_metadata_targets_and_freshness_use_saved_changes_and_preserve_publication(): void
  {
    $page = $this->page('one', 'draft', '<h1>First</h1><h1>Second</h1>');
    $this->withToken($this->token());
    $report = app(PageSeoReport::class)->run($page, 'en');
    $this->assertFalse($report['stale']);
    $this->assertSame('site_fallback', $report['metadata']['description']['source']);
    $finding = collect($report['editorial'])->firstWhere('code', 'h1_multiple');
    $this->assertSame('First', $finding['examples'][0]['example']);
    $this->assertSame('block', $finding['action']['kind']);
    $this->assertStringContainsString('edit='.$page->blocks()->first()->id, $finding['action']['href']);
    $this->assertStringContainsString('locale=en', $finding['action']['href']);
    $url = '/webadmin/api/pages/'.$page->id.'/seo-check/freshness?locale=en&source_version='.$report['source_version'];
    $this->getJson($url)->assertOk()->assertJsonPath('stale', false);
    $page->translations()->first()->update(['seo_description' => 'Page-specific description']);
    $this->getJson($url)->assertOk()->assertJsonPath('stale', true);
    $updated = $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=en')->assertOk();
    $updated->assertJsonPath('report.metadata.description.source', 'translation_seo_description')->assertJsonPath('report.metadata.description.value', 'Page-specific description');
    $this->assertSame('draft', $page->fresh()->status);
  }

  #[Test]
  public function update_migration_is_idempotent_and_evidence_has_no_json_columns(): void
  {
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_03_120000_create_seo_audits.php';
    $migration->up();
    $migration->up();
    Schema::drop('wbcms_seo_audit_links');
    $migration->up();
    $this->assertTrue(Schema::hasTable('wbcms_seo_audit_links'));
    foreach (['wbcms_seo_audits', 'wbcms_seo_audit_pages', 'wbcms_seo_audit_findings', 'wbcms_seo_audit_links'] as $table) {
      foreach (Schema::getColumns($table) as $column) {
        $this->assertNotSame('json', $column['type_name']);
      }
    }
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $audit->delete();
    $this->assertDatabaseCount('wbcms_seo_audit_pages', 0);
    $this->assertDatabaseCount('wbcms_seo_audit_locales', 0);
  }

  #[Test]
  public function shared_rendered_links_keep_owner_and_navigation_identity_without_public_trace_markup(): void
  {
    $target = $this->page('target');
    $source = $this->page('source');
    $nav = NavigationItem::create(['site_id' => $this->site->id, 'title' => 'Target nav', 'menu_key' => 'primary', 'link_type' => 'page', 'page_id' => $target->id, 'visibility' => 'visible']);
    $header = SlotType::create(['name' => 'Header', 'slug' => 'header', 'status' => 'published']);
    $shared = SharedSlot::create(['site_id' => $this->site->id, 'name' => 'Shared header', 'handle' => 'header', 'slot_name' => 'header', 'is_active' => true]);
    $manager = app(SharedSlotSourcePageManager::class);
    $owner = $manager->ensureFor($shared);
    $block = $owner->blocks()->create(['slot_type_id' => $header->id, 'type' => 'navigation-auto', 'status' => 'published']);
    $manager->rebuildAssignments($shared);
    $source->slots()->create(['slot_type_id' => $header->id, 'source_type' => PageSlot::SOURCE_TYPE_SHARED_SLOT, 'shared_slot_id' => $shared->id]);
    $report = app(PageSeoReport::class)->run($source, 'en', 'en', true);
    $link = collect($report['links'])->firstWhere('navigation_item_id', $nav->id);
    $this->assertNotNull($link);
    $this->assertSame($block->id, $link['block_id']);
    $this->assertSame($owner->id, $link['owner_page_id']);
    $this->get('/source')->assertOk()->assertDontSee('data-wb-seo-navigation', false)->assertDontSee('<!--seo-', false);
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $this->assertDatabaseMissing('wbcms_seo_audit_pages', ['audit_id' => $audit->id, 'page_id' => $owner->id]);
    $this->assertDatabaseHas('wbcms_seo_audit_links', ['audit_id' => $audit->id, 'source' => 'rendered', 'block_id' => $block->id, 'navigation_item_id' => $nav->id, 'is_public_source' => true]);
  }

  #[Test]
  public function draft_blocks_and_protected_published_sources_do_not_count_as_public_incoming_links(): void
  {
    $target = $this->page('target');
    $source = $this->page('source', html: '<h1>Source</h1><a href="/target">Draft block link</a>');
    $source->blocks()->first()->update(['status' => 'draft']);
    $protected = $this->page('protected', html: '<h1>Restricted</h1><a href="/target">Restricted link</a>');
    $protected->accessRule()->create(['package_id' => null]);
    $audit = $this->finish(app(ManageSeoAudit::class)->start($this->site));
    $row = DB::table('wbcms_seo_audit_pages')->where('audit_id', $audit->id)->where('page_id', $target->id)->where('locale', 'en')->first();
    $this->assertDatabaseHas('wbcms_seo_audit_findings', ['audit_page_id' => $row->id, 'code' => 'no_incoming_links']);
    $this->assertDatabaseMissing('wbcms_seo_audit_links', ['audit_id' => $audit->id, 'target_page_id' => $target->id, 'is_public_source' => true]);
  }

  #[Test]
  public function retrying_an_interrupted_inventory_cannot_skip_uninventoried_pages(): void
  {
    for ($i = 0; $i < 51; $i++) {
      Page::create(['site_id' => $this->site->id, 'title' => 'Page '.$i, 'slug' => 'page-'.$i, 'status' => 'draft']);
    }
    $audit = app(ManageSeoAudit::class)->start($this->site);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $audit->refresh()->update(['state' => 'failed']);
    app(ManageSeoAudit::class)->transition($audit, 'retry');
    $this->assertSame('inventory', $audit->fresh()->phase);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $this->assertSame(104, $audit->fresh()->total);
    $this->assertSame('inspect', $audit->fresh()->phase);
  }

  #[Test]
  public function exact_image_examples_and_custom_head_targets_point_to_the_actual_source(): void
  {
    $page = $this->page('one', html: '<h1>One</h1><img alt="" src="decorative" width="20" height="20"><img src="missing-alt" width="20" height="20"><h3>Skipped</h3>');
    $this->site->update(['custom_head_html' => '<title>Extra title</title>']);
    $report = app(PageSeoReport::class)->run($page, 'en');
    $finding = collect($report['technical'])->firstWhere('code', 'image_alt_missing');
    $this->assertCount(1, $finding['examples']);
    $this->assertStringContainsString('missing-alt', $finding['examples'][0]['example']);
    $titles = collect($report['technical'])->firstWhere('code', 'title_multiple');
    $extra = collect($titles['examples'])->firstWhere('origin', 'site_head');
    $this->assertSame('site_head', $extra['action']['kind']);
    $this->assertStringContainsString('tab=head', $extra['action']['href']);
    $skipped = collect($report['editorial'])->firstWhere('code', 'heading_skipped');
    $this->assertSame('Skipped', $skipped['examples'][0]['example']);
  }

  #[Test]
  public function malformed_filters_disabled_languages_and_sites_without_languages_are_rejected(): void
  {
    $this->withToken($this->token());
    $url = '/webadmin/api/sites/'.$this->site->id.'/seo-audits';
    foreach (['locale[]=en', 'locale=de', 'page_id=-1', 'per_page=101', 'category=invalid', 'link_state=invalid', 'audit=invalid', 'pages_page=0'] as $query) {
      $this->getJson($url.'?'.$query)->assertUnprocessable();
    }
    $this->getJson($url.'?locale=TR')->assertOk();
    $this->site->locales()->updateExistingPivot($this->tr->id, ['is_enabled' => false]);
    $this->getJson($url.'?locale=tr')->assertUnprocessable();
    $this->site->locales()->updateExistingPivot($this->en->id, ['is_enabled' => false]);
    $this->postJson($url)->assertUnprocessable();
    $this->assertDatabaseCount('wbcms_seo_audits', 0);
    $this->flushHeaders();
    $this->actingAs($this->actor())->get('/webadmin/sites/'.$this->site->id.'/seo?tab=links&per_page=101')
      ->assertRedirect(route('admin.sites.seo-audits.index', ['site' => $this->site, 'tab' => 'links']));
  }

  #[Test]
  public function translations_and_discovery_cover_the_workspace_and_audit_operations(): void
  {
    $english = require dirname(__DIR__, 2).'/resources/lang/en/seo_workspace.php';
    foreach (['tr', 'de', 'es', 'fr', 'it'] as $locale) {
      $other = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/seo_workspace.php';
      $this->assertSame(array_keys($english), array_keys($other));
      foreach (['states', 'phases', 'page_states', 'results'] as $group) {
        $this->assertSame(array_keys($english[$group]), array_keys($other[$group]));
      }
    }
    $paths = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $this->assertSame('content.read', $paths['/sites/{site}/seo-audits']['post']['x-required-capability']);
    $this->assertSame('content.read', $paths['/pages/{page}/seo-check/freshness']['get']['x-required-capability']);
  }

  #[Test]
  public function custom_roles_need_all_source_read_permissions_and_revocation_remains_effective(): void
  {
    $page = $this->page('one');
    $admin = $this->actor('super_admin');
    $reader = $this->actor();
    $role = app(WriteCmsAccess::class)->role($admin, ['name' => 'Synthetic page reader', 'permissions' => ['cms.panel.access', 'cms.pages.read']]);
    $reader->sites()->updateExistingPivot($this->site->id, ['role_id' => $role->id]);
    $this->actingAs($reader)->get('/webadmin/pages/'.$page->id.'/seo-check')->assertOk();
    $this->get('/webadmin/sites/'.$this->site->id.'/seo')->assertForbidden();
    foreach (['cms.navigation.read', 'cms.shared-slots.read'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $this->get('/webadmin/sites/'.$this->site->id.'/seo')->assertOk();
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic reader', creator: $reader, capabilities: ['content.read'], tokenType: 'personal', allowedSiteIds: [$this->site->id])->plainToken;
    $url = '/webadmin/api/sites/'.$this->site->id.'/seo-audits';
    $this->withToken($token)->postJson($url)->assertStatus(202);
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.navigation.read')->delete();
    $this->getJson($url)->assertForbidden();
  }

  #[Test]
  public function a_pause_during_render_rejects_that_workers_late_result(): void
  {
    $page = $this->page('one');
    $manage = app(ManageSeoAudit::class);
    $audit = $manage->start($this->site);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 3); // Inventory and both navigation languages.
    $presenter = app(PublicPagePresenter::class);
    $this->mock(PublicPagePresenter::class)->shouldReceive('present')->once()->andReturnUsing(function (...$arguments) use ($presenter, $manage, $audit) {
      $manage->transition($audit, 'pause');

      return $presenter->present(...$arguments);
    });
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $this->assertSame('paused', $audit->fresh()->state);
    $this->assertSame(2, $audit->fresh()->processed);
    $this->assertDatabaseHas('wbcms_seo_audit_pages', ['audit_id' => $audit->id, 'page_id' => $page->id, 'locale' => 'en', 'state' => 'pending']);
    $this->app->instance(PublicPagePresenter::class, $presenter);
    $manage->transition($audit, 'resume');
    $this->finish($audit);
  }

  #[Test]
  public function rendered_link_truncation_is_partial_and_never_silently_complete(): void
  {
    $page = $this->page('heavy', html: '<h1>Heavy</h1>'.str_repeat('<a href="/target">Target</a>', 2001));
    $report = app(PageSeoReport::class)->run($page, 'en', 'en', true);
    $this->assertCount(2000, $report['links']);
    $this->assertSame('partial', $report['inspection_status']);
    $this->assertContains('links_truncated', array_column($report['not_checked'], 'code'));
  }

  #[Test]
  public function retention_is_processed_in_the_background_and_enqueue_never_deletes_large_evidence(): void
  {
    for ($i = 0; $i < 10; $i++) {
      SeoAudit::create(['site_id' => $this->site->id, 'state' => 'complete', 'phase' => 'finished', 'source_version' => app(SeoSourceVersion::class)->current(), 'cms_version' => 'synthetic']);
    }
    $oldest = SeoAudit::min('id');
    $audit = app(ManageSeoAudit::class)->start($this->site);
    $this->assertDatabaseCount('wbcms_seo_audits', 11);
    $this->assertDatabaseHas('wbcms_seo_audits', ['id' => $oldest]);
    app(SiteSeoAuditWorker::class)->advance($audit->id, 1);
    $this->assertDatabaseCount('wbcms_seo_audits', 10);
    $this->assertDatabaseMissing('wbcms_seo_audits', ['id' => $oldest]);
    $this->assertDatabaseHas('wbcms_seo_audits', ['id' => $audit->id]);
  }

  private function content(): array
  {
    return array_map(fn ($table) => DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all(), [
      'wbcms_pages', 'wbcms_page_translations', 'wbcms_blocks', 'wbcms_block_text_translations', 'wbcms_page_revisions',
    ]);
  }
}
