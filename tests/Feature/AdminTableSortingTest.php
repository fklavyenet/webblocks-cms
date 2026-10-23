<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models;
use WebBlocks\Cms\Queries\AdminTableSort;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class AdminTableSortingTest extends TestCase
{
  private Models\Site $site;

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
    (require dirname(__DIR__, 2).'/database/migrations/updates/2026_07_12_140000_ensure_prefixed_engagement_tables.php')->up();
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    if (! class_exists(User::class)) {
      class_alias(CmsSqlTestUser::class, User::class);
    }
    $actor = User::forceCreate(['name' => 'Synthetic operator', 'email' => 'sort@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($actor);
    Models\Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    foreach (['Z', 'A'] as $i => $letter) {
      $editor = User::forceCreate(['name' => $letter.' editor', 'email' => strtolower($letter).'-sort@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
      $site = Models\Site::create(['name' => $letter.' studio', 'handle' => strtolower($letter).'-studio']);
      $this->cmsActor($editor, [$site->id]);
      $this->site ??= $site;
      Models\SiteDomain::create(['site_id' => $this->site->id, 'domain' => strtolower($letter).'.example.test', 'status' => 'active']);
      Models\Locale::create(['code' => $i ? 'tr' : 'de', 'name' => $letter.' language', 'is_enabled' => true]);
      Models\PageLayout::create(['name' => $letter.' layout', 'handle' => strtolower($letter), 'sort_order' => $i, 'is_active' => true]);
      $type = Models\BlockType::create(['name' => $letter.' type', 'slug' => strtolower($letter).'-sort', 'category' => 'content', 'status' => 'published']);
      Models\SlotType::create(['name' => $letter.' slot', 'slug' => strtolower($letter), 'axis' => 'vertical', 'status' => 'published']);
      $page = Models\Page::create(['site_id' => $site->id, 'title' => $letter.' page', 'slug' => strtolower($letter), 'status' => 'draft']);
      Models\Block::create(['page_id' => $page->id, 'block_type_id' => $type->id, 'status' => 'draft', 'sort_order' => $i]);
      Models\Media::create(['filename' => strtolower($letter).'.txt', 'original_name' => strtolower($letter).'.txt', 'title' => $letter.' media', 'kind' => 'document', 'disk' => 'public', 'path' => 'synthetic/'.strtolower($letter).'.txt', 'mime_type' => 'text/plain', 'size' => 24, 'uploaded_by' => $actor->id]);
      Models\SharedSlot::create(['site_id' => $site->id, 'name' => $letter.' shared', 'handle' => strtolower($letter), 'slot_name' => 'header', 'public_shell' => 'default', 'is_active' => true]);
      Models\AutomaticPageRule::create(['site_id' => $site->id, 'name' => $letter.' rule', 'source_handle' => 'synthetic-source', 'base_path' => strtolower($letter)]);
      Models\EmbeddedApplication::create(['name' => $letter.' app', 'handle' => strtolower($letter), 'render_mode' => 'iframe', 'entry_url' => 'https://example.test/synthetic', 'is_enabled' => false]);
      Models\IconCatalogItem::create(['source' => 'synthetic', 'slug' => strtolower($letter), 'label' => $letter.' icon', 'is_active' => true]);
      Models\CmsRole::create(['name' => $letter.' role', 'handle' => strtolower($letter).'-sort-role', 'is_protected' => false]);
      foreach (['system', 'personal'] as $tokenType) {
        Models\CmsApiToken::create(['name' => $letter.' token', 'token_type' => $tokenType, 'token_hash' => hash('sha256', 'synthetic-sort-'.$i.$tokenType), 'token_preview' => 'synthetic', 'created_by_user_id' => $actor->id]);
      }
      Models\CommentEntry::create(['site_id' => $site->id, 'page_id' => $page->id, 'body' => 'Synthetic feedback', 'status' => 'pending', 'spam_score' => $i * 10]);
      Models\ContentRating::create(['site_id' => $site->id, 'page_id' => $page->id, 'rating_value' => ($i + 1) * 2, 'rating_max' => 5, 'status' => 'active']);
      Models\SystemBackup::create(['status' => 'failed', 'type' => 'manual', 'label' => $letter.' backup', 'archive_size_bytes' => ($i + 1) * 100]);
      Models\SiteExport::create(['site_id' => $site->id, 'user_id' => $actor->id, 'status' => 'failed', 'archive_size_bytes' => ($i + 1) * 100]);
      Models\SiteImport::create(['user_id' => $actor->id, 'status' => 'failed', 'source_archive_name' => $letter.'.zip']);
    }
  }

  public static function listings(): array
  {
    return [
      'pages' => ['pages', 'admin.pages.index', 'title', 'pages', 'title'],
      'media' => ['media', 'admin.media.index', 'title', 'media', 'title'],
      'shared slots' => ['shared-slots', 'admin.shared-slots.index', 'name', 'sharedSlots', 'name'],
      'blocks' => ['blocks', 'admin.blocks.index', 'id', 'blocks', 'id'],
      'automatic pages' => ['automatic-pages', 'admin.automatic-pages.index', 'name', 'rules', 'name'],
      'sites' => ['sites', 'admin.sites.index', 'name', 'sites', 'name'],
      'domains' => ['domains', 'admin.domains.index', 'name', 'sites', 'name'],
      'site domains' => ['site-domains', 'admin.sites.domains.index', 'domain', 'domains', 'domain'],
      'locales' => ['locales', 'admin.locales.index', 'name', 'locales', 'name'],
      'page layouts' => ['page-layouts', 'admin.page-layouts.index', 'name', 'pageLayouts', 'name'],
      'slot types' => ['slot-types', 'admin.slot-types.index', 'name', 'slotTypes', 'name'],
      'block types' => ['block-types', 'admin.block-types.index', 'name', 'blockTypes', 'name'],
      'icons' => ['icons', 'admin.system.icons.index', 'label', 'icons', 'label'],
      'applications' => ['embedded-applications', 'admin.embedded-applications.index', 'name', 'applications', 'name'],
      'users' => ['users', 'admin.users.index', 'name', 'users', 'name'],
      'roles' => ['roles', 'admin.access.roles.index', 'name', 'roles', 'name'],
      'system tokens' => ['api-tokens', 'admin.system.api-tokens.index', 'name', 'tokens', 'name'],
      'personal tokens' => ['personal-api-tokens', 'admin.profile.api-tokens.index', 'name', 'tokens', 'name'],
      'engagement' => ['engagement', 'admin.engagement.index', 'average', 'pageSummary', 'average_rating'],
      'comments' => ['comments', 'admin.engagement.comments.index', 'spam_score', 'comments', 'spam_score'],
      'ratings' => ['ratings', 'admin.engagement.ratings.index', 'rating_value', 'ratings', 'rating_value'],
      'backups' => ['backups', 'admin.system.backups.index', 'archive_size_bytes', 'backups', 'archive_size_bytes'],
      'exports' => ['exports', 'admin.site-transfers.exports.index', 'archive_size_bytes', 'exports', 'archive_size_bytes'],
      'imports' => ['imports', 'admin.site-transfers.exports.index', 'source_archive_name', 'imports', 'source_archive_name'],
    ];
  }

  #[Test]
  #[DataProvider('listings')]
  public function approved_lists_render_and_execute_sort_links_in_both_directions(string $listing, string $route, string $key, string $data, string $attribute): void
  {
    $sort = AdminTableSort::for($listing);
    foreach (['asc', 'desc'] as $direction) {
      $parameters = [$sort->sortParam() => $key, $sort->directionParam() => $direction];
      if ($listing === 'site-domains') {
        $parameters['site'] = $this->site->id;
      } elseif (in_array($listing, ['pages', 'shared-slots', 'domains'], true)) {
        $parameters['site'] = 'all';
      }
      $response = $this->get(route($route, $parameters))->assertOk();
      $values = $response->viewData($data)->pluck($attribute)->all();
      $this->assertGreaterThanOrEqual(2, count($values));
      $expected = $values;
      $direction === 'asc' ? sort($expected) : rsort($expected);
      $this->assertSame($expected, $values);
      $dom = new DOMDocument;
      @$dom->loadHTML($response->getContent());
      $xpath = new DOMXPath($dom);
      $header = $xpath->query('//table[@data-wb-sort-param="'.$sort->sortParam().'"]//th[@data-wb-sort="'.$key.'"]')->item(0);
      $this->assertNotNull($header);
      $this->assertSame($direction === 'asc' ? 'ascending' : 'descending', $header->getAttribute('aria-sort'));
      $link = $xpath->query('.//a', $header)->item(0);
      parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
      $this->assertSame($direction === 'asc' ? 'desc' : 'asc', $query[$sort->directionParam()]);
      $this->assertSame('1', $query[$sort->pageParam()]);
    }
  }

  #[Test]
  public function ordering_is_applied_to_the_entire_filtered_dataset_before_pagination(): void
  {
    for ($i = 30; $i >= 1; $i--) {
      Models\PageLayout::create(['name' => sprintf('Filter %02d', $i), 'handle' => 'filter-'.$i, 'is_active' => true]);
    }
    $perPage = app(SystemSettings::class)->adminListingPerPage();
    $response = $this->get(route('admin.page-layouts.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk();
    $names = $response->viewData('pageLayouts')->pluck('name')->all();
    $expected = Models\PageLayout::query()->orderBy('name')->limit($perPage)->pluck('name')->all();
    $this->assertSame($expected, $names);
    $this->assertGreaterThan($perPage, $response->viewData('pageLayouts')->total());
  }

  #[Test]
  public function transfer_tables_keep_independent_sort_and_page_parameters(): void
  {
    $response = $this->get(route('admin.site-transfers.exports.index', ['exports_sort' => 'archive_size_bytes', 'exports_direction' => 'asc', 'imports_sort' => 'source_archive_name', 'imports_direction' => 'desc']))->assertOk();
    $this->assertSame([100, 200], $response->viewData('exports')->pluck('archive_size_bytes')->all());
    $this->assertSame(['Z.zip', 'A.zip'], $response->viewData('imports')->pluck('source_archive_name')->all());
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $link = (new DOMXPath($dom))->query('//table[@data-wb-sort-param="exports_sort"]//th[@data-wb-sort="archive_size_bytes"]/a')->item(0);
    parse_str(parse_url($link->getAttribute('href'), PHP_URL_QUERY), $query);
    $this->assertSame('desc', $query['exports_direction']);
    $this->assertSame('desc', $query['imports_direction']);
    $this->assertSame('source_archive_name', $query['imports_sort']);
    $this->assertArrayNotHasKey('page', $query);
  }

  #[Test]
  public function untrusted_sort_keys_and_array_inputs_fall_back_without_becoming_sql(): void
  {
    foreach ([['sort' => 'name desc; drop table users', 'direction' => 'sideways'], ['sort' => ['name'], 'direction' => ['desc']]] as $query) {
      $this->get(route('admin.page-layouts.index', $query))->assertOk()
        ->assertViewHas('pageLayouts', fn ($rows) => $rows->pluck('sort_order')->all() === [0, 1]);
    }
  }

  #[Test]
  public function filter_forms_retain_header_sorting_without_duplicating_existing_sort_controls(): void
  {
    $response = $this->get(route('admin.media.index', ['sort' => 'title', 'direction' => 'asc', 'kind' => 'document']))->assertOk();
    $dom = new DOMDocument;
    @$dom->loadHTML($response->getContent());
    $xpath = new DOMXPath($dom);
    $this->assertSame(1, $xpath->query('//form[@data-admin-listing-filters]//*[@name="sort"]')->length);
    $this->assertSame(1, $xpath->query('//form[@data-admin-listing-filters]//*[@name="direction"]')->length);
    $this->assertSame('title', $xpath->query('//select[@name="sort"]/option[@selected]')->item(0)->getAttribute('value'));
    $this->assertSame('asc', $xpath->query('//select[@name="direction"]/option[@selected]')->item(0)->getAttribute('value'));
    $response = $this->get(route('admin.users.index', ['sort' => 'email', 'direction' => 'desc']))->assertOk();
    $this->assertStringContainsString('name="sort" value="email"', $response->getContent());
    $this->assertStringContainsString('name="direction" value="desc"', $response->getContent());
  }

  #[Test]
  public function sorting_never_broadens_the_current_users_site_or_token_scope(): void
  {
    $editor = User::where('name', 'A editor')->firstOrFail();
    $this->actingAs($editor);
    $this->get(route('admin.pages.index', ['site' => 'all', 'sort' => 'title', 'direction' => 'desc']))->assertOk()
      ->assertViewHas('pages', fn ($pages) => $pages->pluck('site_id')->all() === [$editor->sites()->first()->id]);
    $this->get(route('admin.profile.api-tokens.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk()
      ->assertViewHas('tokens', fn ($tokens) => $tokens->isEmpty());
  }

  #[Test]
  public function a_direction_only_request_and_equal_sort_keys_have_truthful_stable_results(): void
  {
    $this->get(route('admin.page-layouts.index', ['direction' => 'desc']))->assertOk()
      ->assertViewHas('pageLayouts', fn ($rows) => $rows->pluck('sort_order')->all() === [1, 0]);
    Models\PageLayout::query()->update(['name' => 'Equal name']);
    $first = $this->get(route('admin.page-layouts.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk()->viewData('pageLayouts')->pluck('id')->all();
    $second = $this->get(route('admin.page-layouts.index', ['sort' => 'name', 'direction' => 'desc']))->assertOk()->viewData('pageLayouts')->pluck('id')->all();
    $this->assertSame($first, $second);
    $expected = $first;
    sort($expected);
    $this->assertSame($expected, $first);
  }

  #[Test]
  public function aggregate_columns_sort_by_their_sql_counts(): void
  {
    Models\Page::create(['site_id' => $this->site->id, 'title' => 'Extra page', 'slug' => 'extra-page', 'status' => 'draft']);
    $this->get(route('admin.sites.index', ['sort' => 'pages_count', 'direction' => 'asc']))->assertOk()
      ->assertViewHas('sites', fn ($sites) => $sites->pluck('pages_count')->all() === [1, 2]);
    $this->get(route('admin.domains.index', ['site' => 'all', 'sort' => 'site_domains_count', 'direction' => 'asc']))->assertOk()
      ->assertViewHas('sites', fn ($sites) => $sites->pluck('site_domains_count')->all() === [0, 2]);
    $response = $this->get(route('admin.access.roles.index', ['sort' => 'assignment_count', 'direction' => 'desc', 'history_action' => 'role.saved']))->assertOk();
    $counts = $response->viewData('roles')->pluck('assignment_count')->all();
    $this->assertGreaterThan(0, max($counts));
    $expected = $counts;
    rsort($expected);
    $this->assertSame($expected, $counts);
  }

  #[Test]
  public function sorting_controls_have_translated_accessible_names_in_every_panel_language(): void
  {
    foreach (['en', 'tr', 'de', 'fr', 'es', 'it'] as $locale) {
      auth()->user()->forceFill(['admin_locale' => $locale])->save();
      $response = $this->get(route('admin.page-layouts.index', ['sort' => 'name', 'direction' => 'asc']))->assertOk();
      $this->assertStringContainsString('aria-label="'.e(__('webblocks-cms::table_sort.listings.page-layouts')).'"', $response->getContent());
      $this->assertStringContainsString('data-wb-sort-label-asc="'.e(__('webblocks-cms::table_sort.label_asc')).'"', $response->getContent());
      $this->assertStringNotContainsString('webblocks-cms::table_sort.', $response->getContent());
    }
  }
}
