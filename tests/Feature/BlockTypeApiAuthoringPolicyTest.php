<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalInventoryController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\BlockTypes\BlockTypeApiAuthoringPolicy;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentApiOperations;
use WebBlocks\Cms\Tests\TestCase;

class BlockTypeApiAuthoringPolicyTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function policy_marks_html_readable_but_never_api_writable(): void
  {
    $policy = app(BlockTypeApiAuthoringPolicy::class);

    $this->assertFalse($policy->isApiWritable('html'));
    $this->assertTrue($policy->isApiReadable('html'));
    $this->assertTrue($policy->isApiWritable('section'));

    $html = $policy->contractFor('html');
    $this->assertTrue($html['api_readable']);
    $this->assertFalse($html['api_writable']);
    $this->assertSame('human_only', $html['authoring']);
    $this->assertSame('block_type_not_api_writable', $html['api_write_error_code']);
    $this->assertNotEmpty($html['api_write_restriction']);

    $section = $policy->contractFor('section');
    $this->assertTrue($section['api_writable']);
    $this->assertSame('api_and_human', $section['authoring']);
    $this->assertArrayNotHasKey('api_write_error_code', $section);
  }

  #[Test]
  public function policy_rejection_uses_the_shared_error_envelope_and_stable_code(): void
  {
    $response = app(BlockTypeApiAuthoringPolicy::class)->rejectionResponse('block.type');
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertFalse($payload['ok']);
    $this->assertSame('block_type_not_api_writable', $payload['code']);
    $this->assertNotEmpty($payload['message']);
    $this->assertSame([], $payload['warnings']);
    $this->assertSame('block.type', $payload['errors'][0]['path']);
  }

  #[Test]
  public function inventory_endpoint_returns_the_packaged_markdown_document(): void
  {
    $response = app(InternalInventoryController::class)->show();
    $payload = $response->getData(true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['ok']);
    $this->assertSame('markdown', $payload['inventory']['format']);
    $this->assertSame('docs/inventory.md', $payload['inventory']['document']);
    $this->assertStringContainsString('# WebBlocks CMS Inventory for AI Page Building', $payload['inventory']['content']);
    $this->assertStringContainsString('HTML Block API Policy', $payload['inventory']['content']);
    $this->assertSame('/webadmin/api/inventory', $payload['_links']['self']);
    $this->assertStringNotContainsString('/Users/', $payload['inventory']['content']);
  }

  #[Test]
  public function direct_html_block_payload_is_rejected_before_normalization(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    $normalized = app(InternalContentApiOperations::class)
      ->normalizeBlock(['type' => 'html', 'settings' => ['content' => '<div>x</div>']], 'block', null, $errors, $warnings);

    $this->assertNull($normalized);
    $this->assertSame('block_type_not_api_writable', $errors[0]['code']);
    $this->assertSame('block.type', $errors[0]['path']);
  }

  #[Test]
  public function nested_html_block_payload_is_rejected(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)->normalizeBlock([
      'type' => 'section',
      'children' => [['type' => 'html', 'settings' => []]],
    ], 'block', null, $errors, $warnings);

    $this->assertSame(
      'block_type_not_api_writable',
      app(BlockTypeApiAuthoringPolicy::class)->codeFromErrors($errors),
    );
    $this->assertContains('block.children.0.type', array_column($errors, 'path'));
  }

  #[Test]
  public function structured_block_payload_is_not_blocked_by_the_policy(): void
  {
    $this->seedBlockTypes();

    $errors = [];
    $warnings = [];
    app(InternalContentApiOperations::class)
      ->normalizeBlock(['type' => 'header', 'translations' => ['title' => 'Hello']], 'block', null, $errors, $warnings);

    $this->assertNull(app(BlockTypeApiAuthoringPolicy::class)->codeFromErrors($errors));
  }

  #[Test]
  public function patching_an_existing_html_block_is_rejected_without_writing(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $block = $this->seedBlock($page, $slotType, 'html', ['title' => 'Original']);

    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', [], [], [], [], json_encode(['translations' => ['title' => 'Changed']]));
    $request->headers->set('Content-Type', 'application/json');

    $response = app(InternalContentResourceController::class)->updateBlock($request, $block);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('block_type_not_api_writable', $payload['code']);
    $this->assertSame('Original', $block->fresh()->title);
  }

  #[Test]
  public function patching_a_structured_block_still_works(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $block = $this->seedBlock($page, $slotType, 'header', ['title' => 'Original']);

    $request = Request::create('/webadmin/api/blocks/'.$block->id, 'PATCH', [], [], [], [], json_encode(['url' => '/somewhere']));
    $request->headers->set('Content-Type', 'application/json');

    $response = app(InternalContentResourceController::class)->updateBlock($request, $block);

    $this->assertNotSame(422, $response->getStatusCode());
    $this->assertNotSame('block_type_not_api_writable', $response->getData(true)['code'] ?? null);
  }

  #[Test]
  public function deleting_a_page_that_contains_html_is_rejected_and_leaves_the_page(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $this->seedBlock($page, $slotType, 'html');

    $response = app(InternalContentResourceController::class)->deletePage($page);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('block_type_not_api_writable', $payload['code']);
    $this->assertNotNull(Page::query()->find($page->id));
  }

  #[Test]
  public function deleting_a_block_subtree_containing_html_is_rejected_and_leaves_the_blocks(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $section = $this->seedBlock($page, $slotType, 'section');
    $html = $this->seedBlock($page, $slotType, 'html', [], $section);

    // A token that legitimately holds the destructive delete capability still
    // cannot remove a subtree containing a human-only block.
    $request = $this->requestWithCapability('DELETE', [CmsApiTokenCapabilities::CONTENT_BLOCKS_DELETE]);

    $response = app(InternalContentResourceController::class)
      ->deleteSlotBlock($request, $page, $slotType->slug, $section);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('block_type_not_api_writable', $payload['code']);
    $this->assertNotNull(Block::query()->find($section->id));
    $this->assertNotNull(Block::query()->find($html->id));
  }

  #[Test]
  public function reordering_a_sibling_group_containing_html_is_rejected(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $first = $this->seedBlock($page, $slotType, 'header');
    $second = $this->seedBlock($page, $slotType, 'html');

    $request = Request::create('/', 'PATCH', [], [], [], [], json_encode(['blocks' => [$second->id, $first->id]]));
    $request->headers->set('Content-Type', 'application/json');

    $response = app(InternalContentResourceController::class)->reorderSlotBlocks($request, $page, $slotType->slug);
    $payload = $response->getData(true);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('block_type_not_api_writable', $payload['code']);
    $this->assertSame(0, $first->fresh()->sort_order);
  }

  #[Test]
  public function content_contract_reports_html_as_readable_human_only_without_a_writable_example(): void
  {
    $this->seedBlockTypes();

    $payload = app(InternalContentResourceController::class)->contentContract()->getData(true);
    $blocks = collect($payload['blocks'] ?? $payload['block_contracts'] ?? []);
    $html = $blocks->firstWhere('slug', 'html');

    $this->assertNotNull($html, 'Content contract must still describe the html block.');
    $this->assertTrue($html['api_readable']);
    $this->assertFalse($html['api_writable']);
    $this->assertSame('human_only', $html['authoring']);
    $this->assertSame('block_type_not_api_writable', $html['api_write_error_code']);
    $this->assertArrayNotHasKey('example', $html);
    $this->assertArrayNotHasKey('validation_rules', $html);

    $header = $blocks->firstWhere('slug', 'header');
    $this->assertTrue($header['api_writable']);
    $this->assertSame('api_and_human', $header['authoring']);
  }

  #[Test]
  public function html_blocks_remain_readable_through_the_api(): void
  {
    $this->seedBlockTypes();
    [$page, $slotType] = $this->seedPage();
    $block = $this->seedBlock($page, $slotType, 'html', ['title' => 'Readable']);

    $payload = app(InternalContentResourceController::class)->block($block)->getData(true);

    $this->assertTrue($payload['ok']);
    $this->assertSame($block->id, $payload['block']['id']);
  }

  /**
   * @param  list<string>  $capabilities
   */
  private function requestWithCapability(string $method, array $capabilities): Request
  {
    $token = CmsApiToken::query()->create([
      'name' => 'test',
      'token_hash' => hash('sha256', 'test-token'),
      'token_preview' => 'test',
      'capabilities' => $capabilities,
    ]);

    $request = Request::create('/', $method);
    $request->attributes->set('cms_api_token', $token);

    return $request;
  }

  private function seedBlockTypes(): void
  {
    foreach ([
      ['name' => 'HTML', 'slug' => 'html', 'category' => 'advanced', 'is_container' => false],
      ['name' => 'Header', 'slug' => 'header', 'category' => 'content', 'is_container' => false],
      ['name' => 'Section', 'slug' => 'section', 'category' => 'layout', 'is_container' => true],
    ] as $index => $definition) {
      BlockType::query()->create($definition + [
        'source_type' => 'static',
        'is_system' => false,
        'sort_order' => $index,
        'status' => 'published',
      ]);
    }
  }

  /**
   * @return array{0: Page, 1: SlotType}
   */
  private function seedPage(): array
  {
    $site = Site::query()->create(['name' => 'Test', 'handle' => 'test', 'is_primary' => true]);
    Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $slotType = SlotType::query()->create(['name' => 'Main', 'slug' => 'main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slotType->id, 'sort_order' => 0]);

    return [$page, $slotType];
  }

  private function seedBlock(Page $page, SlotType $slotType, string $type, array $attributes = [], ?Block $parent = null): Block
  {
    $blockType = BlockType::query()->where('slug', $type)->firstOrFail();

    return Block::query()->create($attributes + [
      'page_id' => $page->id,
      'parent_id' => $parent?->id,
      'type' => $type,
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => $slotType->slug,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'draft',
    ]);
  }
}
