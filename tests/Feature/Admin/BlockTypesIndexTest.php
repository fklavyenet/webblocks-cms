<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\BlockTypeSeeder;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;

class BlockTypesIndexTest extends TestCase
{
  use RefreshDatabase;

  private function seedFoundation(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(BlockTypeSeeder::class);
  }

  #[Test]
  public function index_shows_filter_form_and_listing_actions(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $header = BlockType::query()->where('slug', 'header')->firstOrFail();
    $editableBlockType = BlockType::query()->create([
      'name' => 'Modal Editable Type',
      'slug' => 'modal-editable-type',
      'description' => 'Visible custom block type for index actions.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => -1,
      'status' => 'published',
    ]);

    $response = $this->actingAs($user)->get(route('admin.block-types.index'));

    $response->assertOk();
    $response->assertSee('Search');
    $response->assertSee('Category');
    $response->assertSee('Status');
    $response->assertSee('Usage');
    $response->assertSee('Support');
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('data-admin-listing-filters-search', false);
    $response->assertSee('data-admin-listing-filters-fields', false);
    $response->assertSee('data-admin-listing-filters-actions', false);
    $response->assertSee('id="block_types_search"', false);
    $response->assertSee('id="block_types_category"', false);
    $response->assertSee('id="block_types_status"', false);
    $response->assertSee('id="block_types_usage"', false);
    $response->assertSee('id="block_types_support"', false);
    $response->assertSee('Apply filters');
    $response->assertSee('Search block types...');
    $response->assertSee('New Custom Block Type');
    $response->assertSee('data-admin-block-type-contract-action', false);
    $response->assertSee('Edit block type');

    $expectedContractUrl = route('admin.block-types.index', ['modal' => 'block-type-contract', 'contract_block_type' => $header->id]);
    $expectedModalUrl = route('admin.block-types.index', ['modal' => 'edit-block-type', 'block_type' => $editableBlockType->id]);

    $response->assertSee('href="'.e($expectedContractUrl).'"', false);
    $response->assertSee('href="'.e($expectedModalUrl).'"', false);
    $response->assertSee('aria-haspopup="dialog"', false);
    $response->assertSee('aria-controls="blockTypeEditModal-'.$editableBlockType->id.'"', false);
    $response->assertDontSee('href="'.e(route('admin.block-types.edit', $editableBlockType)).'" class="wb-action-btn wb-action-btn-edit"', false);
  }

  #[Test]
  public function index_can_open_block_type_contract_modal_for_a_published_core_block_type(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('slug', 'header')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'modal' => 'block-type-contract',
      'contract_block_type' => $blockType->id,
    ]));

    $response->assertOk();
    $response->assertSee('data-admin-block-type-contract-modal', false);
    $response->assertSee('Block Type Contract: '.$blockType->name, false);
    $response->assertSee('<code>header</code>', false);
    $response->assertSee('resources/views/admin/blocks/types/header.blade.php', false);
    $response->assertSee('<code>title</code>', false);
    $response->assertSee('resources/views/pages/partials/blocks/header.blade.php', false);
    $response->assertSee('Known Gaps', false);
    $response->assertSee('This modal is informational only and does not save changes.', false);
  }

  #[Test]
  public function index_contract_modal_reflects_phase_three_resolved_contracts(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('slug', 'stat-card')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'modal' => 'block-type-contract',
      'contract_block_type' => $blockType->id,
    ]));

    $response->assertOk();
    $response->assertSee('Block Type Contract: '.$blockType->name, false);
    $response->assertSee('<code>title</code>', false);
    $response->assertSee('<code>subtitle</code>', false);
    $response->assertSee('<code>content</code>', false);
    $response->assertSee('clear', false);
    $response->assertSee('Optional URL stays on the canonical block url column.', false);
    $response->assertDontSee('The admin form stores an optional URL, but the current public renderer does not use it.', false);
  }

  #[Test]
  public function index_contract_modal_reflects_resolved_navigation_brand_contracts(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('slug', 'sidebar-brand')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => 'sidebar brand',
      'modal' => 'block-type-contract',
      'contract_block_type' => $blockType->id,
    ]));

    $response->assertOk();
    $response->assertSee('Block Type Contract: '.$blockType->name, false);
    $response->assertSee('<code>settings.aria_label</code>', false);
    $response->assertSee('clear', false);
    $response->assertSee('No documented gaps.', false);
    $response->assertDontSee('Logo-only accessibility handling is weaker than the current Navbar Brand contract.', false);
  }

  #[Test]
  public function index_shows_a_safe_contract_fallback_for_undocumented_block_types(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->create([
      'name' => 'Undocumented Contract Type',
      'slug' => 'undocumented-contract-type',
      'description' => 'Custom block type without a shipped contract.',
      'category' => 'custom',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => -10,
      'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'modal' => 'block-type-contract',
      'contract_block_type' => $blockType->id,
    ]));

    $response->assertOk();
    $response->assertSee('Block Type Contract: '.$blockType->name, false);
    $response->assertSee('No shipped contract is documented for this block type yet.', false);
    $response->assertSee('<code>undocumented-contract-type</code>', false);
  }

  #[Test]
  public function edit_page_heading_includes_the_block_type_name(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('is_system', false)->orderBy('name')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.block-types.edit', $blockType));

    $response->assertOk();
    $response->assertSee('Edit Block Type: '.$blockType->name, false);
    $response->assertSee('<h1 class="wb-page-header-title">Edit Block Type: '.$blockType->name.'</h1>', false);
  }

  #[Test]
  public function index_can_open_block_type_edit_in_a_modal_that_submits_to_the_update_route(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('is_system', false)->orderBy('name')->firstOrFail();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => $blockType->name,
      'status' => $blockType->status,
      'modal' => 'edit-block-type',
      'block_type' => $blockType->id,
    ]));

    $response->assertOk();
    $response->assertSee('id="blockTypeEditModal-'.$blockType->id.'"', false);
    $response->assertSee('class="wb-modal wb-modal-lg"', false);
    $response->assertSee('data-wb-admin-autoload-overlay', false);
    $response->assertDontSee('class="wb-modal wb-modal-lg is-open"', false);
    $response->assertSee('Edit Block Type: '.$blockType->name, false);
    $response->assertSee('action="'.e(route('admin.block-types.update', $blockType)).'"', false);
    $response->assertSee('name="return_url" value="'.e(route('admin.block-types.index', ['search' => $blockType->name, 'status' => $blockType->status])).'"', false);
  }

  #[Test]
  public function block_types_page_header_count_ignores_filters_while_card_count_uses_filtered_results(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    foreach (range(1, 3) as $index) {
      BlockType::query()->create([
        'name' => 'Count Pattern '.$index,
        'slug' => 'count-pattern-'.$index,
        'description' => 'Count testing block type '.$index,
        'category' => 'pattern',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => 400 + $index,
        'status' => 'published',
      ]);
    }

    $totalCount = BlockType::query()->count();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => 'Count Pattern 1',
    ]));

    $response->assertOk();
    $response->assertSee('Count Pattern 1');
    $response->assertDontSee('Count Pattern 2');
    $response->assertSee('data-admin-page-count', false);
    $response->assertSee('data-admin-list-count', false);
    $response->assertSee('<span class="wb-status-pill wb-status-info" data-admin-page-count>'.$totalCount.'</span>', false);
    $response->assertSee('<span class="wb-status-pill wb-status-info" data-admin-list-count>1</span>', false);
  }

  #[Test]
  public function updating_from_modal_returns_to_the_filtered_block_types_list_context(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();
    $blockType = BlockType::query()->where('is_system', false)->orderBy('name')->firstOrFail();
    $returnUrl = route('admin.block-types.index', [
      'search' => $blockType->name,
      'category' => $blockType->category,
      'status' => $blockType->status,
      'support' => 'user',
      'usage' => 'unused',
      'page' => 2,
    ]);

    $response = $this->actingAs($user)->put(route('admin.block-types.update', $blockType), [
      'name' => $blockType->name.' Updated',
      'slug' => $blockType->slug,
      'description' => $blockType->description,
      'category' => $blockType->category,
      'source_type' => $blockType->source_type,
      'sort_order' => $blockType->sort_order,
      'status' => $blockType->status,
      'is_container' => $blockType->is_container ? '1' : '0',
      'return_url' => $returnUrl,
      '_block_type_modal' => 'edit-block-type',
      '_block_type_id' => (string) $blockType->id,
    ]);

    $response->assertRedirect($returnUrl);
    $this->assertDatabaseHas('block_types', [
      'id' => $blockType->id,
      'name' => $blockType->name.' Updated',
    ]);
  }

  #[Test]
  public function search_filters_by_name_slug_and_description(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'rich']));

    $response->assertOk();
    $response->assertSee('Rich Text');
    $response->assertDontSee('Sidebar Navigation');

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'breadcrumb']));

    $response->assertOk();
    $response->assertSee('Breadcrumb');
    $response->assertDontSee('Plain Text');
  }

  #[Test]
  public function category_filter_limits_results_to_matching_category(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['category' => 'layout']));

    $response->assertOk();
    $response->assertSee('Section');
    $response->assertSee('Container');
    $response->assertDontSee('Breadcrumb');
    $response->assertDontSee('Rich Text');
  }

  #[Test]
  public function status_filter_limits_results_to_matching_status(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    BlockType::query()->create([
      'name' => 'Draft Legacy Demo',
      'slug' => 'draft-legacy-demo',
      'description' => 'Visible draft block type for status filter coverage.',
      'category' => 'legacy',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => -100,
      'status' => 'draft',
    ]);

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['status' => 'draft']));

    $response->assertOk();
    $response->assertSee('Draft Legacy Demo');
    $response->assertSee('legacy');
    $response->assertDontSee('Breadcrumb');
  }

  #[Test]
  public function support_filters_cover_system_user_container_admin_and_render_options(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $systemResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'system']));
    $systemResponse->assertOk();
    $systemResponse->assertSee('Breadcrumb');
    $systemResponse->assertDontSee('Section');

    $userResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'user']));
    $userResponse->assertOk();
    $userResponse->assertSee('Section');
    $userResponse->assertDontSee('Breadcrumb');

    $containerResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'container']));
    $containerResponse->assertOk();
    $containerResponse->assertSee('Section');
    $containerResponse->assertDontSee('Breadcrumb');

    $adminResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'admin']));
    $adminResponse->assertOk();
    $adminResponse->assertSee('Rich Text');
    $adminResponse->assertDontSee('Map');

    $renderResponse = $this->actingAs($user)->get(route('admin.block-types.index', ['support' => 'render']));
    $renderResponse->assertOk();
    $renderResponse->assertSee('Rich Text');
    $renderResponse->assertDontSee('Textarea');
  }

  #[Test]
  public function usage_filter_defaults_to_showing_used_and_unused_block_types(): void
  {
    $this->seedFoundation();

    $usedType = BlockType::query()->create([
      'name' => 'Usage Used Type',
      'slug' => 'usage-used-type',
      'description' => 'Used block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 0,
      'status' => 'published',
    ]);
    $unusedType = BlockType::query()->create([
      'name' => 'Usage Unused Type',
      'slug' => 'usage-unused-type',
      'description' => 'Unused block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 1,
      'status' => 'published',
    ]);

    $this->createBlockUsingType($usedType);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index'));

    $response->assertOk();
    $response->assertSee('Usage Used Type');
    $response->assertSee('Usage Unused Type');
  }

  #[Test]
  public function usage_filter_can_limit_results_to_used_block_types(): void
  {
    $this->seedFoundation();

    $usedType = BlockType::query()->create([
      'name' => 'Usage Used Only',
      'slug' => 'usage-used-only',
      'description' => 'Used-only block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 0,
      'status' => 'published',
    ]);
    $unusedType = BlockType::query()->create([
      'name' => 'Usage Hidden Unused',
      'slug' => 'usage-hidden-unused',
      'description' => 'Unused block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 1,
      'status' => 'published',
    ]);

    $this->createBlockUsingType($usedType);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['usage' => 'used']));

    $response->assertOk();
    $response->assertSee('Usage Used Only');
    $response->assertDontSee('Usage Hidden Unused');
  }

  #[Test]
  public function usage_filter_can_limit_results_to_unused_block_types(): void
  {
    $this->seedFoundation();

    $usedType = BlockType::query()->create([
      'name' => 'Usage Hidden Used',
      'slug' => 'usage-hidden-used',
      'description' => 'Used block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 0,
      'status' => 'published',
    ]);
    $unusedType = BlockType::query()->create([
      'name' => 'Usage Unused Only',
      'slug' => 'usage-unused-only',
      'description' => 'Unused-only block type fixture.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 1,
      'status' => 'published',
    ]);

    $this->createBlockUsingType($usedType);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['usage' => 'unused']));

    $response->assertOk();
    $response->assertSee('Usage Unused Only');
    $response->assertDontSee('Usage Hidden Used');
  }

  #[Test]
  public function usage_filter_combines_with_existing_filters(): void
  {
    $this->seedFoundation();

    $usedPattern = BlockType::query()->create([
      'name' => 'Pattern Match',
      'slug' => 'pattern-match',
      'description' => 'Pattern block that is used.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 300,
      'status' => 'published',
    ]);

    BlockType::query()->create([
      'name' => 'Pattern Spare',
      'slug' => 'pattern-spare',
      'description' => 'Pattern block that is unused.',
      'category' => 'pattern',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 301,
      'status' => 'published',
    ]);

    $this->createBlockUsingType($usedPattern);

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => 'Pattern',
      'category' => 'pattern',
      'status' => 'published',
      'support' => 'user',
      'usage' => 'used',
    ]));

    $response->assertOk();
    $response->assertSee('Pattern Match');
    $response->assertDontSee('Pattern Spare');
    $response->assertDontSee('Breadcrumb');
  }

  #[Test]
  public function seeder_publishes_table_toc_quote_and_header_and_removes_heading_when_unused(): void
  {
    $this->seedFoundation();

    $this->assertDatabaseHas('block_types', [
      'slug' => 'table',
      'name' => 'Table',
      'category' => 'content',
      'status' => 'published',
    ]);
    $this->assertDatabaseHas('block_types', [
      'slug' => 'toc',
      'name' => 'TOC',
      'category' => 'navigation',
      'status' => 'published',
    ]);
    $this->assertDatabaseHas('block_types', [
      'slug' => 'quote',
      'name' => 'Quote',
      'category' => 'content',
      'status' => 'published',
    ]);
    $this->assertDatabaseHas('block_types', [
      'slug' => 'header',
      'name' => 'Header',
      'category' => 'content',
      'status' => 'published',
    ]);
    $this->assertDatabaseMissing('block_types', ['slug' => 'heading']);
  }

  #[Test]
  public function seeder_publishes_html_as_a_trusted_advanced_block(): void
  {
    $this->seedFoundation();

    $this->assertDatabaseHas('block_types', [
      'slug' => 'html',
      'name' => 'HTML (Trusted)',
      'category' => 'advanced',
      'status' => 'published',
    ]);

    $htmlType = BlockType::query()->where('slug', 'html')->firstOrFail();

    $this->assertSame(
      'Render trusted static HTML. Use Rich Text for normal body copy and Code for escaped snippets. Do not paste untrusted scripts or third-party embeds here.',
      $htmlType->description,
    );
  }

  #[Test]
  public function seeder_refuses_to_delete_heading_when_live_blocks_still_reference_it(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);

    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'About',
      'slug' => 'about',
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'About', 'slug' => 'about', 'path' => '/p/about'],
    );

    $slotType = SlotType::query()->updateOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    $headingType = BlockType::query()->updateOrCreate([
      'slug' => 'heading',
    ], [
      'name' => 'Heading',
      'category' => 'legacy',
      'source_type' => 'static',
      'is_system' => false,
      'is_container' => false,
      'sort_order' => 100,
      'status' => 'published',
    ]);

    Block::query()->create([
      'page_id' => $page->id,
      'type' => 'heading',
      'block_type_id' => $headingType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Legacy heading',
      'status' => 'published',
      'is_system' => false,
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Cannot remove legacy block type [heading] because 1 live block(s) still reference it.');

    $this->seed(BlockTypeSeeder::class);
  }

  #[Test]
  public function pagination_preserves_filters_and_uses_webblocks_pagination_contract(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    foreach (range(1, 35) as $index) {
      BlockType::query()->create([
        'name' => 'Filterable Pattern '.$index,
        'slug' => 'filterable-pattern-'.$index,
        'description' => 'Pattern testing block type '.$index,
        'category' => 'pattern',
        'source_type' => 'static',
        'is_system' => false,
        'is_container' => false,
        'sort_order' => 200 + $index,
        'status' => 'published',
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => 'Pattern',
      'category' => 'pattern',
      'status' => 'published',
      'support' => 'user',
      'usage' => 'unused',
    ]));

    $response->assertOk();
    $response->assertSee('data-admin-pagination', false);
    $response->assertSee('class="wb-pagination wb-pagination-compact"', false);
    $response->assertSee('aria-label="Block types pagination"', false);
    $response->assertSee('class="wb-pagination-list"', false);
    $response->assertSee('aria-current="page">1</span>', false);
    $response->assertSee('data-admin-pagination-summary', false);
    $response->assertSee('1-15/35', false);
    $response->assertDontSee('Showing 1-15 of 35', false);
    $response->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;usage=unused&amp;page=2', false);
    $response->assertSee('<span class="wb-pagination-link">Previous</span>', false);

    $pageTwo = $this->actingAs($user)->get(route('admin.block-types.index', [
      'search' => 'Pattern',
      'category' => 'pattern',
      'status' => 'published',
      'support' => 'user',
      'usage' => 'unused',
      'page' => 2,
    ]));

    $pageTwo->assertOk();
    $pageTwo->assertSee('aria-current="page">2</span>', false);
    $pageTwo->assertSee('16-30/35', false);
    $pageTwo->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;usage=unused&amp;page=1', false);
    $pageTwo->assertSee('search=Pattern&amp;category=pattern&amp;status=published&amp;support=user&amp;usage=unused&amp;page=3', false);
  }

  #[Test]
  public function empty_results_show_filter_reset_state_without_breaking_table_card_flow(): void
  {
    $this->seedFoundation();

    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.block-types.index', ['search' => 'does-not-exist']));

    $response->assertOk();
    $response->assertSee('No block types found.');
    $response->assertSee('Try changing your filters.');
    $response->assertSee('>Reset<', false);
    $response->assertDontSee('<table class="wb-table', false);
  }

  private function createBlockUsingType(BlockType $blockType): Block
  {
    $site = Site::query()->where('is_primary', true)->firstOrFail();
    $slotType = SlotType::query()->firstOrCreate(
      ['slug' => 'main'],
      ['name' => 'Main', 'status' => 'published', 'sort_order' => 1, 'is_system' => true],
    );

    $page = Page::query()->create([
      'site_id' => $site->id,
      'title' => 'Usage Page '.$blockType->slug,
      'slug' => 'usage-page-'.$blockType->slug,
      'status' => 'published',
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => Page::defaultLocaleId()],
      ['site_id' => $site->id, 'name' => 'Usage Page '.$blockType->name, 'slug' => 'usage-page-'.$blockType->slug, 'path' => '/usage/'.$blockType->slug],
    );

    PageSlot::query()->create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);

    return Block::query()->create([
      'page_id' => $page->id,
      'type' => $blockType->slug,
      'block_type_id' => $blockType->id,
      'source_type' => $blockType->source_type ?? 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Usage block',
      'status' => 'published',
      'is_system' => false,
    ]);
  }
}
