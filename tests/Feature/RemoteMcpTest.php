<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsRole;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RemoteMcpTest extends TestCase
{
  private const ORIGIN = 'https://cms.example.test';

  private const MCP = self::ORIGIN.'/webadmin/mcp';

  private const REDIRECT = 'https://client.example.test/oauth/callback';

  private ?string $defaultClient = null;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('app.maintenance', ['driver' => 'cache', 'store' => 'array']);
    $app['config']->set('app.url', self::ORIGIN);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(RemoteMcpTestUser::class, User::class);
    }
    Auth::provider('mcp-test-users', fn ($app) => new class($app['hash'], User::class) extends EloquentUserProvider
    {
      public function createModel()
      {
        return parent::createModel()->setTable('users');
      }
    });
    config(['auth.providers.users.driver' => 'mcp-test-users', 'auth.providers.users.model' => User::class]);
    $this->setMcp(true);
  }

  public function test_discovery_is_opt_in_and_advertises_pkce_and_media_upload(): void
  {
    $this->get(self::ORIGIN.'/.well-known/oauth-protected-resource/webadmin/mcp')
      ->assertOk()->assertJsonPath('resource', self::MCP);
    $this->get(self::ORIGIN.'/.well-known/oauth-authorization-server/webadmin/mcp')
      ->assertOk()->assertJsonPath('issuer', self::MCP)->assertJsonPath('registration_endpoint', self::MCP.'/register')
      ->assertJsonPath('code_challenge_methods_supported', ['S256'])
      ->assertJsonPath('scopes_supported', ['pages:read', 'pages:write-draft', 'pages:write-content-draft', 'pages:create-draft', 'pages:write-draft-structure', 'pages:write-draft-translation', 'media:upload', 'pages:read-content', 'pages:read-draft-structure', 'pages:read-draft-translation', 'media:read', 'pages:write-draft-seo', 'notes:read', 'notes:write', 'notes:delete']);
    $this->rpc('initialize', ['protocolVersion' => '2025-03-26'])->assertJsonPath('result.protocolVersion', '2025-03-26');
    $this->rpc('tools/list')->assertJsonCount(22, 'result.tools')
      ->assertJsonPath('result.tools.1.annotations.readOnlyHint', false)
      ->assertJsonPath('result.tools.3.annotations.readOnlyHint', true)
      ->assertJsonPath('result.tools.4.annotations.readOnlyHint', false)
      ->assertJsonPath('result.tools.4.securitySchemes.0.scopes', ['pages:read', 'pages:write-content-draft'])
      ->assertJsonPath('result.tools.5.name', 'search_pages')
      ->assertJsonPath('result.tools.5.annotations.readOnlyHint', true)
      ->assertJsonPath('result.tools.5.securitySchemes.0.scopes', ['pages:read'])
      ->assertJsonPath('result.tools.7.name', 'create_page_draft')
      ->assertJsonPath('result.tools.7.annotations.idempotentHint', true)
      ->assertJsonPath('result.tools.7.securitySchemes.0.scopes', ['pages:read', 'pages:create-draft'])
      ->assertJsonPath('result.tools.8.name', 'get_draft_structure')
      ->assertJsonPath('result.tools.8.securitySchemes.0.scopes', ['pages:read', 'pages:read-draft-structure'])
      ->assertJsonPath('result.tools.9.securitySchemes.0.scopes', ['pages:read', 'pages:write-draft-structure'])
      ->assertJsonPath('result.tools.12.name', 'upload_media')
      ->assertJsonPath('result.tools.12.securitySchemes.0.scopes', ['pages:read', 'media:upload'])
      ->assertJsonPath('result.tools.13.name', 'update_draft_seo')
      ->assertJsonPath('result.tools.13.securitySchemes.0.scopes', ['pages:read', 'pages:write-draft-seo']);
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], status: 401)
      ->assertHeader('WWW-Authenticate')->assertJsonPath('result.structuredContent.code', 'auth_required')
      ->assertJsonPath('result._meta.mcp/www_authenticate.0', fn ($header) => str_contains($header, 'resource_metadata='));
    $this->get(self::MCP, ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
    $this->get(self::MCP)->assertStatus(405);
    $this->postJson('http://cms.example.test/webadmin/mcp', [])->assertNotFound();
    $this->setMcp(false);
    $this->postJson(self::MCP, [])->assertNotFound();
  }

  public function test_search_pages_paginates_only_the_authorized_sites_published_editorial_translations(): void
  {
    [$page] = $this->pageFixture(path: '/guides/start');
    $site = $page->site;
    for ($index = 1; $index <= 20; $index++) {
      Page::create(['site_id' => $site->id, 'title' => 'Guides '.$index, 'slug' => 'guide-'.$index, 'status' => Page::STATUS_PUBLISHED]);
    }
    foreach ([Page::TYPE_SYSTEM, Page::TYPE_SHARED_SLOT_SOURCE, Page::TYPE_DEFAULT] as $type) {
      Page::create([
        'site_id' => $site->id, 'title' => 'Guides '.$type, 'slug' => 'internal-'.$type, 'page_type' => $type,
        'status' => $type === Page::TYPE_DEFAULT ? Page::STATUS_DRAFT : Page::STATUS_PUBLISHED,
      ]);
    }
    Page::create([
      'site_id' => $site->id, 'title' => 'Guides revision', 'slug' => 'revision', 'status' => Page::STATUS_PUBLISHED,
      'settings' => ['revision_restore_candidate' => ['state' => 'preparing']],
    ]);
    $other = Site::create(['handle' => 'other', 'name' => 'Other']);
    Page::create(['site_id' => $other->id, 'title' => 'Guides other', 'slug' => 'other', 'status' => Page::STATUS_PUBLISHED]);

    $reference = ['q' => 'guides', 'locale' => 'en'];
    $this->tool('search_pages', $reference, status: 401)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $token = $this->connect($this->operator(), $site->id, scope: 'pages:read');
    $this->tool('search_pages', $reference, $token)
      ->assertJsonCount(20, 'result.structuredContent.pages')
      ->assertJsonPath('result.structuredContent.pages.0', ['page_id' => $page->id, 'name' => 'Home', 'path' => '/guides/start'])
      ->assertJsonPath('result.structuredContent.next_page', 2);
    $this->tool('search_pages', $reference + ['page' => 2], $token)
      ->assertJsonCount(1, 'result.structuredContent.pages')
      ->assertJsonPath('result.structuredContent.pages.0.name', 'Guides 20')
      ->assertJsonPath('result.structuredContent.next_page', null);
    $this->tool('search_pages', ['q' => 'Ana sayfa', 'locale' => 'tr'], $token)
      ->assertJsonCount(1, 'result.structuredContent.pages')
      ->assertJsonPath('result.structuredContent.pages.0.page_id', $page->id);
    $this->tool('search_pages', ['q' => '%', 'locale' => 'en'], $token)
      ->assertJsonCount(0, 'result.structuredContent.pages');
    Locale::create(['code' => 'fr', 'name' => 'French', 'is_enabled' => true]);
    $this->tool('search_pages', ['q' => 'Guides', 'locale' => 'fr'], $token)
      ->assertJsonPath('result.structuredContent.code', 'locale_unavailable');
    $this->tool('search_pages', $reference + ['page' => 1001], $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_request');
  }

  public function test_page_draft_creation_requires_new_consent_and_replays_the_private_preview(): void
  {
    [$source, $hero] = $this->pageFixture();
    $layout = PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    $layout->layoutSlots()->create(['slot_type_id' => $hero->slot_type_id, 'slot_name' => 'main', 'label' => 'Main', 'is_active' => true]);
    $user = $this->operator();
    $input = ['locale' => 'tr', 'title' => 'MCP taslağı', 'path' => '/rehber/mcp'];
    $oldToken = $this->connect($user, $source->site_id, scope: 'pages:read pages:write-content-draft');
    $this->tool('create_page_draft', $input, $oldToken, 403)
      ->assertJsonPath('result.structuredContent.code', 'auth_required')
      ->assertJsonPath('result._meta.mcp/www_authenticate.0', fn ($header) => str_contains($header, 'scope="pages:read pages:create-draft"'));

    $token = $this->connect($user, $source->site_id, scope: 'pages:read pages:create-draft');
    $result = $this->tool('create_page_draft', $input, $token)
      ->assertJsonPath('result.structuredContent.path', '/rehber/mcp')
      ->json('result.structuredContent');
    $renewedToken = $this->connect($user, $source->site_id, scope: 'pages:read pages:create-draft');
    $this->tool('create_page_draft', array_replace($input, ['title' => ' MCP taslağı ', 'path' => 'rehber/mcp/', 'layout' => 'default']), $renewedToken)
      ->assertJsonPath('result.structuredContent', $result);

    $page = Page::findOrFail($result['draft_id']);
    $this->assertSame($page->id, $result['page_id']);
    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertSame($user->id, $page->created_by_user_id);
    $this->assertNull($page->published_at);
    $this->assertSame($hero->slot_type_id, $page->slots()->sole()->slot_type_id);
    $this->assertSame('tr', $page->translations()->sole()->locale->code);
    $this->assertSame(1, $page->revisions()->count());
    $this->assertSame(2, Page::count());
    $this->assertSame(1, DB::table('wbcms_mcp_page_drafts')->count());

    $this->get($result['preview_url'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('MCP taslağı');
    app('auth')->forgetGuards();
    $this->get($result['preview_url'])->assertRedirect();
  }

  public function test_page_draft_creation_distinguishes_path_conflicts_from_retries(): void
  {
    [$source] = $this->pageFixture();
    PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    $user = $this->operator();
    $token = $this->connect($user, $source->site_id, scope: 'pages:read pages:create-draft');
    $input = ['locale' => 'en', 'title' => 'MCP draft', 'path' => '/guides/0'];
    $this->tool('create_page_draft', array_replace($input, ['path' => '/']), $token)
      ->assertJsonPath('result.structuredContent.code', 'page_path_conflict');
    $this->tool('create_page_draft', array_replace($input, ['path' => '/guides/home']), $token)
      ->assertJsonPath('result.structuredContent.code', 'page_path_conflict');
    $this->tool('create_page_draft', array_replace($input, ['path' => '/webadmin/test']), $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_page_path');
    $this->tool('create_page_draft', array_replace($input, ['path' => str_repeat('a', 255)]), $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_page_path');
    $this->tool('create_page_draft', $input + ['layout' => 'missing'], $token)
      ->assertJsonPath('result.structuredContent.code', 'layout_unavailable');
    $this->tool('create_page_draft', array_replace($input, ['locale' => 'fr']), $token)
      ->assertJsonPath('result.structuredContent.code', 'locale_unavailable');
    $this->assertSame(1, Page::count());

    $draftId = $this->tool('create_page_draft', $input, $token)->json('result.structuredContent.draft_id');
    $this->assertSame('0', Page::findOrFail($draftId)->translations()->sole()->slug);
    $this->tool('create_page_draft', array_replace($input, ['title' => 'Different draft']), $token)
      ->assertJsonPath('result.structuredContent.code', 'page_path_conflict');
    $otherToken = $this->connect($this->operator(), $source->site_id, scope: 'pages:read pages:create-draft');
    $this->tool('create_page_draft', $input, $otherToken)->assertJsonPath('result.structuredContent.code', 'page_path_conflict');

    Page::findOrFail($draftId)->delete();
    $this->tool('create_page_draft', $input, $token)->assertJsonPath('result.structuredContent.code', 'created_draft_unavailable');
    $this->assertSame(1, Page::count());
    $this->assertSame(1, DB::table('wbcms_mcp_page_drafts')->count());
  }

  public function test_new_draft_and_mcp_creation_records_roll_back_together_and_allow_retry(): void
  {
    [$source, $hero] = $this->pageFixture();
    $layout = PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    $layout->layoutSlots()->create(['slot_type_id' => $hero->slot_type_id, 'slot_name' => 'main', 'is_active' => true]);
    $token = $this->connect($this->operator(), $source->site_id, scope: 'pages:read pages:create-draft', draftAccess: 'created');
    $input = ['locale' => 'en', 'title' => 'MCP retry', 'path' => '/guides/mcp-retry'];
    $armed = true;
    DB::listen(function (QueryExecuted $query) use (&$armed): void {
      if ($armed && str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'wbcms_mcp_page_drafts')) {
        $armed = false;
        throw new \RuntimeException('Synthetic failure after the MCP receipt was written.');
      }
    });

    $this->tool('create_page_draft', $input, $token, status: 500);
    $this->assertFalse($armed);
    $this->assertDatabaseCount('wbcms_pages', 1);
    $this->assertDatabaseCount('wbcms_page_translations', 2);
    $this->assertDatabaseCount('wbcms_page_slots', 1);
    $this->assertDatabaseEmpty('wbcms_page_revisions');
    $this->assertDatabaseEmpty('wbcms_mcp_page_drafts');
    $this->assertDatabaseEmpty('wbcms_mcp_grant_drafts');

    $draft = $this->tool('create_page_draft', $input, $token)->json('result.structuredContent');
    $page = Page::findOrFail($draft['draft_id']);
    $this->assertSame(Page::STATUS_DRAFT, $page->status);
    $this->assertSame($input['path'], $page->translations()->sole()->path);
    $this->assertSame($hero->slot_type_id, $page->slots()->sole()->slot_type_id);
    $this->assertSame('page_created', $page->revisions()->sole()->event);
    $this->tool('create_page_draft', $input, $token)->assertJsonPath('result.structuredContent', $draft);
    $this->assertDatabaseCount('wbcms_mcp_page_drafts', 1);
    $this->assertDatabaseCount('wbcms_mcp_grant_drafts', 1);
  }

  public function test_standalone_draft_structure_can_add_move_and_reorder_without_stale_or_partial_writes(): void
  {
    [$page, $hero] = $this->pageFixture();
    $page->update(['status' => Page::STATUS_DRAFT]);
    $aside = SlotType::create(['slug' => 'aside', 'name' => 'Aside', 'status' => 'published', 'sort_order' => 1]);
    $page->slots()->create(['slot_type_id' => $aside->id, 'source_type' => 'page', 'sort_order' => 1]);
    foreach (['stack', 'plain_text'] as $type) {
      BlockType::create(['slug' => $type, 'name' => $type, 'status' => 'published']);
    }
    $user = $this->operator();
    $old = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-content-draft pages:read-content');
    $reference = ['page_id' => $page->id, 'locale' => 'en'];
    $this->tool('get_draft_structure', $reference, $old, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $token = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-draft-structure pages:read-draft-structure');
    $version = $this->tool('get_draft_structure', $reference, $token)
      ->assertJsonPath('result.structuredContent.slots.0', ['name' => 'main', 'managed' => true])
      ->json('result.structuredContent.version');

    $stack = $this->tool('edit_draft_structure', $reference + [
      'version' => $version, 'operation' => 'add', 'slot' => 'main', 'type' => 'stack',
    ], $token)->assertJsonPath('result.structuredContent.draft_id', $page->id)->json('result.structuredContent');
    $this->tool('edit_draft_structure', $reference + [
      'version' => $stack['version'], 'operation' => 'add', 'slot' => 'aside', 'type' => 'plain_text',
      'parent_id' => $stack['block_ids'][0], 'text' => ['content' => 'Wrong slot'],
    ], $token)->assertJsonPath('result.structuredContent.code', 'invalid_block_placement');
    $this->assertSame(2, $page->blocks()->count());
    $paragraph = $this->tool('edit_draft_structure', $reference + [
      'version' => $stack['version'], 'operation' => 'add', 'slot' => 'main', 'type' => 'plain_text',
      'text' => ['content' => 'MCP paragraph'],
    ], $token)->assertJsonPath('result.structuredContent.draft_id', $page->id)->json('result.structuredContent');
    $move = $reference + [
      'operation' => 'move', 'slot' => 'main', 'parent_id' => $stack['block_ids'][0], 'block_id' => $paragraph['block_ids'][0],
    ];
    $this->tool('edit_draft_structure', $move + ['version' => $stack['version']], $token)
      ->assertJsonPath('result.structuredContent.code', 'conflict');
    $moved = $this->tool('edit_draft_structure', $move + ['version' => $paragraph['version']], $token)
      ->assertJsonPath('result.structuredContent.block_ids', $paragraph['block_ids'])->json('result.structuredContent');
    $this->assertSame($stack['block_ids'][0], Block::findOrFail($paragraph['block_ids'][0])->parent_id);

    $this->tool('edit_draft_structure', array_replace($move, [
      'block_id' => $stack['block_ids'][0], 'parent_id' => $paragraph['block_ids'][0], 'version' => $moved['version'],
    ]), $token)->assertJsonPath('result.structuredContent.code', 'invalid_block_placement');
    $reorder = $reference + ['version' => $moved['version'], 'operation' => 'reorder', 'slot' => 'main'];
    $this->tool('edit_draft_structure', $reorder + ['block_ids' => [$hero->id]], $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_block_order');
    $order = [$stack['block_ids'][0], $hero->id];
    $preview = $this->tool('edit_draft_structure', $reorder + ['block_ids' => $order], $token)
      ->assertJsonPath('result.structuredContent.block_ids', $order)->json('result.structuredContent.preview_url');

    $this->assertSame($order, $page->blocks()->whereNull('parent_id')->orderBy('sort_order')->pluck('id')->all());
    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    $this->assertSame(4, $page->revisions()->count());
    $this->get($preview)->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('MCP paragraph');
  }

  public function test_draft_structure_assembles_a_split_and_requires_an_exchange_to_move_out_of_it(): void
  {
    [$page, $heading] = $this->pageFixture(type: 'header');
    $page->update(['status' => Page::STATUS_DRAFT]);
    $splitType = BlockType::create(['slug' => 'split', 'name' => 'Split', 'status' => 'published']);
    $plain = BlockType::create(['slug' => 'plain_text', 'name' => 'Plain Text', 'status' => 'published']);
    $split = $page->blocks()->create([
      'type' => 'split', 'block_type_id' => $splitType->id, 'slot_type_id' => $heading->slot_type_id, 'status' => 'draft',
    ]);
    $replacement = $page->blocks()->create([
      'block_type_id' => $plain->id, 'slot_type_id' => $heading->slot_type_id, 'status' => 'draft', 'sort_order' => 7,
    ]);
    $heading->update(['parent_id' => $split->id, 'sort_order' => 0]);
    $token = $this->connect($this->operator(), $page->site_id, 'pages:read pages:write-draft-structure pages:read-draft-structure');
    $reference = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_structure', $reference, $token)->json('result.structuredContent.version');
    $add = $reference + [
      'operation' => 'add', 'slot' => 'main', 'type' => 'plain_text', 'parent_id' => $split->id,
      'text' => ['content' => 'Second side'],
    ];

    $added = $this->tool('edit_draft_structure', $add + ['version' => $version], $token)
      ->assertJsonPath('result.structuredContent.draft_id', $page->id)->json('result.structuredContent');
    $this->tool('edit_draft_structure', $add + ['version' => $added['version']], $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_block_placement');
    $this->assertSame(2, $split->children()->count());
    $this->assertSame(1, $page->revisions()->count());

    $move = $reference + [
      'operation' => 'move', 'slot' => 'main', 'block_id' => $heading->id, 'version' => $added['version'],
    ];
    $this->tool('edit_draft_structure', $move, $token)->assertJsonPath('result.structuredContent.code', 'invalid_block_placement');
    $this->tool('edit_draft_structure', $move + ['replacement_block_id' => $added['block_ids'][0]], $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_block_placement');
    $this->assertSame($split->id, $heading->fresh()->parent_id);
    $this->assertSame(1, $page->revisions()->count());

    $this->tool('edit_draft_structure', $move + ['replacement_block_id' => $replacement->id], $token)
      ->assertJsonPath('result.structuredContent.block_ids', [$heading->id, $replacement->id]);
    $this->assertNull($heading->fresh()->parent_id);
    $this->assertSame(7, $heading->fresh()->sort_order);
    $this->assertSame($split->id, $replacement->fresh()->parent_id);
    $this->assertSame(0, $replacement->fresh()->sort_order);
    $this->assertSame(2, $split->children()->count());
    $this->assertSame(2, $page->revisions()->count());
  }

  public function test_staged_structure_moves_require_both_slots_to_be_promoted_together(): void
  {
    [$source, $hero] = $this->pageFixture();
    $stack = $source->blocks()->create(['type' => 'stack', 'slot_type_id' => $hero->slot_type_id, 'status' => 'published']);
    $hero->update(['parent_id' => $stack->id]);
    $aside = SlotType::create(['slug' => 'aside', 'name' => 'Aside', 'status' => 'published', 'sort_order' => 1]);
    $source->slots()->create(['slot_type_id' => $aside->id, 'source_type' => 'page', 'sort_order' => 1]);
    $token = $this->connect($this->operator(), $source->site_id, scope: 'pages:read pages:write-content-draft pages:write-draft-structure pages:read-draft-structure pages:read-content');
    $reference = ['page_id' => $source->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_structure', $reference, $token)
      ->assertJsonPath('result.structuredContent.draft_id', null)->json('result.structuredContent.version');
    $prepared = $this->tool('edit_draft_structure', $reference + [
      'version' => $version, 'operation' => 'prepare', 'slots' => ['main', 'aside'],
    ], $token)->json('result.structuredContent');
    $tree = $this->tool('get_draft_structure', $reference, $token)
      ->assertJsonPath('result.structuredContent.version', $prepared['version'])->json('result.structuredContent');
    $draftStackId = collect($tree['blocks'])->firstWhere('type', 'stack')['block_id'];
    $this->tool('edit_draft_structure', $reference + [
      'version' => $tree['version'], 'operation' => 'move', 'slot' => 'aside', 'block_id' => $draftStackId,
    ], $token)->assertJsonPath('result.structuredContent.draft_id', $prepared['draft_id']);
    $this->assertSame($hero->slot_type_id, $hero->fresh()->slot_type_id);
    $draftBlocks = Page::findOrFail($prepared['draft_id'])->blocks;
    $this->assertSame([$aside->id], $draftBlocks->pluck('slot_type_id')->unique()->values()->all());
    $this->assertSame($draftStackId, $draftBlocks->firstWhere('type', 'hero')->parent_id);
    $contentVersion = $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.write.blocked_by', null)->json('result.structuredContent.version');
    $this->tool('update_draft_block', $reference + [
      'version' => $contentVersion, 'block_id' => $hero->id, 'text' => ['title' => 'Moved hero'],
    ], $token)->assertJsonPath('result.structuredContent.draft_id', $prepared['draft_id']);

    $plans = app(InternalContentPlanService::class);
    $promotion = [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $prepared['draft_id'],
      'expected_source_page_id' => $source->id, 'expected_source_path' => '/',
    ];
    $partial = $plans->validate(['plan' => $promotion + ['promote_slots' => ['aside']]]);
    $this->assertFalse($partial->ok);
    $this->assertContains('Promote both slots together when a block has moved between them.', array_column($partial->errors, 'message'));
    $full = $plans->apply(['plan' => $promotion + ['promote_slots' => ['main', 'aside']]]);
    $this->assertTrue($full->ok, json_encode($full->errors));
    $published = $source->blocks()->get();
    $this->assertSame([$aside->id], $published->pluck('slot_type_id')->unique()->values()->all());
    $this->assertSame($published->firstWhere('type', 'stack')->id, $published->firstWhere('type', 'hero')->parent_id);
    $this->assertSame('Moved hero', $published->firstWhere('type', 'hero')->textTranslations()->orderBy('locale_id')->firstOrFail()->title);
  }

  public function test_mcp_translation_applies_only_selected_fields_and_rejects_a_changed_source(): void
  {
    [$page, $heading] = $this->pageFixture(path: '/original');
    $en = Locale::where('code', 'en')->sole();
    $sourceTranslation = $page->translationForLocale($en);
    $sourceTranslation->update(['seo_title' => 'Original SEO', 'og_description' => 'Keep this']);
    $user = $this->operator();
    $old = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-content-draft pages:read-content');
    $reference = ['page_id' => $page->id, 'locale' => 'en'];
    $this->tool('get_draft_translation', $reference, $old, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $token = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-draft-translation pages:read-draft-translation');
    $version = $this->tool('get_draft_translation', $reference, $token)->json('result.structuredContent.version');
    $input = $reference + ['version' => $version, 'name' => 'Edited name', 'path' => '/new-path', 'seo_title' => 'Edited SEO'];
    $result = $this->tool('update_draft_translation', $input, $token)
      ->assertJsonPath('result.structuredContent.translation.path', '/new-path')->json('result.structuredContent');
    $this->tool('update_draft_translation', $input, $token)->assertJsonPath('result.structuredContent.code', 'conflict');
    $this->get($result['preview_url'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Edited SEO');
    $this->assertSame('/original', $sourceTranslation->fresh()->path);
    $this->assertSame('Original SEO', $sourceTranslation->fresh()->seo_title);

    $plans = app(InternalContentPlanService::class);
    $promotion = ['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $result['draft_id'], 'expected_source_path' => '/original',
    ]];
    $sourceTranslation->update(['seo_title' => 'Concurrent SEO']);
    $failed = $plans->apply($promotion);
    $this->assertFalse($failed->ok);
    $this->assertContains('The published page changed after the translation draft was prepared.', array_column($failed->errors, 'message'));
    $this->assertSame('Concurrent SEO', $sourceTranslation->fresh()->seo_title);

    $sourceTranslation->update(['seo_title' => 'Original SEO']);
    $competitor = Page::create(['site_id' => $page->site_id, 'title' => 'Other page', 'slug' => 'new-path', 'status' => Page::STATUS_DRAFT]);
    $collision = $plans->apply($promotion);
    $this->assertFalse($collision->ok);
    $this->assertContains('This path is already used in this site for this locale.', array_column($collision->errors, 'message'));
    $competitor->delete();
    $draft = Page::findOrFail($result['draft_id']);
    $draft->translationForLocale($en)->update(['og_description' => 'Unselected draft change']);
    $applied = $plans->apply($promotion);
    $this->assertTrue($applied->ok, json_encode($applied->errors));
    $this->assertSame('/new-path', $sourceTranslation->fresh()->path);
    $this->assertSame('Edited name', $sourceTranslation->fresh()->name);
    $this->assertSame('Edited SEO', $sourceTranslation->fresh()->seo_title);
    $this->assertSame('Keep this', $sourceTranslation->fresh()->og_description);
    $this->assertSame($heading->id, $page->blocks()->sole()->id);
  }

  public function test_mcp_can_add_an_enabled_locale_in_a_draft_and_clear_seo_fields(): void
  {
    [$page] = $this->pageFixture();
    $locale = Locale::create(['code' => 'de', 'name' => 'Deutsch', 'is_enabled' => true]);
    $page->site->locales()->attach($locale->id, ['is_enabled' => true]);
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read pages:write-draft-translation pages:read-draft-translation');
    $reference = ['page_id' => $page->id, 'locale' => 'de'];
    $version = $this->tool('get_draft_translation', $reference, $token)->json('result.structuredContent.version');
    $first = $this->tool('update_draft_translation', $reference + [
      'version' => $version, 'name' => 'Neue Seite', 'seo_description' => 'Entwurf',
    ], $token)->assertJsonPath('result.structuredContent.translation.path', '/neue-seite')->json('result.structuredContent');
    $this->tool('update_draft_translation', $reference + [
      'version' => $first['version'], 'seo_description' => null,
    ], $token)->assertJsonPath('result.structuredContent.changes.seo_description', null);
    $this->assertNull($page->translationForLocale($locale));
    $promoted = app(InternalContentPlanService::class)->apply(['plan' => [
      'mode' => 'promote_staged_page_update', 'staged_page_id' => $first['draft_id'], 'expected_source_path' => '/',
    ]]);
    $this->assertTrue($promoted->ok, json_encode($promoted->errors));
    $translation = $page->fresh()->translationForLocale($locale);
    $this->assertSame('Neue Seite', $translation->name);
    $this->assertSame('/neue-seite', $translation->path);
    $this->assertNull($translation->seo_description);
  }

  public function test_mcp_can_edit_translation_fields_on_a_standalone_draft(): void
  {
    [$page] = $this->pageFixture();
    $page->update(['status' => Page::STATUS_DRAFT]);
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read pages:write-draft-translation pages:read-draft-translation');
    $reference = ['page_id' => $page->id, 'locale' => 'tr'];
    $version = $this->tool('get_draft_translation', $reference, $token)->json('result.structuredContent.version');
    $this->tool('update_draft_translation', $reference + [
      'version' => $version, 'name' => 'Taslak başlığı', 'path' => '/taslak', 'og_title' => 'Paylaşım başlığı',
    ], $token)->assertJsonPath('result.structuredContent.draft_id', $page->id)
      ->assertJsonPath('result.structuredContent.translation.og_title', 'Paylaşım başlığı');
    $this->assertSame('/taslak', $page->fresh()->translationForLocale('tr')->path);
    $this->assertSame(Page::STATUS_DRAFT, $page->fresh()->status);
    $this->assertSame(1, Page::count());
  }

  public function test_oauth_binds_codes_to_pkce_callback_resource_and_one_site(): void
  {
    [$page] = $this->pageFixture();
    $other = Site::create(['handle' => 'other', 'name' => 'Other']);
    $otherPage = Page::create(['site_id' => $other->id, 'title' => 'Other', 'slug' => 'other', 'status' => 'published']);
    $user = $this->operator();
    $auth = $this->authorizationRequest();
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertRedirect();
    $this->actingAs($user, 'web');
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee('Choose a site')
      ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self' https://client.example.test; base-uri 'none'");
    $this->post(self::MCP.'/authorize', array_replace($auth, ['redirect_uri' => self::REDIRECT.'/unexpected', 'site_id' => $page->site_id, 'decision' => 'allow']))
      ->assertStatus(400);
    $code = $this->consent($page->site_id, $auth);
    $this->exchange($code, ['code_verifier' => str_repeat('z', 43)])->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, ['resource' => self::MCP.'/other'])->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, ['client_secret' => 'unexpected'])->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $token = $this->exchange($code)->assertOk()->json('access_token');
    $this->assertDatabaseMissing('wbcms_mcp_grants', ['token_hash' => $token]);
    $this->assertDatabaseHas('wbcms_mcp_grants', ['token_hash' => hash('sha256', $token), 'code_hash' => null]);
    $this->exchange($code)->assertJsonPath('error', 'invalid_grant');
    $this->tool('find_page', ['page_id' => $otherPage->id, 'locale' => 'en'], $token)
      ->assertJsonPath('result.structuredContent.code', 'page_missing');
    $this->tool('update_draft_heading', [], $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $this->travel(61)->minutes();
    $this->tool('find_page', ['page_id' => $page->id, 'locale' => 'en'], $token, 401)
      ->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  public function test_panel_controls_public_registration_without_a_configured_client(): void
  {
    [$page] = $this->pageFixture();
    DB::table('wbcms_system_settings')->where('key', SystemSettings::MCP_ENABLED)->delete();
    $settingsUrl = self::ORIGIN.'/webadmin/system/settings';
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT]])->assertNotFound();
    $editor = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]), [$page->site_id]);
    $this->actingAs($editor, 'web');
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '1'])->assertForbidden();
    $this->get(self::ORIGIN.'/webadmin/profile/mcp-connections')->assertOk();
    $this->actingAs($this->operator(), 'web');
    $this->get($settingsUrl.'?tab=mcp')->assertOk()->assertSee(self::MCP);
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '1'])->assertRedirect(route('admin.system.settings.edit', ['tab' => 'mcp']));
    $this->get(self::ORIGIN.'/.well-known/oauth-authorization-server/webadmin/mcp')
      ->assertOk()->assertJsonPath('token_endpoint_auth_methods_supported', ['none', 'client_secret_post']);
    $this->registerClient('Client A', [self::REDIRECT]);
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '0'])->assertRedirect();
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT]])->assertNotFound();
    $this->postJson(self::MCP, [])->assertNotFound();
  }

  public function test_read_scopes_allow_inspection_without_enabling_draft_edits(): void
  {
    [$page] = $this->pageFixture();
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id,
      scope: 'pages:read pages:read-content pages:read-draft-structure pages:read-draft-translation media:read');
    $input = ['page_id' => $page->id, 'locale' => 'en'];
    $this->tool('get_page_content', $input, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.write.text_fields', [])
      ->assertJsonPath('result.structuredContent.blocks.0.write.blocked_by', 'forbidden');
    $this->tool('get_draft_structure', $input, $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
    $version = $this->tool('get_draft_translation', $input, $token)
      ->assertJsonPath('result.structuredContent.page_id', $page->id)->json('result.structuredContent.version');
    $this->tool('find_media', [], $token)->assertJsonPath('result.structuredContent.images', []);
    foreach (['update_draft_block', 'edit_draft_structure', 'update_draft_translation', 'upload_media'] as $tool) {
      $this->tool($tool, $input, $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    }
    $writeOnly = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-draft-translation');
    $this->tool('get_draft_translation', $input, $writeOnly, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $this->tool('update_draft_translation', $input + ['version' => $version, 'seo_title' => 'Write only'], $writeOnly)
      ->assertJsonMissingPath('result.structuredContent.translation')
      ->assertJsonMissingPath('result.structuredContent.changes')
      ->assertJsonPath('result.structuredContent.page_id', $page->id);
  }

  public function test_scope_settings_suspend_active_and_pending_grants_and_keep_profile_permissions_separate_by_site(): void
  {
    [$page] = $this->pageFixture();
    $otherSite = Site::create(['handle' => 'branch-office', 'name' => 'Branch office']);
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id, scope: 'pages:read pages:read-draft-translation pages:write-draft-translation');
    $this->connect($user, $otherSite->id, scope: 'pages:read media:read');
    $auth = array_replace($this->authorizationRequest(), ['scope' => 'pages:read pages:write-draft-translation']);
    $pending = $this->consent($page->site_id, $auth);
    $settings = self::ORIGIN.'/webadmin/system/settings/mcp';
    $profile = self::ORIGIN.'/webadmin/profile/mcp-connections';
    $allowed = ['mcp_enabled' => '1', 'manage_mcp_scopes' => '1', 'mcp_scopes' => ['pages:read-draft-translation', 'media:read']];
    $this->put($settings, $allowed)->assertRedirect();

    $this->get($profile)->assertOk()->assertSee('Branch office')
      ->assertViewHas('connections', function ($connections) use ($page, $otherSite): bool {
        $active = $connections->getCollection()->reject(fn ($row) => $row->pending)->keyBy('site_id');

        return $active[$page->site_id]->permissions === ['scope_read' => true, 'scope_write_translation' => false, 'scope_read_translation' => true]
          && $active[$otherSite->id]->permissions === ['scope_read' => true, 'scope_read_media' => true];
      });
    $this->post(self::MCP.'/authorize', $auth + ['site_id' => $page->site_id, 'decision' => 'allow'])->assertForbidden();
    $this->exchange($pending)->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->assertNotContains('update_draft_translation', array_column($this->rpc('tools/list')->json('result.tools'), 'name'));
    $input = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_translation', $input, $token)->json('result.structuredContent.version');
    $edit = $input + ['version' => $version, 'seo_title' => 'Revised title'];
    $this->tool('update_draft_translation', $edit, $token, 403)->assertJsonPath('result.structuredContent.code', 'scope_disabled');
    $allowed['mcp_scopes'][] = 'pages:write-draft-translation';
    $this->put($settings, $allowed)->assertRedirect();
    $this->tool('update_draft_translation', $edit, $token)
      ->assertJsonPath('result.structuredContent.translation.seo_title', 'Revised title');
  }

  public function test_existing_combined_grants_keep_only_their_previous_read_access_after_upgrade(): void
  {
    [$page] = $this->pageFixture();
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read pages:write-content-draft');
    Schema::table('wbcms_mcp_grants', fn ($table) => $table->dropColumn([
      'can_read_content', 'can_read_structure', 'can_read_translations', 'can_read_media',
    ]));
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_05_200000_split_mcp_read_grants.php';
    $migration->up();

    $input = ['page_id' => $page->id, 'locale' => 'en'];
    $this->tool('get_page_content', $input, $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
    $this->tool('find_media', [], $token)->assertJsonPath('result.structuredContent.images', []);
    $this->tool('get_draft_translation', $input, $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  public function test_registered_clients_require_consent_and_use_exact_callbacks_and_pkce(): void
  {
    [$page] = $this->pageFixture();
    $alternate = 'http://127.0.0.1:49200/callback?profile=example';
    $first = $this->registerClient('Client A', [self::REDIRECT, $alternate]);
    $second = $this->registerClient('Client B', [self::REDIRECT]);
    $this->assertNotSame($first, $second);
    $this->assertDatabaseEmpty('wbcms_mcp_grants');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => ['http://external.test/']])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => ['https://client.example.test/callback?%63ode=forged']])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT], 'token_endpoint_auth_method' => 'client_secret_basic'])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_client_metadata');

    $this->actingAs($this->operator(), 'web');
    $auth = array_replace($this->authorizationRequest($first), ['redirect_uri' => $alternate]);
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee('Client A')->assertSee('127.0.0.1')
      ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self' http://127.0.0.1:49200; base-uri 'none'");
    $this->post(self::MCP.'/authorize', array_replace($auth, ['redirect_uri' => $alternate.'1', 'decision' => 'deny']))->assertBadRequest();
    $this->post(self::MCP.'/authorize', $auth + ['decision' => 'deny'])->assertStatus(303)
      ->assertRedirect($alternate.'&error=access_denied&iss='.rawurlencode(self::MCP).'&state=state-from-client');
    $this->assertDatabaseEmpty('wbcms_mcp_grants');

    $code = $this->consent($page->site_id, $auth);
    $credentials = ['client_id' => $first, 'redirect_uri' => $alternate];
    $this->exchange($code, array_replace($credentials, ['client_id' => $second, 'redirect_uri' => self::REDIRECT]))
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, array_replace($credentials, ['redirect_uri' => self::REDIRECT]))
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, $credentials + ['code_verifier' => str_repeat('x', 43)])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $token = $this->exchange($code, $credentials)->assertOk()->json('access_token');
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
  }

  public function test_dynamic_registration_accepts_a_confidential_pkce_client_requesting_refresh(): void
  {
    [$page] = $this->pageFixture();
    $registration = $this->postJson(self::MCP.'/register', [
      'client_name' => 'ChatGPT',
      'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
      'grant_types' => ['authorization_code', 'refresh_token'],
      'response_types' => ['code'],
      'token_endpoint_auth_method' => 'client_secret_post',
    ])->assertCreated()
      ->assertJsonPath('grant_types', ['authorization_code'])
      ->assertJsonPath('token_endpoint_auth_method', 'client_secret_post')
      ->assertJsonPath('client_secret_expires_at', 0)
      ->assertHeader('Cache-Control', 'no-store, private');
    $id = $registration->json('client_id');
    $secret = $registration->json('client_secret');
    $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $secret);
    $this->assertDatabaseHas('wbcms_mcp_oauth_clients', ['id' => $id, 'client_secret_hash' => hash('sha256', $secret)]);
    $this->assertDatabaseEmpty('wbcms_mcp_grants');

    $this->actingAs($this->operator(), 'web');
    $redirect = $registration->json('redirect_uris.0');
    $auth = array_replace($this->authorizationRequest($id), ['redirect_uri' => $redirect]);
    $code = $this->consent($page->site_id, $auth);
    $credentials = ['client_id' => $id, 'redirect_uri' => $redirect];
    $this->exchange($code, $credentials)->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $this->exchange($code, $credentials + ['client_secret' => 'incorrect'])->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $this->exchange($code, $credentials + ['client_secret' => $secret, 'code_verifier' => str_repeat('z', 43)])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $token = $this->exchange($code, $credentials + ['client_secret' => $secret])->assertOk()->assertJsonMissingPath('refresh_token')->json('access_token');
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
  }

  public function test_profile_revokes_only_the_selected_connection_while_remote_mcp_is_disabled(): void
  {
    [$page] = $this->pageFixture();
    $otherSite = Site::create(['handle' => 'another', 'name' => 'Another site']);
    $client = $this->registerClient('Client A', [self::REDIRECT]);
    $otherClient = $this->registerClient('Client B', [self::REDIRECT]);
    $firstUser = $this->operator();
    $secondUser = $this->operator();
    $this->actingAs($firstUser, 'web');
    $auth = $this->authorizationRequest($client);
    $credentials = ['client_id' => $client];
    $first = $this->exchange($this->consent($page->site_id, $auth), $credentials)->assertOk()->json('access_token');
    $second = $this->exchange($this->consent($otherSite->id, $auth), $credentials)->assertOk()->json('access_token');
    $pending = $this->consent($page->site_id, $auth);
    $otherCode = $this->consent($page->site_id, array_replace($auth, ['client_id' => $otherClient]));
    $other = $this->exchange($otherCode, array_replace($credentials, ['client_id' => $otherClient]))->assertOk()->json('access_token');
    $profileUrl = self::ORIGIN.'/webadmin/profile/mcp-connections';
    $grants = DB::table('wbcms_mcp_grants');
    $selectedId = (clone $grants)->where('token_hash', hash('sha256', $first))->value('id');
    $pendingId = (clone $grants)->where('code_hash', hash('sha256', $pending))->value('id');
    $this->setMcp(false);
    $response = $this->get($profileUrl)->assertOk()->assertSee(__('webblocks-cms::mcp.disabled_notice'));
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $trigger = $xpath->query('//button[@data-wb-toggle="modal" and @data-wb-target="#revoke-mcp-'.$selectedId.'"]');
    $this->assertSame(1, $trigger->length);
    $dialog = '//*[@id="wb-overlay-root"]//*[@role="dialog" and @id="revoke-mcp-'.$selectedId.'"]';
    $this->assertStringContainsString('Client A', $xpath->evaluate('normalize-space('.$dialog.')'));
    $this->assertStringContainsString('Test site', $xpath->evaluate('normalize-space('.$dialog.')'));
    $action = $xpath->evaluate('string('.$dialog.'/descendant::form[@method="POST" and input[@name="_method" and @value="DELETE"] and input[@name="_token"]]/@action)');
    $this->assertSame($profileUrl.'/'.$selectedId, $action);

    $this->setMcp(true);
    $this->actingAs($secondUser, 'web');
    $this->get($profileUrl)->assertOk()->assertDontSee('Client A')->assertDontSee('Client B');
    $this->delete($action)->assertNotFound();
    $separate = $this->exchange($this->consent($page->site_id, $auth), $credentials)->assertOk()->json('access_token');
    $this->actingAs($firstUser, 'web');
    $this->setMcp(false);
    $this->post($action, ['_method' => 'DELETE'])->assertRedirect($profileUrl);
    $this->get($profileUrl)->assertOk()->assertDontSee('id="revoke-mcp-'.$selectedId.'"', false)->assertSee('Client A')->assertSee('Client B');
    $this->assertDatabaseMissing('wbcms_mcp_grants', ['id' => $selectedId]);
    $this->assertDatabaseHas('wbcms_mcp_grants', ['id' => $pendingId]);
    $this->post($profileUrl.'/'.$pendingId, ['_method' => 'DELETE'])->assertRedirect($profileUrl);
    $this->setMcp(true);

    $arguments = ['path' => '/', 'locale' => 'en'];
    $this->tool('find_page', $arguments, $first, 401);
    $this->tool('search_pages', ['q' => 'Home', 'locale' => 'en'], $second)
      ->assertJsonPath('result.structuredContent.site_id', $otherSite->id);
    $this->exchange($pending, $credentials)->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->tool('find_page', $arguments, $other);
    $this->tool('find_page', $arguments, $separate);
  }

  public function test_a_localized_heading_changes_in_the_same_draft_and_renders_in_private_preview(): void
  {
    [$page, $heading] = $this->pageFixture();
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id);
    $reference = ['page_id' => $page->id, 'locale' => 'tr'];
    $read = $this->tool('find_page', ['path' => '/', 'locale' => 'tr'], $token)
      ->assertJsonPath('result.structuredContent.headings.0.title', 'Türkçe başlık')->json('result.structuredContent');
    $input = $reference + ['block_id' => $heading->id, 'version' => $read['version'], 'title' => 'Taslak başlığı'];
    $this->tool('update_draft_heading', array_replace($input, ['locale' => 'en']), $token)
      ->assertJsonPath('result.structuredContent.code', 'conflict');
    $written = $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.title', 'Taslak başlığı')
      ->json('result.structuredContent');

    $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.code', 'conflict');
    $read = $this->tool('find_page', $reference, $token)
      ->assertJsonPath('result.structuredContent.headings.0.draft_title', 'Taslak başlığı')->json('result.structuredContent');
    $this->tool('update_draft_heading', array_replace($input, ['version' => $read['version'], 'title' => 'İkinci taslak başlığı']), $token)
      ->assertJsonPath('result.structuredContent.draft_id', $written['draft_id']);
    $this->tool('get_draft_preview', $reference, $token)->assertJsonPath('result.structuredContent.preview_url', $written['preview_url']);

    $draft = Page::findOrFail($written['draft_id']);
    $this->assertSame(Page::STATUS_DRAFT, $draft->status);
    $this->assertSame($user->id, $draft->updated_by_user_id);
    $this->assertSame(1, app(InternalContentPlanService::class)->stagedDrafts($page)->count());
    $this->assertGreaterThanOrEqual(3, $draft->revisions()->count());
    $this->assertSame(['English heading', 'Türkçe başlık'], $heading->textTranslations()->orderBy('locale_id')->pluck('title')->all());
    $this->assertSame(['English heading', 'İkinci taslak başlığı'], $draft->blocks()->firstOrFail()->textTranslations()->orderBy('locale_id')->pluck('title')->all());
    $this->assertSame('Türkçe metin', $draft->blocks()->firstOrFail()->textTranslations()->whereNotNull('content')->value('content'));

    $this->get($written['preview_url'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
      ->assertSee('<h1 class="wb-promo-title">İkinci taslak başlığı</h1>', false);
    app('auth')->forgetGuards();
    $this->get($written['preview_url'])->assertRedirect();
  }

  public function test_a_draft_conflict_and_a_changed_published_source_do_not_rebase_or_overwrite(): void
  {
    [$page, $heading] = $this->pageFixture();
    $user = $this->operator();
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $user);
    $token = $this->connect($user, $page->site_id);
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $read = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft.source_verified', true)->json('result.structuredContent');
    $draft->blocks()->firstOrFail()->textTranslations()->orderBy('id')->firstOrFail()->update(['title' => 'Human draft edit']);
    $input = $ref + ['block_id' => $heading->id, 'version' => $read['version'], 'title' => 'MCP edit'];
    $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.code', 'conflict');

    $heading->textTranslations()->orderBy('id')->firstOrFail()->update(['title' => 'Human live edit']);
    $result = app(InternalContentPlanService::class)->apply(['plan' => [
      'mode' => InternalContentPlanService::MODE_CREATE_STAGED_UPDATE,
      'page_id' => $page->id, 'expected_source_path' => '/', 'managed_slots' => ['main'],
    ]]);
    $this->assertTrue($result->ok);
    $this->assertTrue($result->data['reused_staged_update']);
    $read = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft.source_verified', false)->json('result.structuredContent');
    $this->tool('update_draft_heading', array_replace($input, ['version' => $read['version']]), $token)
      ->assertJsonPath('result.structuredContent.code', 'unverified_base');
    $this->assertSame('Human draft edit', $draft->blocks()->firstOrFail()->textTranslations()->orderBy('id')->value('title'));
    $this->assertSame('Human live edit', $heading->textTranslations()->orderBy('id')->value('title'));
  }

  #[DataProvider('headingTypes')]
  public function test_a_new_localized_heading_keeps_inherited_copy_and_revoked_access_stops_the_token(string $type): void
  {
    [$page, $heading] = $this->pageFixture($type, '/editorial');
    $heading->textTranslations()->where('locale_id', Locale::where('code', 'tr')->value('id'))->delete();
    $heading->textTranslations()->firstOrFail()->update(['subtitle' => 'Shared fallback copy']);
    $user = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]), [$page->site_id]);
    $token = $this->connect($user, $page->site_id);
    $ref = ['page_id' => $page->id, 'locale' => 'tr'];
    $read = $this->tool('find_page', ['path' => '/editorial', 'locale' => 'tr'], $token)
      ->assertJsonPath('result.structuredContent.headings.0.title', 'English heading')->json('result.structuredContent');
    $write = $this->tool('update_draft_heading', $ref + [
      'version' => $read['version'], 'block_id' => $heading->id, 'title' => 'Yerelleştirilmiş başlık',
    ], $token)->json('result.structuredContent');

    $this->get($write['preview_url'])->assertOk()->assertSee('Yerelleştirilmiş başlık');
    $draftBlock = Block::findOrFail($write['block_id']);
    $this->assertSame(['Shared fallback copy', 'Shared fallback copy'], $draftBlock->textTranslations()->orderBy('locale_id')->pluck('subtitle')->all());
    $this->assertSame(1, $heading->textTranslations()->count());
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
    $this->tool('find_page', $ref, $token, 401)->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  public function test_existing_heading_grants_do_not_authorize_expanded_tools(): void
  {
    [$page] = $this->pageFixture();
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id);
    foreach ([
      'get_page_content' => 'pages:read-content',
      'update_draft_block' => 'pages:write-content-draft',
      'find_media' => 'media:read',
    ] as $name => $scope) {
      $this->tool($name, [], $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required')
        ->assertJsonPath('result._meta.mcp/www_authenticate.0', fn ($header) => str_contains($header, 'scope="pages:read '.$scope.'"'));
    }

    $auth = array_replace($this->authorizationRequest(), ['scope' => 'pages:read pages:write-content-draft']);
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()
      ->assertSee(__('webblocks-cms::mcp.scope_write_content'))->assertDontSee(__('webblocks-cms::mcp.scope_read_content'));
    $this->exchange($this->consent($page->site_id, $auth))->assertOk()
      ->assertJsonPath('scope', 'pages:read pages:write-content-draft');
  }

  public function test_block_edits_preserve_inherited_copy_and_render_sanitized_content_in_the_draft(): void
  {
    [$page, $hero] = $this->pageFixture();
    $hero->textTranslations()->whereNotNull('content')->delete();
    $hero->textTranslations()->update(['subtitle' => 'Inherited eyebrow', 'content' => 'Inherited copy']);
    $rich = $page->blocks()->create([
      'type' => 'rich-text', 'slot_type_id' => $hero->slot_type_id, 'status' => 'published', 'sort_order' => 1,
    ]);
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read pages:write-content-draft pages:read-content');
    $reference = ['page_id' => $page->id, 'locale' => 'tr'];
    $version = $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.text.content', 'Inherited copy')
      ->json('result.structuredContent.version');

    foreach ([['url' => 'https://example.test/'], ['eyebrow' => 'A valid text field outside the MCP Hero allowlist'], ['content' => str_repeat('x', 8001)]] as $text) {
      $this->tool('update_draft_block', $reference + [
        'block_id' => $hero->id, 'version' => $version, 'text' => $text,
      ], $token)->assertJsonPath('result.structuredContent.code', 'invalid_request');
    }
    $this->assertSame(0, app(InternalContentPlanService::class)->stagedDrafts($page)->count());

    $updated = $this->tool('update_draft_block', $reference + [
      'block_id' => $hero->id, 'version' => $version, 'text' => ['content' => 'Yeni tanıtım'],
    ], $token)->assertJsonPath('result.structuredContent.updated_fields', ['content'])->json('result.structuredContent');

    $richEdit = $reference + ['block_id' => $rich->id, 'text' => ['content' => '<p>Yeni <strong>paragraf</strong></p><script>bad()</script>']];
    $this->tool('update_draft_block', $richEdit + ['version' => $version], $token)
      ->assertJsonPath('result.structuredContent.code', 'conflict');

    $version = $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.draft.managed_slots', ['main'])
      ->assertJsonPath('result.structuredContent.blocks.0.draft_text.title', 'English heading')
      ->assertJsonPath('result.structuredContent.blocks.0.draft_text.subtitle', 'Inherited eyebrow')
      ->assertJsonPath('result.structuredContent.blocks.0.draft_text.content', 'Yeni tanıtım')
      ->json('result.structuredContent.version');

    $this->tool('update_draft_block', $richEdit + ['version' => $version], $token)
      ->assertJsonPath('result.structuredContent.draft_id', $updated['draft_id']);
    $this->get($updated['preview_url'])->assertOk()->assertSee('Yeni tanıtım')
      ->assertSee('<p>Yeni <strong>paragraf</strong></p>', false)->assertDontSee('bad()');

    $this->assertSame('Inherited copy', $hero->textTranslations()->sole()->content);
    $this->assertSame(0, $rich->textTranslations()->count());
  }

  public function test_content_discovery_reports_fields_and_draft_slot_restrictions(): void
  {
    [$page, $hero] = $this->pageFixture();
    $aside = SlotType::create(['slug' => 'aside', 'name' => 'Aside', 'status' => 'published', 'sort_order' => 1]);
    $page->slots()->create(['slot_type_id' => $aside->id, 'source_type' => 'page', 'sort_order' => 1]);
    $image = $page->blocks()->create(['type' => 'image', 'slot_type_id' => $aside->id, 'status' => 'published', 'sort_order' => 0]);
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read pages:write-content-draft pages:read-content');
    $reference = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.write.text_fields', ['title', 'subtitle', 'content'])
      ->assertJsonPath('result.structuredContent.blocks.1.block_id', $image->id)
      ->assertJsonPath('result.structuredContent.blocks.1.write.text_fields', ['caption', 'alt_text'])
      ->assertJsonPath('result.structuredContent.blocks.1.write.media_id', true)
      ->json('result.structuredContent.version');
    $this->tool('get_page_content', ['page_id' => $page->id, 'locale' => 'tr'], $token)
      ->assertJsonPath('result.structuredContent.blocks.1.write.text_fields', ['caption', 'alt_text'])
      ->assertJsonPath('result.structuredContent.blocks.1.write.media_id', false)
      ->assertJsonPath('result.structuredContent.blocks.1.write.media_blocked_by', 'media_default_locale_required');

    $draftId = $this->tool('update_draft_block', $reference + [
      'block_id' => $hero->id, 'version' => $version, 'text' => ['title' => 'New title'],
    ], $token)->json('result.structuredContent.draft_id');
    $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.write.blocked_by', null)
      ->assertJsonPath('result.structuredContent.blocks.1.write.text_fields', [])
      ->assertJsonPath('result.structuredContent.blocks.1.write.media_id', false)
      ->assertJsonPath('result.structuredContent.blocks.1.write.blocked_by', 'unmanaged_slot');

    Page::findOrFail($draftId)->update(['status' => Page::STATUS_IN_REVIEW]);
    $this->tool('get_page_content', $reference, $token)
      ->assertJsonPath('result.structuredContent.blocks.0.write.text_fields', [])
      ->assertJsonPath('result.structuredContent.blocks.0.write.media_id', false)
      ->assertJsonPath('result.structuredContent.blocks.0.write.blocked_by', 'conflict');
  }

  public function test_image_selection_respects_the_site_and_keeps_alt_text_separate_from_caption(): void
  {
    [$page, $hero] = $this->pageFixture();
    $english = Locale::where('code', 'en')->firstOrFail();
    $user = $this->operator();
    $otherSite = Site::create(['handle' => 'other', 'name' => 'Other site']);
    $image = $page->blocks()->create([
      'type' => 'image', 'slot_type_id' => $hero->slot_type_id, 'status' => 'published', 'sort_order' => 1,
    ]);
    $image->imageTranslations()->create(['locale_id' => $english->id, 'caption' => 'Existing caption', 'alt_text' => 'Existing alt']);

    $media = ['disk' => 'public', 'kind' => Media::KIND_IMAGE, 'visibility' => 'public', 'mime_type' => 'image/png', 'uploaded_by' => $user->id];
    $available = Media::create($media + ['path' => 'example.png', 'filename' => 'example.png', 'original_name' => 'example.png', 'title' => 'MCP 50%_offer']);
    $foreign = Media::create($media + ['path' => 'other.png', 'filename' => 'other.png', 'original_name' => 'other.png', 'title' => 'MCP foreign 50XXoffer']);
    $alternate = Media::create($media + ['path' => 'alternate.png', 'filename' => 'alternate.png', 'title' => 'Special 50XXoffer']);
    $otherSite->update(['favicon_media_id' => $foreign->id]);

    $token = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-content-draft pages:read-content media:read');
    $this->tool('find_media', ['q' => 'MCP'], $token)->assertJsonCount(1, 'result.structuredContent.images')
      ->assertJsonPath('result.structuredContent.images.0.media_id', $available->id);
    $this->tool('find_media', ['q' => '50%_offer'], $token)->assertJsonCount(1, 'result.structuredContent.images')
      ->assertJsonPath('result.structuredContent.images.0.media_id', $available->id);
    $this->tool('find_media', ['q' => '50%_offer', 'search_mode' => 'pattern'], $token)->assertJsonCount(2, 'result.structuredContent.images')
      ->assertJsonPath('result.structuredContent.images.1.media_id', $alternate->id);

    $reference = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_page_content', $reference, $token)->json('result.structuredContent.version');
    $input = $reference + ['block_id' => $image->id, 'version' => $version, 'text' => ['alt_text' => 'A river at sunset']];
    $this->tool('update_draft_block', $input + ['media_id' => $foreign->id], $token)
      ->assertJsonPath('result.structuredContent.code', 'media_unavailable');
    $this->assertSame(0, app(InternalContentPlanService::class)->stagedDrafts($page)->count());

    $result = $this->tool('update_draft_block', $input + ['media_id' => $available->id], $token)
      ->assertJsonPath('result.structuredContent.media_id', $available->id)->json('result.structuredContent');
    $draftBlock = Block::findOrFail($result['block_id']);
    $this->assertSame($available->id, $draftBlock->media_id);
    $this->assertSame('Existing caption', $draftBlock->imageTranslations()->sole()->caption);
    $this->assertSame('A river at sunset', $draftBlock->imageTranslations()->sole()->alt_text);
    $this->assertNull($image->fresh()->media_id);
    $this->assertSame('Existing alt', $image->imageTranslations()->sole()->alt_text);
  }

  public function test_upload_media_requires_consent_and_saves_the_original_file_and_independent_metadata(): void
  {
    Storage::fake('public');
    [$page] = $this->pageFixture();
    $user = $this->operator();
    $bytes = UploadedFile::fake()->image('river.png', 50, 40)->get();
    $input = ['filename' => 'river.png', 'content_base64' => base64_encode($bytes), 'alt_text' => 'A river at sunset', 'caption' => 'Photo from the park'];

    $old = $this->connect($user, $page->site_id, scope: 'pages:read pages:write-content-draft');
    $this->tool('upload_media', $input, $old, 403)
      ->assertJsonPath('result.structuredContent.code', 'auth_required');

    $auth = array_replace($this->authorizationRequest(), ['scope' => 'pages:read pages:write-content-draft media:upload media:read']);
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee(__('webblocks-cms::mcp.consent_upload'));
    $token = $this->exchange($this->consent($page->site_id, $auth))->assertOk()
      ->assertJsonPath('scope', $auth['scope'])->json('access_token');

    $id = $this->tool('upload_media', $input, $token)
      ->assertJsonPath('result.structuredContent.mime_type', 'image/png')
      ->assertJsonPath('result.structuredContent.width', 50)
      ->assertJsonPath('result.structuredContent.alt_text', $input['alt_text'])
      ->assertJsonPath('result.structuredContent.caption', $input['caption'])
      ->json('result.structuredContent.media_id');
    $media = Media::findOrFail($id);
    $this->assertSame($user->id, $media->uploaded_by);
    $this->assertSame('public', $media->visibility);
    $this->assertSame($bytes, Storage::disk('public')->get($media->path));
    $this->tool('find_media', ['q' => 'river'], $token)
      ->assertJsonPath('result.structuredContent.images.0.media_id', $id);
    foreach (['title' => 255, 'alt_text' => 255, 'caption' => 2000, 'description' => 2000] as $field => $limit) {
      $this->tool('upload_media', [$field => str_repeat('ü', $limit + 1)] + $input, $token)
        ->assertJsonPath('result.structuredContent.code', 'invalid_request');
    }
    $this->assertSame(1, Media::count());
    $this->tool('upload_media', [
      'filename' => 'document.txt', 'content_base64' => base64_encode(str_repeat('a', 512 * 1024)),
    ], $token)->assertJsonPath('result.structuredContent.size', 512 * 1024);
  }

  public function test_upload_media_rechecks_permission_for_the_consented_site(): void
  {
    Storage::fake('public');
    [$page] = $this->pageFixture();
    $otherSite = Site::create(['handle' => 'other', 'name' => 'Other site']);
    $user = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]));
    $reader = CmsRole::create(['name' => 'MCP reader', 'handle' => 'mcp-reader']);
    $uploader = CmsRole::create(['name' => 'MCP uploader', 'handle' => 'mcp-uploader']);
    foreach ([$reader, $uploader] as $role) {
      foreach (['cms.panel.access', 'cms.pages.read'] as $permission) {
        DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
      }
    }
    DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $uploader->id, 'permission' => 'cms.media.upload']);
    $user->sites()->attach([$page->site_id => ['role_id' => $uploader->id], $otherSite->id => ['role_id' => $reader->id]]);
    $token = $this->connect($user, $page->site_id, scope: 'pages:read media:upload');
    $auth = array_replace($this->authorizationRequest(), ['scope' => 'pages:read media:upload']);
    $this->post(self::MCP.'/authorize', $auth + ['site_id' => $otherSite->id, 'decision' => 'allow'])->assertForbidden();

    $user->sites()->updateExistingPivot($page->site_id, ['role_id' => $reader->id]);
    $user->sites()->updateExistingPivot($otherSite->id, ['role_id' => $uploader->id]);
    $this->tool('upload_media', ['filename' => 'note.txt', 'content_base64' => base64_encode('A note.')], $token)
      ->assertJsonPath('result.structuredContent.code', 'forbidden');
    $this->assertDatabaseEmpty('wbcms_media');
    $this->assertSame([], Storage::disk('public')->allFiles());
  }

  public function test_upload_media_checks_file_type_size_and_cleans_up_failed_storage(): void
  {
    Storage::fake('public');
    config(['webblocks-cms.media.allow_svg_uploads' => true]);
    [$page] = $this->pageFixture();
    $token = $this->connect($this->operator(), $page->site_id, scope: 'pages:read media:upload');
    $input = ['filename' => 'note.txt', 'content_base64' => base64_encode('A note.')];
    $invalid = [
      ['content_base64' => 'a==='],
      ['content_base64' => base64_encode(str_repeat('a', 512 * 1024 + 1))],
      ['filename' => 'note.php'],
      ['filename' => 'note.png'],
      ['filename' => 'note.svg', 'content_base64' => base64_encode('<svg xmlns="http://www.w3.org/2000/svg"></svg>')],
    ];
    foreach ($invalid as $changes) {
      $this->tool('upload_media', $changes + $input, $token)
        ->assertJsonPath('result.structuredContent.code', 'invalid_media_file');
    }
    $this->tool('upload_media', ['filename' => 'a\\b.txt'] + $input, $token)
      ->assertJsonPath('result.structuredContent.code', 'invalid_request');
    $this->rpc('ping', ['padding' => str_repeat('a', 17_000)])->assertBadRequest();
    $this->assertDatabaseEmpty('wbcms_media');
    $this->assertSame([], Storage::disk('public')->allFiles());

    DB::connection()->beforeExecuting(function (string $sql): void {
      if (str_starts_with($sql, 'insert into "wbcms_media"')) {
        throw new \RuntimeException('Simulated Media Library write failure.');
      }
    });
    $this->tool('upload_media', $input, $token)->assertJsonPath('result.structuredContent.code', 'media_upload_failed');
    $this->assertDatabaseEmpty('wbcms_media');
    $this->assertSame([], Storage::disk('public')->allFiles());
  }

  public static function headingTypes(): array
  {
    return ['header' => ['header'], 'content header' => ['content_header']];
  }

  public function test_new_consent_defaults_to_connection_created_drafts_and_hides_existing_work(): void
  {
    [$page, $heading] = $this->pageFixture();
    $colleague = $this->operator();
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $colleague);
    $draft->blocks()->sole()->textTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'en'))
      ->update(['title' => 'Private colleague copy']);
    $user = $this->operator();
    $this->actingAs($user, 'web');
    $auth = $this->authorizationRequest();
    $auth['scope'] = 'pages:read pages:write-draft pages:read-content pages:write-content-draft pages:read-draft-structure pages:write-draft-structure pages:read-draft-translation pages:write-draft-seo';
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()
      ->assertSee('Drafts created by this connection and the draft you select')->assertSee('All eligible drafts on the selected site');
    $token = $this->exchange($this->consent($page->site_id, $auth))->assertOk()->json('access_token');
    $this->assertDatabaseHas('wbcms_mcp_grants', ['token_hash' => hash('sha256', $token), 'drafts_created_only' => true]);
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft_restricted', true)
      ->assertJsonPath('result.structuredContent.draft', null)->assertDontSee('Private colleague copy')->json('result.structuredContent.version');
    $this->tool('get_page_content', $ref, $token)->assertJsonPath('result.structuredContent.blocks.0.write.blocked_by', 'draft_access_denied')
      ->assertJsonPath('result.structuredContent.draft', null)->assertDontSee('Private colleague copy');
    foreach (['get_draft_structure', 'get_draft_translation', 'get_draft_preview'] as $name) {
      $this->tool($name, $ref, $token)->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    }
    $this->tool('update_draft_heading', $ref + ['version' => $version, 'block_id' => $heading->id, 'title' => 'Overwritten'], $token)
      ->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->tool('edit_draft_structure', $ref + ['version' => $version, 'operation' => 'add', 'slot' => 'main', 'type' => 'header'], $token)
      ->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_title' => 'Overwritten'], $token)
      ->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->assertSame('Private colleague copy', $draft->blocks()->sole()->textTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'en'))->sole()->title);
    $this->assertDatabaseEmpty('wbcms_mcp_grant_drafts');
  }

  public function test_restricted_grants_bind_new_update_drafts_to_one_connection_and_keep_conflict_checks(): void
  {
    [$page, $heading] = $this->pageFixture();
    $user = $this->operator();
    $scope = 'pages:read pages:write-draft pages:read-draft-structure pages:write-draft-structure pages:read-draft-translation pages:write-draft-seo';
    $token = $this->connect($user, $page->site_id, $scope, draftAccess: 'created');
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('find_page', $ref, $token)->json('result.structuredContent.version');
    $result = $this->tool('update_draft_heading', $ref + ['block_id' => $heading->id, 'version' => $version, 'title' => 'Owned draft'], $token)
      ->json('result.structuredContent');
    $grantId = DB::table('wbcms_mcp_grants')->where('token_hash', hash('sha256', $token))->value('id');
    $this->assertDatabaseHas('wbcms_mcp_grant_drafts', ['grant_id' => $grantId, 'page_id' => $result['draft_id']]);
    $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.headings.0.draft_title', 'Owned draft');
    $this->tool('get_draft_preview', $ref, $token)->assertJsonPath('result.structuredContent.draft_id', $result['draft_id']);
    $this->tool('get_draft_structure', $ref, $token)->assertJsonPath('result.structuredContent.draft_id', $result['draft_id']);
    $translation = $this->tool('get_draft_translation', $ref, $token)->json('result.structuredContent');
    $second = $this->connect($user, $page->site_id, $scope, draftAccess: 'created');
    $this->tool('update_draft_seo', $ref + ['version' => $translation['version'], 'seo_title' => 'Cross connection'], $second)
      ->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $fresh = $this->tool('find_page', $ref, $token)->json('result.structuredContent.version');
    Page::findOrFail($result['draft_id'])->blocks()->sole()->textTranslations()->whereHas('locale', fn ($query) => $query->where('code', 'en'))
      ->update(['title' => 'Human update']);
    $this->tool('update_draft_heading', $ref + ['block_id' => $heading->id, 'version' => $fresh, 'title' => 'Stale write'], $token)
      ->assertJsonPath('result.structuredContent.code', 'conflict');
    $this->get('/webadmin/profile/mcp-connections')->assertOk()->assertSee('Drafts created by this connection and the draft you select');
    $grantId = DB::table('wbcms_mcp_grants')->where('token_hash', hash('sha256', $token))->value('id');
    $this->delete('/webadmin/profile/mcp-connections/'.$grantId)->assertRedirect();
    $this->assertDatabaseEmpty('wbcms_mcp_grant_drafts');
  }

  public function test_restricted_standalone_draft_retries_do_not_transfer_ownership_to_another_connection(): void
  {
    [$source, $hero] = $this->pageFixture();
    BlockType::create(['slug' => 'header', 'name' => 'Header', 'status' => 'published']);
    $layout = PageLayout::create(['handle' => 'default', 'name' => 'Default', 'is_active' => true]);
    $layout->layoutSlots()->create(['slot_type_id' => $hero->slot_type_id, 'slot_name' => 'main', 'label' => 'Main', 'is_active' => true]);
    $user = $this->operator();
    $scope = 'pages:read pages:create-draft pages:read-draft-structure pages:write-draft-structure pages:read-draft-translation pages:write-draft-seo';
    $token = $this->connect($user, $source->site_id, $scope, draftAccess: 'created');
    $input = ['locale' => 'en', 'title' => 'Private draft', 'path' => '/private-draft'];
    $draft = $this->tool('create_page_draft', $input, $token)->json('result.structuredContent');
    $this->tool('create_page_draft', $input, $token)->assertJsonPath('result.structuredContent', $draft);
    $ref = ['page_id' => $draft['page_id'], 'locale' => 'en'];
    $structure = $this->tool('get_draft_structure', $ref, $token)->json('result.structuredContent');
    $this->tool('edit_draft_structure', $ref + ['version' => $structure['version'], 'operation' => 'add', 'slot' => 'main', 'type' => 'header', 'text' => ['title' => 'Private heading']], $token)
      ->assertJsonPath('result.structuredContent.draft_id', $draft['draft_id']);
    $other = $this->connect($user, $source->site_id, $scope, draftAccess: 'created');
    $this->tool('create_page_draft', $input, $other)->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->tool('get_draft_structure', $ref, $other)->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->tool('get_draft_translation', $ref, $other)->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->assertSame(2, Page::count());
    $this->assertSame(1, DB::table('wbcms_mcp_grant_drafts')->count());
    Page::findOrFail($draft['page_id'])->delete();
    $this->assertDatabaseEmpty('wbcms_mcp_grant_drafts');
  }

  public function test_seo_only_grant_cannot_change_page_identity_or_invoke_the_broader_translation_tool(): void
  {
    [$page] = $this->pageFixture(path: '/original');
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id, 'pages:read pages:read-draft-translation pages:write-draft-seo', draftAccess: 'created');
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_translation', $ref, $token)->json('result.structuredContent.version');
    $this->tool('update_draft_translation', $ref + ['version' => $version, 'seo_title' => 'Broader tool'], $token, 403)
      ->assertJsonPath('result.structuredContent.code', 'auth_required');
    foreach (['name' => 'Renamed', 'slug' => 'new-slug', 'path' => '/other-path'] as $field => $value) {
      $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_title' => 'SEO title', $field => $value], $token)
        ->assertJsonPath('result.structuredContent.code', 'invalid_request');
    }
    $result = $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_title' => 'SEO title'], $token)
      ->assertJsonPath('result.structuredContent.translation.seo_title', 'SEO title')->json('result.structuredContent');
    $this->assertSame('/original', $page->translationForLocale('en')->fresh()->path);
    $this->assertDatabaseHas('wbcms_mcp_grant_drafts', ['page_id' => $result['draft_id']]);
    app(SystemSettings::class)->save(['system.mcp_write_seo' => '0']);
    $this->rpc('tools/list')->assertDontSee('update_draft_seo');
    $this->tool('update_draft_seo', $ref + ['version' => $result['version'], 'seo_title' => 'Disabled'], $token, 403)
      ->assertJsonPath('result.structuredContent.code', 'scope_disabled');
  }

  public function test_explicit_site_draft_consent_can_continue_an_existing_draft(): void
  {
    [$page, $heading] = $this->pageFixture();
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $this->operator());
    $token = $this->connect($this->operator(), $page->site_id, draftAccess: 'site');
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft.id', $draft->id)->json('result.structuredContent.version');
    $this->tool('update_draft_heading', $ref + ['version' => $version, 'block_id' => $heading->id, 'title' => 'Explicit site access'], $token)
      ->assertJsonPath('result.structuredContent.draft_id', $draft->id);
    $this->assertDatabaseHas('wbcms_mcp_grants', ['token_hash' => hash('sha256', $token), 'drafts_created_only' => false]);
  }

  public function test_consent_can_approve_one_existing_draft_for_a_separate_seo_task(): void
  {
    [$page, $heading] = $this->pageFixture();
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $this->operator());
    $unrelated = Page::create(['site_id' => $page->site_id, 'title' => 'Colleague notes', 'slug' => 'colleague-notes', 'status' => Page::STATUS_DRAFT]);
    $user = $this->operator();
    $this->actingAs($user, 'web');
    $auth = $this->authorizationRequest();
    $auth['scope'] = 'pages:read pages:read-draft-translation pages:write-draft-seo';
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee('Also allow one existing draft');
    $token = $this->exchange($this->consent($page->site_id, $auth + ['draft_page_id' => $draft->id]))->assertOk()->json('access_token');
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_translation', $ref, $token)->json('result.structuredContent.version');
    $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_description' => 'Reviewed per task'], $token)
      ->assertJsonPath('result.structuredContent.draft_id', $draft->id);
    $this->tool('get_draft_translation', ['page_id' => $unrelated->id, 'locale' => 'en'], $token)
      ->assertJsonPath('result.structuredContent.code', 'draft_access_denied');
    $this->assertSame(1, DB::table('wbcms_mcp_grant_drafts')->count());
    $this->get('/webadmin/profile/mcp-connections')->assertOk()->assertSee('Authorized drafts');
    $foreignSite = Site::create(['handle' => 'other', 'name' => 'Other']);
    $foreignDraft = Page::create(['site_id' => $foreignSite->id, 'title' => 'Foreign', 'slug' => 'foreign', 'status' => Page::STATUS_DRAFT]);
    $grantCount = DB::table('wbcms_mcp_grants')->count();
    foreach ([$foreignDraft->id, $page->id] as $invalid) {
      $this->post(self::MCP.'/authorize', $auth + ['draft_page_id' => $invalid, 'site_id' => $page->site_id, 'decision' => 'allow'])->assertForbidden();
    }
    $this->assertSame($grantCount, DB::table('wbcms_mcp_grants')->count());
  }

  public function test_seo_only_tools_recheck_current_role_and_translation_permissions(): void
  {
    [$page] = $this->pageFixture();
    $user = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]));
    $role = CmsRole::create(['name' => 'SEO editor', 'handle' => 'seo-editor']);
    foreach (['cms.panel.access', 'cms.pages.read', 'cms.pages.update-draft', 'cms.seo.update'] as $permission) {
      DB::table('wbcms_acl_role_permissions')->insert(['role_id' => $role->id, 'permission' => $permission]);
    }
    $user->sites()->attach([$page->site_id => ['role_id' => $role->id]]);
    $token = $this->connect($user, $page->site_id, 'pages:read pages:read-draft-translation pages:write-draft-seo', draftAccess: 'created');
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $version = $this->tool('get_draft_translation', $ref, $token)->json('result.structuredContent.version');
    $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_title' => 'Allowed SEO'], $token)
      ->assertJsonPath('result.structuredContent.translation.seo_title', 'Allowed SEO');
    $turkish = ['page_id' => $page->id, 'locale' => 'tr'];
    $translated = $this->tool('get_draft_translation', $turkish, $token)->json('result.structuredContent.version');
    $this->tool('update_draft_seo', $turkish + ['version' => $translated, 'seo_title' => 'Yetkisiz'], $token)
      ->assertJsonPath('result.structuredContent.code', 'forbidden');
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.seo.update')->delete();
    $this->tool('update_draft_seo', $ref + ['version' => $version, 'seo_title' => 'Revoked'], $token)
      ->assertJsonPath('result.structuredContent.code', 'forbidden');
  }

  public function test_notes_use_the_same_visibility_and_ownership_in_all_three_channels(): void
  {
    [$page] = $this->pageFixture();
    $otherSite = Site::create(['handle' => 'second-office', 'name' => 'Second office']);
    $author = $this->notesUser('Note Author', $page->site_id);
    $recipient = $this->notesUser('Note Recipient', $otherSite->id);
    $third = $this->notesUser('Third Operator', $page->site_id);
    $admin = $this->operator();
    $inactive = $this->notesUser('Disabled Recipient', $page->site_id, false);
    $noPanel = $this->cmsActor((new User)->forceFill(['name' => 'Host Account', 'role' => 'editor']), []);
    $panel = self::ORIGIN.'/webadmin/notes';
    $api = self::ORIGIN.'/webadmin/api/notes';
    $auth = fn (User $actor, int $site) => $this->noteBearer($actor, $site);
    $allScopes = 'pages:read notes:read notes:write notes:delete';
    $body = "<script>unsafe()</script>\n".str_repeat('🗒', 1800);

    $this->actingAs($author)->get($panel)->assertOk()
      ->assertSee('data-wb-target="#notes-add"', false)->assertSee('Note Recipient')
      ->assertDontSee('Disabled Recipient')->assertDontSee('Host Account');
    $this->post($panel, ['title' => 'Retain these words', 'body' => '', 'audience_target' => (string) $recipient->id])
      ->assertRedirect($panel)->assertSessionHasErrors('body', null, 'note');
    $this->get($panel)->assertOk()->assertSee('Retain these words')
      ->assertSee('data-wb-admin-autoload-overlay', false)->assertSee('aria-invalid="true"', false);
    $this->post($panel, ['title' => 'Private Northstar', 'body' => $body, 'audience_target' => (string) $recipient->id])
      ->assertRedirect($panel);
    $id = (int) DB::table('wbcms_notes')->value('id');
    $this->get($panel.'/'.$id)->assertOk()->assertSee($body)->assertDontSee('<script>unsafe()</script>', false);
    $this->patch($panel.'/'.$id, ['title' => 'Preserved edit input', 'body' => $body, 'audience_target' => (string) $inactive->id])
      ->assertRedirect($panel.'/'.$id.'/edit')->assertSessionHasErrors('recipient_id', null, 'note');
    $this->get($panel.'/'.$id.'/edit')->assertOk()->assertSee('Preserved edit input')->assertSee($body);

    $recipientAuth = $auth($recipient, $otherSite->id);
    $thirdAuth = $auth($third, $page->site_id);
    $adminAuth = $auth($admin, $page->site_id);
    $authorAuth = $auth($author, $page->site_id);
    $note = '/'.$id;
    $this->getJson($api.$note, $recipientAuth)->assertOk()->assertJsonPath('note.body', $body)->assertJsonPath('note.read', false);
    $this->getJson($api.$note, $thirdAuth)->assertNotFound();
    $this->getJson($api.'?search=Northstar', $thirdAuth)->assertOk()->assertJsonPath('pagination.total', 0);
    $this->getJson($api.'?audience=administration', $thirdAuth)->assertForbidden();
    $this->getJson($api.$note, $adminAuth)->assertNotFound();
    $this->getJson($api.$note.'/edit', $adminAuth)->assertOk()->assertJsonPath('note.body', $body)->assertJsonMissingPath('note.read');
    $this->getJson($api.'?audience=administration', $adminAuth)->assertJsonPath('notes', [['id' => $id, 'edit_only' => true]]);
    $this->deleteJson($api.$note, [], $adminAuth)->assertNotFound();
    $this->patchJson($api.$note, ['title' => 'Recipient cannot edit'], $recipientAuth)->assertNotFound();

    $adminMcp = $this->connect($admin, $page->site_id, $allScopes);
    $recipientMcp = $this->connect($recipient, $otherSite->id, 'pages:read notes:read');
    $thirdMcp = $this->connect($third, $page->site_id, $allScopes);
    $authorMcp = $this->connect($author, $page->site_id, $allScopes);
    $ref = ['note_id' => $id];
    $this->tool('get_note', $ref, $recipientMcp)->assertJsonPath('result.structuredContent.note.body', $body);
    $this->tool('get_note', $ref, $thirdMcp)->assertJsonPath('result.structuredContent.code', 'note_unavailable');
    $this->tool('get_note', $ref, $adminMcp)->assertJsonPath('result.structuredContent.code', 'note_unavailable');
    $this->tool('get_note_for_edit', $ref, $adminMcp)->assertJsonPath('result.structuredContent.note.body', $body);
    $this->tool('list_note_recipients', [], $recipientMcp, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $this->tool('list_note_recipients', [], $authorMcp)->assertJsonFragment(['id' => $recipient->id, 'name' => 'Note Recipient'])
      ->assertJsonMissing(['name' => 'Disabled Recipient'])->assertJsonMissing(['name' => 'Host Account']);
    $this->tool('update_note', $ref + ['body' => $body], $adminMcp)->assertJsonPath('result.structuredContent.note.id', $id);
    $this->tool('delete_note', $ref, $adminMcp)->assertJsonPath('result.structuredContent.code', 'note_unavailable');
    $this->tool('update_note', $ref + ['recipient_id' => $inactive->id], $authorMcp)
      ->assertJsonPath('result.structuredContent.code', 'invalid_note_arguments');

    $this->actingAs($admin)->get($panel.'?audience=administration')->assertOk()
      ->assertSee('Private note #'.$id)->assertDontSee('Private Northstar')->assertDontSee('unsafe()')
      ->assertDontSee('data-wb-admin-row-select', false);
    $this->get($panel.$note)->assertNotFound();
    $this->post($panel.$note)->assertNotFound();
    $this->get($panel.$note.'/edit?audience=administration')->assertOk()->assertSee($body);
    $this->actingAs($third)->get($panel.$note.'/edit')->assertNotFound();

    $this->patchJson($api.$note.'/read-state', ['read' => true], $recipientAuth)->assertOk();
    $this->getJson($api.$note, $authorAuth)->assertJsonPath('note.read', false);
    DB::table('users')->where('id', $recipient->id)->delete();
    $this->assertDatabaseHas('wbcms_notes', ['id' => $id, 'is_private' => true, 'recipient_id' => null]);
    $this->assertDatabaseMissing('wbcms_note_reads', ['note_id' => $id, 'user_id' => $recipient->id]);
    $this->patchJson($api.$note, ['title' => 'Still private', 'recipient_id' => null], $authorAuth)
      ->assertOk()->assertJsonPath('note.visibility', 'private');
    $this->getJson($api.$note, $thirdAuth)->assertNotFound();
    $this->tool('get_note', $ref, $recipientMcp, 401)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $this->tool('delete_note', $ref, $authorMcp)->assertJsonPath('result.structuredContent.deleted', true);
    $this->assertDatabaseMissing('wbcms_notes', ['id' => $id]);
  }

  public function test_notes_filter_paginate_and_keep_general_read_state_personal(): void
  {
    [$page] = $this->pageFixture();
    $site = $page->site_id;
    $author = $this->notesUser('Notes Editor', $site);
    $other = $this->notesUser('Another Editor', $site);
    $ownerAuth = $this->noteBearer($author, $site);
    $otherAuth = $this->noteBearer($other, $site, ['notes.read']);
    $api = self::ORIGIN.'/webadmin/api/notes';
    $panel = self::ORIGIN.'/webadmin/notes';

    $legacy = $this->connect($other, $site, 'pages:read');
    $this->tool('list_notes', [], $legacy, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $system = app(CmsApiTokenIssuer::class)
      ->issue('System credential', $this->operator(), ['notes.read', 'notes.write'])->plainToken;
    $this->getJson($api, ['Authorization' => 'Bearer '.$system])->assertForbidden();
    $this->getJson($api.'/recipients', ['Authorization' => 'Bearer '.$system])->assertForbidden();
    $this->getJson($api.'/recipients', $otherAuth)->assertForbidden();
    $this->postJson($api, ['title' => 'No write', 'body' => 'Example'], $otherAuth)->assertForbidden();

    foreach (['100% ready', '1000 ready', 'New general'] as $title) {
      $this->postJson($api, ['title' => $title, 'body' => 'Example', 'author_id' => $other->id], $ownerAuth)
        ->assertCreated()->assertJsonPath('note.author.id', $author->id)->assertJsonPath('note.visibility', 'general');
    }

    $this->getJson($api.'?per_page=2', $ownerAuth)->assertJsonPath('pagination.total', 3)
      ->assertJsonPath('notes.0.title', 'New general')->assertJsonPath('notes.1.title', '1000 ready');
    $match = $this->getJson($api.'?search=100%25', $ownerAuth)->assertJsonPath('pagination.total', 1)
      ->assertJsonPath('notes.0.title', '100% ready')->json('notes.0.id');
    $this->actingAs($other)->get($panel.'?per_page=1&page=2')->assertOk()->assertSee('1000 ready')->assertDontSee('New general');
    $this->get($panel.'/'.$match)->assertOk();
    $this->assertDatabaseCount('wbcms_note_reads', 0);
    $this->post($panel.'/'.$match.'?audience=general&status=unread')->assertStatus(303)
      ->assertRedirect($panel.'/'.$match.'?audience=general&status=unread');
    $readModal = $this->get($panel.'/'.$match.'?audience=general&status=unread')->assertOk()
      ->assertSee('data-admin-page-count>2</span>', false)
      ->assertSee('<td class="wb-cms-note-title">', false)
      ->assertSee('id="notes-read"', false);
    $document = new DOMDocument;
    @$document->loadHTML($readModal->getContent());
    $modal = new DOMXPath($document);
    $this->assertSame(2, $modal->query('//*[@id="notes-read"]//*[@readonly]')->length);
    $this->assertSame(0, $modal->query('//*[@id="notes-read"]//form')->length);
    $this->assertSame('100% ready', $modal->query('//*[@id="notes-read-title"]')->item(0)->getAttribute('value'));
    $this->getJson($api.'?status=read&audience=general', $otherAuth)->assertJsonPath('pagination.total', 1);
    $this->getJson($api.'?status=unread', $otherAuth)->assertJsonPath('pagination.total', 2);
    $this->getJson($api.'/'.$match, $ownerAuth)->assertJsonPath('note.read', false);

    $token = $this->connect($other, $site, 'pages:read notes:read');
    $this->tool('set_note_read_state', ['note_id' => $match, 'read' => true], $token)
      ->assertJsonPath('result.structuredContent.read', true);
    $this->assertDatabaseCount('wbcms_note_reads', 1);
    $this->tool('set_note_read_state', ['note_id' => $match, 'read' => false], $token)
      ->assertJsonPath('result.structuredContent.read', false);
    $this->assertDatabaseCount('wbcms_note_reads', 0);
    $this->getJson($api.'?status=unread', $ownerAuth)->assertJsonPath('pagination.total', 3);
    $write = $this->connect($other, $site, 'pages:read notes:write');
    $self = $this->tool('create_note', ['title' => 'Personal', 'body' => 'Only for me', 'recipient_id' => $other->id], $write)
      ->assertJsonPath('result.structuredContent.note.visibility', 'private')->json('result.structuredContent.note.id');
    $this->getJson($api.'/'.$self, $otherAuth)->assertOk();
    $this->getJson($api.'/'.$self, $ownerAuth)->assertNotFound();
    $this->tool('update_note', ['note_id' => $self, 'recipient_id' => null], $write)
      ->assertJsonPath('result.structuredContent.note.visibility', 'private');
    $this->getJson($api.'/'.$self, $ownerAuth)->assertNotFound();
    $this->tool('update_note', ['note_id' => $self, 'visibility' => 'general'], $write)
      ->assertJsonPath('result.structuredContent.note.visibility', 'general');
    $this->getJson($api.'/'.$self, $ownerAuth)->assertOk();
  }

  public function test_notes_bulk_read_state_is_personal_and_rejects_hidden_notes_atomically(): void
  {
    [$page] = $this->pageFixture();
    $author = $this->notesUser('Notes author', $page->site_id);
    $reader = $this->notesUser('Notes reader', $page->site_id);
    $panel = self::ORIGIN.'/webadmin/notes';
    $bulk = $panel.'/bulk-read-state?audience=general';
    $this->actingAs($author);
    foreach (['General one', 'General two', 'Private note'] as $title) {
      $this->post($panel, ['title' => $title, 'body' => 'Text', 'audience_target' => $title === 'Private note' ? 'author' : 'everyone'])
        ->assertRedirect($panel);
    }
    [$first, $second, $hidden] = DB::table('wbcms_notes')->orderBy('id')->pluck('id')->all();
    $this->post($panel.'/'.$first)->assertStatus(303);

    $this->actingAs($reader)->get($panel.'?audience=general')->assertOk()
      ->assertSee('data-wb-admin-select-all-visible', false)->assertSee('Mark selected as read')->assertDontSee('Private note</strong>', false);
    $this->patch($bulk, ['read' => '1', 'note_ids' => [$first, $hidden]])->assertNotFound();
    $this->assertDatabaseMissing('wbcms_note_reads', ['note_id' => $first, 'user_id' => $reader->id]);
    $this->patch($bulk, ['read' => '1', 'note_ids' => []])->assertRedirect($panel.'?audience=general')
      ->assertSessionHasErrors('note_ids', null, 'notesBulk');
    $this->patch($bulk, ['read' => '1', 'note_ids' => [$first, $second]])->assertRedirect($panel.'?audience=general');
    $this->get($panel.'?audience=general')->assertOk()->assertSee('Selected notes marked as read.');
    $this->assertDatabaseCount('wbcms_note_reads', 3);

    $this->patch($bulk, ['read' => '0', 'note_ids' => [$first, $second]])->assertRedirect($panel.'?audience=general');
    $this->assertDatabaseCount('wbcms_note_reads', 1);
    $this->assertDatabaseHas('wbcms_note_reads', ['note_id' => $first, 'user_id' => $author->id]);
  }

  public function test_notes_migrations_create_relational_tables_and_do_not_extend_existing_grants(): void
  {
    $this->assertTrue(Schema::hasTable('wbcms_notes'));
    $this->assertTrue(Schema::hasTable('wbcms_note_reads'));
    [$page] = $this->pageFixture();
    $token = $this->connect($this->operator(), $page->site_id, 'pages:read');
    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_11_11_120000_create_cms_notes.php';
    $migration->down();
    $this->assertFalse(Schema::hasTable('wbcms_notes'));
    $migration->up();
    $migration->up();
    $this->assertDatabaseHas('wbcms_mcp_grants', ['can_read_notes' => false, 'can_write_notes' => false, 'can_delete_notes' => false]);
    $this->tool('list_notes', [], $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  private function notesUser(string $name, int $site, bool $active = true): User
  {
    return $this->cmsActor((new User)->forceFill(['name' => $name, 'role' => 'editor', 'is_active' => $active]), [$site]);
  }

  private function noteBearer(User $actor, int $site, array $capabilities = ['notes.read', 'notes.write', 'notes.delete']): array
  {
    $token = app(CmsApiTokenIssuer::class)
      ->issue('Notes tests', $actor, $capabilities, 'personal', [$site])->plainToken;

    return ['Authorization' => 'Bearer '.$token];
  }

  private function operator(): User
  {
    return $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
  }

  private function pageFixture(string $type = 'hero', string $path = '/'): array
  {
    $site = Site::create(['handle' => 'test', 'name' => 'Test site', 'is_primary' => true]);
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site->locales()->sync([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Home', 'slug' => 'home', 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->where('locale_id', $english->id)->firstOrFail()->update(['path' => $path]);
    $page->translations()->create(['locale_id' => $turkish->id, 'name' => 'Ana sayfa', 'slug' => 'home', 'path' => $path]);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'source_type' => 'page', 'sort_order' => 0]);
    $heading = Block::create(['page_id' => $page->id, 'type' => $type, 'variant' => $type === 'header' ? 'h1' : null, 'slot_type_id' => $slot->id, 'status' => 'published', 'sort_order' => 0]);
    $heading->textTranslations()->create(['locale_id' => $english->id, 'title' => 'English heading']);
    $heading->textTranslations()->create(['locale_id' => $turkish->id, 'title' => 'Türkçe başlık', 'content' => 'Türkçe metin']);

    return [$page, $heading];
  }

  private function connect(User $user, int $siteId, string $scope = 'pages:read pages:write-draft', string $draftAccess = 'site'): string
  {
    $this->actingAs($user, 'web');
    $auth = array_replace($this->authorizationRequest(), ['scope' => $scope, 'draft_access' => $draftAccess]);

    return $this->exchange($this->consent($siteId, $auth))->assertOk()->json('access_token');
  }

  private function registerClient(string $name, array $redirectUris): string
  {
    $response = $this->postJson(self::MCP.'/register', [
      'client_name' => $name,
      'redirect_uris' => $redirectUris,
      'token_endpoint_auth_method' => 'none',
    ])->assertCreated()
      ->assertJsonPath('client_name', $name)
      ->assertJsonPath('redirect_uris', $redirectUris)
      ->assertJsonPath('token_endpoint_auth_method', 'none')
      ->assertJsonMissingPath('client_secret');

    return $response->json('client_id');
  }

  private function setMcp(bool $enabled): void
  {
    app(SystemSettings::class)->save([SystemSettings::MCP_ENABLED => $enabled ? '1' : '0']);
  }

  private function defaultClient(): string
  {
    return $this->defaultClient ??= $this->registerClient('Synthetic client', [self::REDIRECT]);
  }

  private function authorizationRequest(?string $client = null): array
  {
    return [
      'client_id' => $client ?? $this->defaultClient(),
      'response_type' => 'code',
      'redirect_uri' => self::REDIRECT,
      'resource' => self::MCP,
      'code_challenge_method' => 'S256',
      'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 43), true)), '+/', '-_'), '='),
      'state' => 'state-from-client',
      'scope' => 'pages:read',
    ];
  }

  private function consent(int $siteId, array $auth): string
  {
    $redirect = $this->post(self::MCP.'/authorize', $auth + ['site_id' => $siteId, 'decision' => 'allow'])
      ->assertStatus(303)->headers->get('Location');
    $separator = str_contains($auth['redirect_uri'], '?') ? '&' : '?';
    $this->assertStringStartsWith($auth['redirect_uri'].$separator, $redirect);
    parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
    $this->assertSame('state-from-client', $query['state']);
    $this->assertSame(self::MCP, $query['iss']);

    return $query['code'];
  }

  private function exchange(string $code, array $overrides = []): TestResponse
  {
    return $this->post(self::MCP.'/token', array_replace([
      'grant_type' => 'authorization_code',
      'code' => $code,
      'code_verifier' => str_repeat('v', 43),
      'client_id' => $overrides['client_id'] ?? $this->defaultClient(),
      'redirect_uri' => self::REDIRECT,
      'resource' => self::MCP,
    ], $overrides));
  }

  private function tool(string $name, array $arguments, ?string $token = null, int $status = 200): TestResponse
  {
    return $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token)->assertStatus($status);
  }

  private function rpc(string $method, array $params = [], ?string $token = null): TestResponse
  {
    return $this->postJson(self::MCP, [
      'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params,
    ], $token ? ['Authorization' => 'Bearer '.$token] : []);
  }
}

class RemoteMcpTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
    return 'user_id';
  }
}
