<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Middleware\EnsureInstalled;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Policies\PageSeoPolicy;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\Pages\PublicPagePresenter;
use WebBlocks\Cms\Support\Seo\PageSeoReport;
use WebBlocks\Cms\Tests\Support\MaintenanceTestUser;
use WebBlocks\Cms\Tests\TestCase;

class PageSeoCheckTest extends TestCase
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

  protected function setUp(): void
  {
    if (! class_exists(User::class)) {
      class_alias(MaintenanceTestUser::class, User::class);
    }
    parent::setUp();
    $this->withoutMiddleware(EnsureInstalled::class);
    Http::preventStrayRequests();
  }

  private function fixture(): Page
  {
    $en = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $tr = Locale::create(['code' => 'tr', 'name' => 'Türkçe', 'is_enabled' => true]);
    $site = Site::create(['name' => 'Synthetic studio', 'handle' => 'synthetic', 'is_primary' => true, 'domain' => 'example.test']);
    $site->locales()->sync([$en->id => ['is_enabled' => true], $tr->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Saved page', 'slug' => 'saved', 'status' => 'draft']);
    $page->translations()->create(['locale_id' => $tr->id, 'name' => 'Kaydedilmiş sayfa', 'slug' => 'kayitli', 'path' => '/kayitli', 'seo_description' => 'Türkçe açıklama']);
    $slot = SlotType::create(['name' => 'Main', 'slug' => 'main', 'status' => 'published']);
    $page->slots()->create(['slot_type_id' => $slot->id]);
    $heading = $page->blocks()->create(['slot_type_id' => $slot->id, 'type' => 'header', 'variant' => 'h1', 'status' => 'draft']);
    $heading->textTranslations()->create(['locale_id' => $en->id, 'title' => 'English heading']);
    $heading->textTranslations()->create(['locale_id' => $tr->id, 'title' => 'Türkçe başlık']);

    return $page;
  }

  private function actor(Page $page, string $role = 'editor'): User
  {
    $user = (new User)->setTable('users')->forceFill(['name' => 'Synthetic editor', 'email' => 'editor@example.test', 'password' => 'unused', 'role' => $role, 'is_active' => true]);
    $user->save();
    $this->cmsActor($user, [$page->site_id]);

    return $user;
  }

  private function token(array $capabilities = ['content.read'], ?array $sites = null): string
  {
    return app(CmsApiTokenIssuer::class)->issue('Synthetic SEO check', capabilities: $capabilities, allowedSiteIds: $sites)->plainToken;
  }

  #[Test]
  public function panel_and_api_render_identical_findings_for_the_same_language_without_content_writes(): void
  {
    $this->freezeTime();
    $page = $this->fixture();
    $user = $this->actor($page);
    $before = $this->contentState();
    $api = $this->withToken($this->token())->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=tr');
    $api->assertOk()->assertJsonPath('ok', true)->assertJsonPath('report.locale', 'tr')->assertJsonPath('report.publication_blocked', false)
      ->assertJsonPath('report.technical', [])->assertJsonPath('report.editorial', [])->assertHeader('Cache-Control', 'no-store, private');
    $this->flushHeaders();
    $panel = $this->actingAs($user)->get('/webadmin/pages/'.$page->id.'/seo-check?locale=tr');
    $panel->assertOk()->assertViewHas('seoReport', $api->json('report'))->assertSee('page-management-seo-panel', false)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    $this->assertSame($before, $this->contentState());
    Http::assertNothingSent();
  }

  #[Test]
  public function opening_the_tab_and_translation_shortcuts_do_not_run_a_report(): void
  {
    $page = $this->fixture();
    $this->actingAs($this->actor($page));
    $this->mock(PublicPagePresenter::class)->shouldNotReceive('present');
    $this->get('/webadmin/pages/'.$page->id.'/edit?tab=seo&seo_locale=tr')->assertOk()
      ->assertSee('value="tr" selected', false)->assertSee('name="locale"', false)->assertViewHas('seoReport', null)
      ->assertSee('seo_locale=tr', false);
    $translation = $page->translations()->where('locale_id', Locale::where('code', 'tr')->value('id'))->first();
    $this->get(route('admin.pages.translations.edit', [$page, $translation]))->assertOk()->assertSee('seo_locale=tr', false);
  }

  #[Test]
  public function requested_language_is_validated_without_silent_default_fallback(): void
  {
    $page = $this->fixture();
    $this->withToken($this->token());
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=TR')->assertOk()->assertJsonPath('report.locale', 'tr');
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=de')->assertUnprocessable()->assertJsonValidationErrors('locale');
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale[]=tr')->assertUnprocessable()->assertJsonValidationErrors('locale');
    $page->site->locales()->updateExistingPivot(Locale::where('code', 'tr')->value('id'), ['is_enabled' => false]);
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=tr')->assertUnprocessable();
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check')->assertOk()->assertJsonPath('report.locale', 'en');
  }

  #[Test]
  public function api_authentication_capability_and_site_scope_are_enforced(): void
  {
    $page = $this->fixture();
    $url = '/webadmin/api/pages/'.$page->id.'/seo-check';
    $this->getJson($url)->assertUnauthorized();
    $this->withToken($this->token(['media.read']))->getJson($url)->assertForbidden();
    $this->withToken($this->token(['content.read'], [$page->site_id + 100]))->getJson($url)->assertForbidden();
    $this->withToken($this->token(['content.read'], [$page->site_id]))->getJson($url)->assertOk();
  }

  #[Test]
  public function session_and_personal_tokens_cannot_inspect_another_site(): void
  {
    $page = $this->fixture();
    $user = $this->actor($page);
    $other = Site::create(['name' => 'Other site', 'handle' => 'other']);
    $foreign = Page::create(['site_id' => $other->id, 'title' => 'Other page', 'status' => 'draft']);
    $this->actingAs($user)->get('/webadmin/pages/'.$foreign->id.'/seo-check')->assertForbidden();
    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic personal SEO', creator: $user, capabilities: ['content.read'], tokenType: 'personal', allowedSiteIds: [$page->site_id])->plainToken;
    $this->withToken($token)->getJson('/webadmin/api/pages/'.$foreign->id.'/seo-check')->assertForbidden();
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check')->assertOk();
  }

  #[Test]
  public function failures_are_unchecked_and_do_not_leak_exception_details_or_change_render_context(): void
  {
    $page = $this->fixture();
    $original = request();
    $originalLocale = app()->getLocale();
    $this->mock(PublicPagePresenter::class)->shouldReceive('present')->once()->andThrow(new \RuntimeException('synthetic-private-detail'));
    $report = app(PageSeoReport::class)->run($page, 'tr');
    $this->assertContains('render_failed', array_column($report['not_checked'], 'code'));
    $this->assertStringNotContainsString('synthetic-private-detail', json_encode($report));
    $this->assertSame($original, request());
    $this->assertSame($originalLocale, app()->getLocale());
    $this->assertFalse($report['publication_blocked']);
  }

  #[Test]
  public function draft_state_does_not_generate_a_false_noindex_finding_and_saved_locale_output_is_used(): void
  {
    $page = $this->fixture();
    $en = app(PageSeoReport::class)->run($page, 'en');
    $tr = app(PageSeoReport::class)->run($page, 'tr');
    $this->assertContains('description_missing', array_column($en['editorial'], 'code'));
    $this->assertNotContains('description_missing', array_column($tr['editorial'], 'code'));
    $this->assertNotContains('noindex_present', array_column($tr['editorial'], 'code'));
    $this->assertNotContains('h1_missing', array_column($tr['editorial'], 'code'));
    $this->assertSame('saved_preview', $tr['source']);
    $this->assertSame('draft', $page->fresh()->status);
  }

  #[Test]
  public function every_panel_language_has_complete_report_copy_and_discovery_describes_the_boundary(): void
  {
    $english = require dirname(__DIR__, 2).'/resources/lang/en/seo.php';
    foreach (['de', 'tr', 'es', 'fr', 'it'] as $locale) {
      $localized = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/seo.php';
      $this->assertSame(array_keys($english), array_keys($localized));
      $this->assertSame(array_keys($english['findings']), array_keys($localized['findings']));
      $this->assertSame(array_keys($english['not_checked']), array_keys($localized['not_checked']));
    }
    $paths = app(InternalApiDiscoveryController::class)->openapi()->getData(true)['paths'];
    $this->assertSame('content.read', $paths['/pages/{page}/seo-check']['get']['x-required-capability']);
  }

  #[Test]
  public function technical_findings_do_not_block_the_existing_publish_endpoint(): void
  {
    $page = $this->fixture();
    $page->site->update(['custom_head_html' => '<title>Duplicate title</title>']);
    $this->withToken($this->token(['content.read', 'content.publish']));
    $report = $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check')->assertOk();
    $this->assertContains('title_multiple', array_column($report->json('report.technical'), 'code'));
    $this->postJson('/webadmin/api/pages/'.$page->id.'/publish', [])->assertOk()->assertJsonPath('ok', true);
    $this->assertSame('published', $page->fresh()->status);
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check')->assertOk()->assertJsonPath('report.publication_blocked', false);
  }

  #[Test]
  public function scaffolding_disabled_languages_and_inactive_users_cannot_be_inspected(): void
  {
    $page = $this->fixture();
    $this->withToken($this->token());
    Locale::where('code', 'tr')->update(['is_enabled' => false]);
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check?locale=tr')->assertUnprocessable();
    $page->forceFill(['page_type' => Page::TYPE_SHARED_SLOT_SOURCE])->save();
    $this->getJson('/webadmin/api/pages/'.$page->id.'/seo-check')->assertNotFound();
    $page->forceFill(['page_type' => 'page'])->save();
    $this->flushHeaders();
    $actor = $this->actor($page);
    $actor->forceFill(['is_active' => false])->save();
    $this->assertFalse(app(PageSeoPolicy::class)->check($actor, $page));
  }

  #[Test]
  public function provider_failure_marks_the_report_partial_even_when_fallback_html_renders(): void
  {
    $page = $this->fixture();
    $presenter = app(PublicPagePresenter::class);
    $this->mock(PublicPagePresenter::class)->shouldReceive('present')->once()->andReturnUsing(function (...$arguments) use ($presenter) {
      request()->attributes->set('cms_editor_preview_issues', ['unavailable']);

      return $presenter->present(...$arguments);
    });
    $report = app(PageSeoReport::class)->run($page, 'tr');
    $this->assertSame('partial', $report['inspection_status']);
    $this->assertContains('dynamic_content_incomplete', array_column($report['not_checked'], 'code'));
  }

  private function contentState(): array
  {
    $tables = ['wbcms_pages', 'wbcms_page_translations', 'wbcms_blocks', 'wbcms_block_text_translations', 'wbcms_page_revisions'];

    return array_map(fn ($table) => DB::table($table)->get()->map(fn ($row) => (array) $row)->all(), $tables);
  }
}
