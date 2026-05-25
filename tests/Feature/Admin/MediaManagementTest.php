<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia as BlockAsset;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media as Asset;
use WebBlocks\Cms\Models\MediaFolder as AssetFolder;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Models\SystemSetting;
use WebBlocks\Cms\Support\System\SystemSettings;

class MediaManagementTest extends TestCase
{
  use RefreshDatabase;

  private function editor(): User
  {
    return User::factory()->editor()->create();
  }

  private function slotType(string $slug = 'main', string $name = 'Main', int $sortOrder = 2): SlotType
  {
    return SlotType::query()->updateOrCreate(
      ['slug' => $slug],
      ['name' => $name, 'status' => 'published', 'sort_order' => $sortOrder, 'is_system' => true],
    );
  }

  private function pageSlot(Page $page, SlotType $slotType): PageSlot
  {
    return PageSlot::query()->firstOrCreate(
      ['page_id' => $page->id, 'slot_type_id' => $slotType->id],
      ['sort_order' => 0],
    );
  }

  #[Test]
  public function media_index_is_library_first_and_does_not_show_large_inline_forms(): void
  {
    $user = User::factory()->superAdmin()->create();
    $folder = AssetFolder::create(['name' => 'Images']);
    $asset = Asset::create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/images/example.jpg',
      'filename' => 'example.jpg',
      'original_name' => 'Example.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Example image',
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index'));
    $returnUrl = route('admin.media.index');

    $response->assertOk();
    $response->assertSee('Upload Media');
    $response->assertSee('New Folder');
    $response->assertSee('All folders');
    $response->assertSee('Example image');
    $response->assertDontSee('Accepted: images, videos, PDF, Office files, text, CSV, ZIP.');
    $response->assertDontSee('Organize shared assets into compact folders.');
    $response->assertDontSee('MIME Type');
    $response->assertDontSee('Size');
    $response->assertDontSee('Upload Asset');
    $response->assertSee(route('admin.media.edit', ['media' => $asset, 'return_url' => $returnUrl]), false);
    $response->assertDontSee('Copy asset URL');
    $response->assertSee('List');
    $response->assertSee('Grid');
    $response->assertSee('<div class="wb-card wb-card-muted">', false);
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('<div class="wb-table-wrap">', false);
    $response->assertDontSee('<div class="wb-page-actions">', false);
    $response->assertSee('<strong>Media Library</strong>', false);
    $response->assertSee('<th>Media</th>', false);
    $response->assertDontSee('<th>Asset</th>', false);
    $response->assertSee('name="view" value="list"', false);
  }

  #[Test]
  public function media_index_renders_filter_and_list_layout_with_standard_admin_listing_pattern(): void
  {
    $user = User::factory()->superAdmin()->create();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/layout-check.jpg',
      'filename' => 'layout-check.jpg',
      'original_name' => 'layout-check.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Layout check asset',
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index'));

    $response->assertOk();
    $response->assertSee('data-admin-listing-filters', false);
    $response->assertSee('data-admin-listing-filters-search', false);
    $response->assertSee('data-admin-listing-filters-fields', false);
    $response->assertSee('data-admin-listing-filters-actions', false);
    $response->assertSee('id="media_search"', false);
    $response->assertSee('id="media_kind"', false);
    $response->assertSee('id="media_usage"', false);
    $response->assertSee('id="media_sort"', false);
    $response->assertSee('id="media_direction"', false);
    $response->assertSee('>Sort by<', false);
    $response->assertSee('>Direction<', false);
    $response->assertSee('<button type="submit" class="wb-btn wb-btn-primary">Apply</button>', false);
    $response->assertSee('<div class="wb-card wb-card-muted">', false);
    $response->assertSee('<div class="wb-table-wrap">', false);
    $response->assertSee('wb-media-view-toggle', false);
    $response->assertSee(route('admin.media.edit', ['media' => $asset, 'return_url' => route('admin.media.index')]), false);
  }

  #[Test]
  public function media_index_uses_total_count_for_page_header_and_filtered_count_for_library_badge(): void
  {
    $user = User::factory()->superAdmin()->create();

    Asset::create([
      'disk' => 'public',
      'path' => 'media/images/count-visible.jpg',
      'filename' => 'count-visible.jpg',
      'original_name' => 'count-visible.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Count visible asset',
    ]);

    Asset::create([
      'disk' => 'public',
      'path' => 'media/documents/count-hidden.pdf',
      'filename' => 'count-hidden.pdf',
      'original_name' => 'count-hidden.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2200,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Count hidden asset',
    ]);

    $unfilteredResponse = $this->actingAs($user)->get(route('admin.media.index'));

    $unfilteredResponse->assertOk();
    $unfilteredResponse->assertSee('<span class="wb-status-pill wb-status-info" data-admin-page-count>2</span>', false);
    $unfilteredResponse->assertSee('<span class="wb-status-pill wb-status-info" data-admin-list-count>2</span>', false);

    $filteredResponse = $this->actingAs($user)->get(route('admin.media.index', ['search' => 'visible']));

    $filteredResponse->assertOk();
    $filteredResponse->assertSee('Count visible asset');
    $filteredResponse->assertDontSee('Count hidden asset');
    $filteredResponse->assertSee('<span class="wb-status-pill wb-status-info" data-admin-page-count>2</span>', false);
    $filteredResponse->assertSee('<span class="wb-status-pill wb-status-info" data-admin-list-count>1</span>', false);
  }

  #[Test]
  public function media_index_pagination_preserves_filters_and_uses_compact_summary(): void
  {
    $user = User::factory()->superAdmin()->create();

    foreach (range(1, 35) as $index) {
      Asset::create([
        'disk' => 'public',
        'path' => 'media/images/pattern-'.$index.'.jpg',
        'filename' => 'pattern-'.$index.'.jpg',
        'original_name' => 'pattern-'.$index.'.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1024 + $index,
        'kind' => Asset::KIND_IMAGE,
        'visibility' => 'public',
        'title' => 'Pattern asset '.$index,
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.media.index', [
      'search' => 'Pattern asset',
      'kind' => Asset::KIND_IMAGE,
      'sort' => 'title',
      'direction' => 'asc',
    ]));

    $response->assertOk();
    $response->assertSee('data-admin-pagination', false);
    $response->assertSee('class="wb-pagination wb-pagination-compact"', false);
    $response->assertSee('aria-label="Media pagination"', false);
    $response->assertSee('aria-current="page">1</span>', false);
    $response->assertSee('data-admin-pagination-summary', false);
    $response->assertSee('1-15/35', false);
    $response->assertDontSee('Showing 1-15 of 35', false);
    $response->assertSee(e(route('admin.media.index', [
      'search' => 'Pattern asset',
      'kind' => Asset::KIND_IMAGE,
      'sort' => 'title',
      'direction' => 'asc',
      'page' => 2,
    ])), false);
    $response->assertSee('<span class="wb-pagination-link">Previous</span>', false);

    $pageTwo = $this->actingAs($user)->get(route('admin.media.index', [
      'search' => 'Pattern asset',
      'kind' => Asset::KIND_IMAGE,
      'sort' => 'title',
      'direction' => 'asc',
      'page' => 2,
    ]));

    $pageTwo->assertOk();
    $pageTwo->assertSee('aria-current="page">2</span>', false);
    $pageTwo->assertSee('16-30/35', false);
    $pageTwo->assertSee(e(route('admin.media.index', [
      'search' => 'Pattern asset',
      'kind' => Asset::KIND_IMAGE,
      'sort' => 'title',
      'direction' => 'asc',
      'page' => 1,
    ])), false);
  }

  #[Test]
  public function media_index_uses_configured_admin_listing_rows_per_page_and_keeps_sort_query(): void
  {
    $user = User::factory()->superAdmin()->create();

    SystemSetting::query()->updateOrCreate(
      ['key' => SystemSettings::ADMIN_LISTING_PER_PAGE],
      ['value' => '12'],
    );

    foreach (range(1, 13) as $index) {
      Asset::create([
        'disk' => 'public',
        'path' => 'media/images/configured-media-'.$index.'.jpg',
        'filename' => 'configured-media-'.$index.'.jpg',
        'original_name' => 'configured-media-'.$index.'.jpg',
        'extension' => 'jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1000 + $index,
        'kind' => Asset::KIND_IMAGE,
        'visibility' => 'public',
        'title' => 'Configured Media '.sprintf('%02d', $index),
      ]);
    }

    $response = $this->actingAs($user)->get(route('admin.media.index', [
      'search' => 'Configured Media',
      'sort' => 'title',
      'direction' => 'asc',
    ]));

    $response->assertOk();
    $response->assertSee('1-12/13', false);
    $response->assertSee('sort=title', false);
    $response->assertSee('direction=asc', false);
    $response->assertSee('page=2', false);

    $pageTwo = $this->actingAs($user)->get(route('admin.media.index', [
      'search' => 'Configured Media',
      'sort' => 'title',
      'direction' => 'asc',
      'page' => 2,
    ]));

    $pageTwo->assertOk();
    $pageTwo->assertSee('13-13/13', false);
    $pageTwo->assertSee('aria-current="page">2</span>', false);
  }

  #[Test]
  public function media_index_defaults_sort_and_direction_and_keeps_valid_selected_values(): void
  {
    $user = User::factory()->superAdmin()->create();

    Asset::create([
      'disk' => 'public',
      'path' => 'media/images/default-sort.jpg',
      'filename' => 'default-sort.jpg',
      'original_name' => 'default-sort.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Default sort asset',
    ]);

    $defaultResponse = $this->actingAs($user)->get(route('admin.media.index'));
    $defaultResponse->assertOk();
    $defaultResponse->assertSee('<option value="updated_at" selected>Updated at</option>', false);
    $defaultResponse->assertSee('<option value="desc" selected>Descending</option>', false);

    $customResponse = $this->actingAs($user)->get(route('admin.media.index', [
      'sort' => 'filename',
      'direction' => 'asc',
    ]));

    $customResponse->assertOk();
    $customResponse->assertSee('<option value="filename" selected>Filename</option>', false);
    $customResponse->assertSee('<option value="asc" selected>Ascending</option>', false);
  }

  #[Test]
  public function media_index_invalid_sort_and_direction_fall_back_safely(): void
  {
    $user = User::factory()->superAdmin()->create();

    Asset::create([
      'disk' => 'public',
      'path' => 'media/images/fallback-sort.jpg',
      'filename' => 'fallback-sort.jpg',
      'original_name' => 'fallback-sort.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Fallback sort asset',
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index', [
      'sort' => 'drop table media',
      'direction' => 'sideways',
    ]));

    $response->assertOk();
    $response->assertSee('<option value="updated_at" selected>Updated at</option>', false);
    $response->assertSee('<option value="desc" selected>Descending</option>', false);
    $response->assertDontSee('drop table media');
    $response->assertDontSee('sideways');
  }

  #[Test]
  public function media_index_sorts_deterministically_by_title_updated_folder_and_usage(): void
  {
    $user = User::factory()->superAdmin()->create();
    $alphaFolder = AssetFolder::create(['name' => 'Alpha Folder']);
    $betaFolder = AssetFolder::create(['name' => 'Beta Folder']);
    $slotType = $this->slotType();
    $page = Page::create([
      'title' => 'Usage Sort Page',
      'slug' => 'usage-sort-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    $this->pageSlot($page, $slotType);

    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    $betaTitle = Asset::create([
      'folder_id' => $betaFolder->id,
      'disk' => 'public',
      'path' => 'media/images/beta-title.jpg',
      'filename' => 'beta-title.jpg',
      'original_name' => 'beta-title.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1000,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Beta title',
      'updated_at' => now()->subDay(),
      'created_at' => now()->subDays(2),
    ]);

    $alphaTitle = Asset::create([
      'folder_id' => $alphaFolder->id,
      'disk' => 'public',
      'path' => 'media/images/alpha-title.jpg',
      'filename' => 'alpha-title.jpg',
      'original_name' => 'alpha-title.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1000,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Alpha title',
      'updated_at' => now()->subHours(2),
      'created_at' => now()->subDays(3),
    ]);

    $noFolder = Asset::create([
      'folder_id' => null,
      'disk' => 'public',
      'path' => 'media/images/no-folder.jpg',
      'filename' => 'no-folder.jpg',
      'original_name' => 'no-folder.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1000,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'No folder title',
      'updated_at' => now()->subMinutes(30),
      'created_at' => now()->subDays(4),
    ]);

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Usage sort block',
      'asset_id' => $betaTitle->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    BlockAsset::create([
      'block_id' => Block::query()->latest('id')->value('id'),
      'asset_id' => $alphaTitle->id,
      'role' => 'gallery_item',
      'position' => 0,
    ]);

    $titleAsc = $this->actingAs($user)->get(route('admin.media.index', ['sort' => 'title', 'direction' => 'asc']));
    $titleAsc->assertOk();
    $this->assertBefore($titleAsc->getContent(), 'Alpha title', 'Beta title');

    $updatedDesc = $this->actingAs($user)->get(route('admin.media.index', ['sort' => 'updated_at', 'direction' => 'desc']));
    $updatedDesc->assertOk();
    $this->assertBefore($updatedDesc->getContent(), 'No folder title', 'Alpha title');

    $folderAsc = $this->actingAs($user)->get(route('admin.media.index', ['sort' => 'folder', 'direction' => 'asc']));
    $folderAsc->assertOk();
    $this->assertBefore($folderAsc->getContent(), 'Alpha title', 'Beta title');
    $this->assertBefore($folderAsc->getContent(), 'Beta title', 'No folder title');

    $usageDesc = $this->actingAs($user)->get(route('admin.media.index', ['sort' => 'usage', 'direction' => 'desc']));
    $usageDesc->assertOk();
    $this->assertBefore($usageDesc->getContent(), 'Alpha title', 'No folder title');
    $this->assertBefore($usageDesc->getContent(), 'Beta title', 'No folder title');
  }

  #[Test]
  public function media_index_handles_expired_upload_sessions_with_a_full_login_redirect(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->actingAs($user)->get(route('admin.media.index'));

    $response->assertOk();
    $response->assertSee('cms/js/admin/core.js', false);
    $response->assertSee('cms/js/admin/asset-picker.js', false);
    $assetPickerJs = file_get_contents(public_path('cms/js/admin/asset-picker.js'));
    $this->assertNotFalse($assetPickerJs);
    $this->assertStringContainsString("fetch('/cms/media'", $assetPickerJs);
    $this->assertStringNotContainsString("fetch('/admin/media'", $assetPickerJs);
    $response->assertDontSee("credentials: 'same-origin'", false);
    $response->assertDontSee('if (response.redirected)', false);
    $response->assertDontSee('response.status === 401 || response.status === 403 || response.status === 419', false);
    $response->assertDontSee('function redirectToLoginFromAdmin()', false);
  }

  #[Test]
  public function media_index_supports_grid_view_filters_and_usage_drawer(): void
  {
    $user = User::factory()->superAdmin()->create();
    $folder = AssetFolder::create(['name' => 'Brand']);
    $slotType = $this->slotType();
    $page = Page::create([
      'title' => 'Media Library Page',
      'slug' => 'media-library-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);

    $usedAsset = Asset::create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/images/used-grid.jpg',
      'filename' => 'used-grid.jpg',
      'original_name' => 'used-grid.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 2048,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Used grid asset',
      'width' => 1200,
      'height' => 800,
    ]);

    $unusedAsset = Asset::create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/documents/unused-guide.pdf',
      'filename' => 'unused-guide.pdf',
      'original_name' => 'unused-guide.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 4096,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Unused guide',
    ]);

    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Hero visual',
      'asset_id' => $usedAsset->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index', [
      'view' => 'grid',
      'kind' => 'image',
      'usage' => 'used',
      'folder_id' => $folder->id,
      'usage_media' => $usedAsset->id,
    ]));

    $response->assertOk();
    $response->assertSee('Used grid asset');
    $response->assertDontSee('Unused guide');
    $response->assertSee('Used in 1');
    $response->assertSee('Media usage');
    $response->assertSee('Media Library Page');
    $response->assertSee('Hero visual');
    $response->assertSee('wb-media-grid', false);
  }

  #[Test]
  public function media_index_supports_unused_filter_and_preview_modal(): void
  {
    $user = User::factory()->superAdmin()->create();

    $previewable = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/preview-modal.jpg',
      'filename' => 'preview-modal.jpg',
      'original_name' => 'preview-modal.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Preview modal asset',
    ]);

    $other = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/other-used.jpg',
      'filename' => 'other-used.jpg',
      'original_name' => 'other-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Other used asset',
    ]);

    $slotType = $this->slotType();
    $page = Page::create([
      'title' => 'Preview Test Page',
      'slug' => 'preview-test-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Used image',
      'asset_id' => $other->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index', [
      'usage' => 'unused',
      'preview' => $previewable->id,
    ]));

    $response->assertOk();
    $response->assertSee('Preview modal asset');
    $response->assertDontSee('Other used asset');
    $response->assertSee('Unused');
    $response->assertSee('media-preview-modal');
  }

  #[Test]
  public function media_usage_filters_only_query_real_reference_tables_and_separate_used_from_unused_media(): void
  {
    $user = User::factory()->superAdmin()->create();

    $blockUsed = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/usage-block-used.jpg',
      'filename' => 'usage-block-used.jpg',
      'original_name' => 'usage-block-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Block used media',
    ]);

    $siteUsed = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/usage-site-used.jpg',
      'filename' => 'usage-site-used.jpg',
      'original_name' => 'usage-site-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Site used media',
    ]);

    $seoUsed = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/usage-seo-used.jpg',
      'filename' => 'usage-seo-used.jpg',
      'original_name' => 'usage-seo-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'SEO used media',
    ]);

    $unused = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/usage-unused.jpg',
      'filename' => 'usage-unused.jpg',
      'original_name' => 'usage-unused.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Actually unused media',
    ]);

    $slotType = $this->slotType();
    $defaultLocale = Locale::query()->where('is_default', true)->firstOrFail();
    $site = Site::create([
      'name' => 'Usage Filter Site',
      'handle' => 'usage-filter-site',
      'is_primary' => true,
    ]);
    $page = Page::create([
      'site_id' => $site->id,
      'title' => 'Usage Filter Page',
      'slug' => 'usage-filter-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Usage filter block',
      'media_id' => $blockUsed->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $site->update([
      'favicon_media_id' => $siteUsed->id,
    ]);

    PageTranslation::query()->updateOrCreate(
      ['page_id' => $page->id, 'locale_id' => $defaultLocale->id],
      [
        'name' => 'Usage Filter Page',
        'slug' => 'usage-filter-page',
        'site_id' => $site->id,
        'path' => '/p/usage-filter-page',
        'og_image_media_id' => $seoUsed->id,
      ],
    );

    DB::flushQueryLog();
    DB::enableQueryLog();

    $usedResponse = $this->actingAs($user)->get(route('admin.media.index', ['usage' => 'used']));

    $usedResponse->assertOk();
    $usedResponse->assertSee('Block used media');
    $usedResponse->assertSee('Site used media');
    $usedResponse->assertSee('SEO used media');
    $usedResponse->assertDontSee('Actually unused media');

    $usedSql = collect(DB::getQueryLog())
      ->pluck('query')
      ->map(fn (string $query) => Str::lower($query))
      ->implode("\n");

    $this->assertStringNotContainsString('`media_id` is not null', $usedSql);
    $this->assertStringNotContainsString('`asset_id` is not null', $usedSql);
    $this->assertStringContainsString('from "blocks"', $usedSql);
    $this->assertStringContainsString('from "block_media"', $usedSql);
    $this->assertStringContainsString('from "sites"', $usedSql);
    $this->assertStringContainsString('from "page_translations"', $usedSql);

    DB::flushQueryLog();

    $unusedResponse = $this->actingAs($user)->get(route('admin.media.index', ['usage' => 'unused']));

    $unusedResponse->assertOk();
    $unusedResponse->assertSee('Actually unused media');
    $unusedResponse->assertDontSee('Block used media');
    $unusedResponse->assertDontSee('Site used media');
    $unusedResponse->assertDontSee('SEO used media');

    $unusedSql = collect(DB::getQueryLog())
      ->pluck('query')
      ->map(fn (string $query) => Str::lower($query))
      ->implode("\n");

    $this->assertStringNotContainsString('`media_id` is null', $unusedSql);
    $this->assertStringNotContainsString('`asset_id` is null', $unusedSql);
  }

  #[Test]
  public function media_show_route_redirects_to_edit_and_preserves_safe_return_url(): void
  {
    $user = User::factory()->superAdmin()->create();

    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/back-to-preview.jpg',
      'filename' => 'back-to-preview.jpg',
      'original_name' => 'back-to-preview.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Back to preview asset',
    ]);

    $returnUrl = route('admin.media.index', ['search' => 'Back to preview asset', 'view' => 'grid']);
    $response = $this->actingAs($user)->get(route('admin.media.show', ['media' => $asset, 'return_url' => $returnUrl]));

    $response->assertRedirect(route('admin.media.edit', ['media' => $asset, 'return_url' => $returnUrl]));
  }

  #[Test]
  public function media_edit_uses_merged_screen_sections_and_safe_return_url_flow(): void
  {
    $user = User::factory()->superAdmin()->create();
    $folder = AssetFolder::create(['name' => 'Library']);

    $asset = Asset::create([
      'folder_id' => $folder->id,
      'disk' => 'public',
      'path' => 'media/images/edit-context.jpg',
      'filename' => 'edit-context.jpg',
      'original_name' => 'edit-context.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1536,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Edit context asset',
      'alt_text' => 'Current alt',
      'caption' => 'Current caption',
      'description' => 'Current description',
    ]);

    $returnUrl = route('admin.media.index', ['search' => 'Edit context asset', 'view' => 'grid']);

    $editResponse = $this->actingAs($user)->get(route('admin.media.edit', ['media' => $asset, 'return_url' => $returnUrl]));
    $editHtml = $editResponse->getContent();

    $editResponse->assertOk();
    $editResponse->assertSee('Edit Media: Edit context asset');
    $editResponse->assertSee('Review file details, update metadata, and manage this media item safely.');
    $editResponse->assertSee('Preview');
    $editResponse->assertSee('Usage');
    $editResponse->assertSee('Media Information');
    $editResponse->assertDontSee('<div class="wb-card-header"><strong>Metadata</strong></div>', false);
    $editResponse->assertDontSee('<div class="wb-card-header"><strong>Organization</strong></div>', false);
    $editResponse->assertDontSee('<div class="wb-card-header"><strong>Danger Zone</strong></div>', false);
    $editResponse->assertSee('name="title"', false);
    $editResponse->assertSee('name="alt_text"', false);
    $editResponse->assertSee('name="caption"', false);
    $editResponse->assertSee('name="description"', false);
    $editResponse->assertSee('name="folder_id"', false);
    $editResponse->assertDontSee('name="kind"', false);
    $editResponse->assertSee('<div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">', false);
    $editResponse->assertSee('<strong>Preview</strong>', false);
    $editResponse->assertSee('<div class="wb-card-header">', false);
    $editResponse->assertSee('<strong>Media Information</strong>', false);
    $editResponse->assertSee('>File Details<', false);
    $editResponse->assertSee('modal=file-details', false);
    $editResponse->assertSee('wb-card-body wb-grid wb-grid-2 wb-gap-4', false);
    $editResponse->assertDontSee('>Copy public URL<', false);
    $editResponse->assertSee('data-admin-form-actions', false);
    $editResponse->assertSee('>Save changes<', false);
    $editResponse->assertSee('name="return_url" value="'.e($returnUrl).'"', false);
    $editResponse->assertSee('href="'.e($returnUrl).'" class="wb-btn wb-btn-secondary"', false);
    $editResponse->assertDontSee('data-admin-form-actions-danger', false);
    $editResponse->assertDontSee('Delete media');
    $editResponse->assertDontSee('Delete blocked');
    $editResponse->assertDontSee('modal=delete-media', false);
    $this->assertSame(1, substr_count($editHtml, '>File Details<'));
    $this->assertMatchesRegularExpression('/<strong>Preview<\/strong>\s*<a href="[^"]*modal=file-details/', $editHtml);

    $updateResponse = $this->actingAs($user)->put(route('admin.media.update', $asset), [
      'title' => 'Updated title',
      'alt_text' => 'Alt text',
      'caption' => 'Caption',
      'description' => 'Description',
      'folder_id' => null,
      'return_url' => $returnUrl,
    ]);

    $updateResponse->assertRedirect($returnUrl);
  }

  #[Test]
  public function media_index_can_open_upload_and_folder_modals_and_persist_new_records(): void
  {
    Storage::fake('public');

    $user = $this->editor();
    $images = AssetFolder::create(['name' => 'Images']);

    $modalResponse = $this->actingAs($user)->get(route('admin.media.index', ['modal' => 'upload-asset']));
    $modalResponse->assertOk();
    $modalResponse->assertSee('media-upload-modal');
    $modalResponse->assertSee('Upload Media');
    $modalResponse->assertSee('Add a new file to the shared media library.');

    $folderModalResponse = $this->actingAs($user)->get(route('admin.media.index', ['modal' => 'new-folder']));
    $folderModalResponse->assertOk();
    $folderModalResponse->assertSee('media-folder-modal');
    $folderModalResponse->assertSee('Organize shared assets into compact folders.');

    $uploadResponse = $this->actingAs($user)->post(route('admin.media.store'), [
      'folder_id' => $images->id,
      'file' => UploadedFile::fake()->image('hero.jpg'),
      'title' => 'Hero image',
      'alt_text' => 'Hero alt',
      'caption' => 'Hero caption',
      'description' => 'Hero description',
      '_media_modal' => 'upload-asset',
    ]);

    $uploadResponse->assertRedirect(route('admin.media.index', ['folder_id' => $images->id]));
    $this->assertDatabaseHas('media', [
      'folder_id' => $images->id,
      'title' => 'Hero image',
      'alt_text' => 'Hero alt',
    ]);

    $folderResponse = $this->actingAs($user)->post(route('admin.media.folders.store'), [
      'name' => 'Downloads',
      'slug' => 'downloads',
      'parent_id' => $images->id,
      '_media_modal' => 'new-folder',
    ]);

    $folder = AssetFolder::query()->where('slug', 'downloads')->first();

    $this->assertNotNull($folder);
    $folderResponse->assertRedirect(route('admin.media.index', ['folder_id' => $folder->id]));
  }

  #[Test]
  public function image_block_uses_selected_internal_asset_only(): void
  {
    $user = $this->editor();
    $page = Page::create([
      'title' => 'Media Page',
      'slug' => 'media-page',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $slotType = $this->slotType();
    PageSlot::create([
      'page_id' => $page->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/picked-image.jpg',
      'filename' => 'picked-image.jpg',
      'original_name' => 'picked-image.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Picked image',
      'uploaded_by' => $user->id,
    ]);

    $storeResponse = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $blockType->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Image caption',
      'subtitle' => 'Image alt',
      'url' => null,
      'asset_id' => $asset->id,
      'variant' => null,
      'meta' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
    ]);

    $storeResponse->assertRedirect();

    $block = Block::query()->latest('id')->first();

    $this->assertNotNull($block);
    $this->assertSame($asset->id, $block->asset_id);
    $this->assertSame($asset->url(), $block->fresh()->asset?->url());

    $deleteResponse = $this->actingAs($user)->delete(route('admin.media.destroy', $asset));

    $deleteResponse->assertRedirect(route('admin.media.edit', $asset));
    $this->assertDatabaseHas('media', ['id' => $asset->id]);
  }

  #[Test]
  public function an_asset_can_be_updated(): void
  {
    $user = $this->editor();
    $folder = AssetFolder::create(['name' => 'Library']);
    $asset = Asset::create([
      'folder_id' => null,
      'disk' => 'public',
      'path' => 'media/images/example.jpg',
      'filename' => 'example.jpg',
      'original_name' => 'Example.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->put(route('admin.media.update', $asset), [
      'folder_id' => $folder->id,
      'title' => 'Hero Image',
      'alt_text' => 'Hero alt',
      'caption' => 'Hero caption',
      'description' => 'Hero description',
    ]);

    $response->assertRedirect(route('admin.media.index'));

    $this->assertDatabaseHas('media', [
      'id' => $asset->id,
      'folder_id' => $folder->id,
      'title' => 'Hero Image',
      'alt_text' => 'Hero alt',
    ]);
  }

  #[Test]
  public function deleting_an_asset_removes_file_and_record(): void
  {
    Storage::fake('public');

    $user = $this->editor();
    $file = UploadedFile::fake()->image('delete-me.jpg');
    $path = $file->store('media/images', 'public');

    $asset = Asset::create([
      'disk' => 'public',
      'path' => $path,
      'filename' => basename($path),
      'original_name' => 'delete-me.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => Storage::disk('public')->size($path),
      'kind' => 'image',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.media.destroy', $asset));

    $response->assertRedirect(route('admin.media.index'));
    $this->assertFalse(Storage::disk('public')->exists($path));
    $this->assertDatabaseMissing('media', ['id' => $asset->id]);
  }

  #[Test]
  public function image_block_can_store_asset_reference(): void
  {
    $user = User::factory()->create();
    $page = Page::create([
      'title' => 'Test Page',
      'slug' => 'test-page',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::create([
      'name' => 'Image',
      'slug' => 'image',
      'source_type' => 'static',
      'status' => 'published',
      'sort_order' => 1,
    ]);
    $slotType = $this->slotType();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/example.jpg',
      'filename' => 'example.jpg',
      'original_name' => 'Example.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1234,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Example',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $blockType->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Image caption',
      'subtitle' => 'Image alt',
      'url' => null,
      'asset_id' => $asset->id,
      'variant' => null,
      'meta' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response->assertRedirect();
    $this->assertStringContainsString('/cms/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
    $this->assertStringContainsString('/blocks', (string) $response->headers->get('Location'));

    $block = Block::query()->latest('id')->first();

    $this->assertNotNull($block);
    $this->assertSame($asset->id, $block->asset_id);
  }

  #[Test]
  public function used_asset_cannot_be_deleted(): void
  {
    $user = $this->editor();
    $page = Page::create([
      'title' => 'Test Page',
      'slug' => 'test-page',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::create([
      'name' => 'Image',
      'slug' => 'image',
      'source_type' => 'static',
      'status' => 'published',
      'sort_order' => 1,
    ]);
    $slotType = $this->slotType();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/used.jpg',
      'filename' => 'used.jpg',
      'original_name' => 'used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
    ]);

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'asset_id' => $asset->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.media.destroy', $asset));

    $response->assertRedirect(route('admin.media.edit', $asset));
    $this->assertDatabaseHas('media', ['id' => $asset->id]);
  }

  #[Test]
  public function gallery_block_can_store_asset_references(): void
  {
    $user = $this->editor();
    $page = Page::create([
      'title' => 'Gallery Page',
      'slug' => 'gallery-page',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'gallery'],
      [
        'name' => 'Gallery',
        'source_type' => 'static',
        'status' => 'published',
        'sort_order' => 2,
      ]
    );
    $slotType = $this->slotType();
    $this->pageSlot($page, $slotType);
    $firstAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/gallery-1.jpg',
      'filename' => 'gallery-1.jpg',
      'original_name' => 'gallery-1.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);
    $secondAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/gallery-2.jpg',
      'filename' => 'gallery-2.jpg',
      'original_name' => 'gallery-2.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $blockType->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Gallery block',
      'subtitle' => 'Gallery description',
      'url' => null,
      'asset_id' => null,
      'gallery_asset_ids' => [$firstAsset->id, $secondAsset->id],
      'attachment_asset_id' => null,
      'variant' => null,
      'meta' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response->assertRedirect();
    $this->assertStringContainsString('/cms/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
    $this->assertStringContainsString('/blocks', (string) $response->headers->get('Location'));

    $block = Block::query()->latest('id')->first();

    $this->assertNotNull($block);
    $this->assertSame([$firstAsset->id, $secondAsset->id], $block->galleryAssetIds());
    $this->assertDatabaseHas('block_media', [
      'block_id' => $block->id,
      'media_id' => $firstAsset->id,
      'role' => 'gallery_item',
      'position' => 0,
    ]);
    $this->assertDatabaseHas('block_media', [
      'block_id' => $block->id,
      'media_id' => $secondAsset->id,
      'role' => 'gallery_item',
      'position' => 1,
    ]);
    $this->assertSame([
      'variant' => 'grid',
      'columns' => '3',
      'gap' => 'md',
      'aspect_ratio' => 'auto',
      'captions_mode' => 'below',
      'overlay_mode' => 'gradient',
      'lightbox_enabled' => true,
    ], json_decode((string) $block->fresh()->settings, true));
  }

  #[Test]
  public function download_block_can_store_document_asset(): void
  {
    $user = $this->editor();
    $page = Page::create([
      'title' => 'Downloads',
      'slug' => 'downloads',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'download'],
      [
        'name' => 'Download',
        'source_type' => 'static',
        'status' => 'published',
        'sort_order' => 3,
      ]
    );
    $slotType = $this->slotType();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/documents/guide.pdf',
      'filename' => 'guide.pdf',
      'original_name' => 'guide.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 200,
      'kind' => 'document',
      'visibility' => 'public',
      'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)->post(route('admin.blocks.store'), [
      'page_id' => $page->id,
      'parent_id' => null,
      'block_type_id' => $blockType->id,
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Download guide',
      'subtitle' => 'PDF guide',
      'url' => null,
      'asset_id' => $asset->id,
      'gallery_asset_ids' => [],
      'attachment_asset_id' => null,
      'variant' => 'primary',
      'meta' => null,
      'settings' => null,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response->assertRedirect();
    $this->assertStringContainsString('/cms/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
    $this->assertStringContainsString('/blocks', (string) $response->headers->get('Location'));

    $block = Block::query()->latest('id')->first();

    $this->assertNotNull($block);
    $this->assertSame($asset->id, $block->downloadAsset()?->id);
    $this->assertSame($asset->id, $block->asset_id);
  }

  #[Test]
  public function used_gallery_asset_cannot_be_deleted_when_referenced_through_block_assets(): void
  {
    $user = $this->editor();
    $page = Page::create([
      'title' => 'Gallery Page',
      'slug' => 'gallery-page',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'gallery'],
      [
        'name' => 'Gallery',
        'source_type' => 'static',
        'status' => 'published',
        'sort_order' => 2,
      ]
    );
    $slotType = $this->slotType();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/gallery-used.jpg',
      'filename' => 'gallery-used.jpg',
      'original_name' => 'gallery-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
    ]);
    $block = Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'gallery',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'status' => 'published',
      'is_system' => false,
    ]);

    BlockAsset::create([
      'block_id' => $block->id,
      'asset_id' => $asset->id,
      'role' => 'gallery_item',
      'position' => 0,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.media.destroy', $asset));

    $response->assertRedirect(route('admin.media.edit', $asset));
    $this->assertDatabaseHas('media', ['id' => $asset->id]);
  }

  #[Test]
  public function public_gallery_block_uses_the_shared_webblocks_gallery_viewer(): void
  {
    $page = Page::create([
      'title' => 'Gallery Page',
      'slug' => 'gallery-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'gallery'],
      [
        'name' => 'Gallery',
        'source_type' => 'static',
        'status' => 'published',
        'sort_order' => 2,
      ]
    );
    $slotType = $this->slotType();
    $this->pageSlot($page, $slotType);
    $firstAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/gallery-public-1.jpg',
      'filename' => 'gallery-public-1.jpg',
      'original_name' => 'gallery-public-1.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Gallery image one',
      'alt_text' => 'Gallery image one alt',
      'caption' => 'First gallery caption',
      'description' => 'First gallery meta',
      'width' => 1200,
      'height' => 800,
    ]);
    $secondAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/gallery-public-2.jpg',
      'filename' => 'gallery-public-2.jpg',
      'original_name' => 'gallery-public-2.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 100,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Gallery image two',
      'alt_text' => 'Gallery image two alt',
      'caption' => 'Second gallery caption',
      'description' => 'Second gallery meta',
      'width' => 1200,
      'height' => 800,
    ]);
    $block = Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'gallery',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Selected project visuals',
      'subtitle' => 'Gallery description',
      'status' => 'published',
      'is_system' => false,
    ]);

    BlockAsset::create([
      'block_id' => $block->id,
      'asset_id' => $firstAsset->id,
      'role' => 'gallery_item',
      'position' => 0,
    ]);
    BlockAsset::create([
      'block_id' => $block->id,
      'asset_id' => $secondAsset->id,
      'role' => 'gallery_item',
      'position' => 1,
    ]);

    $response = $this->get(route('pages.show', $page->slug));
    $html = $response->getContent();
    $galleryHtml = view('pages.partials.blocks.gallery', [
      'block' => $block->fresh()->load(['blockAssets.asset', 'children']),
    ])->render();

    $response->assertOk();
    $this->assertStringContainsString('wb-gallery', $galleryHtml);
    $this->assertStringContainsString('wb-gallery-trigger', $galleryHtml);
    $this->assertStringContainsString('id="wb-overlay-root"', $html);
    $this->assertSame(1, substr_count($html, 'class="wb-overlay-root"'));
    $this->assertStringNotContainsString('id="wb-public-overlay-root"', $html);
    $this->assertMatchesRegularExpression('/id="wb-gallery-viewer-\d+"/', $html);
    $this->assertMatchesRegularExpression('/id="wb-overlay-root" class="wb-overlay-root">.*id="wb-gallery-viewer-\d+"/s', $html);
    $this->assertMatchesRegularExpression('/data-wb-gallery-target="#wb-gallery-viewer-\d+"/', $galleryHtml);
    $this->assertStringContainsString('/storage/media/images/gallery-public-1.jpg', $galleryHtml);
    $this->assertStringContainsString('data-wb-gallery-alt="Gallery image one alt"', $galleryHtml);
    $this->assertStringContainsString('data-wb-gallery-caption="First gallery caption"', $galleryHtml);
    $this->assertStringContainsString('data-wb-gallery-meta="First gallery meta"', $galleryHtml);
    $this->assertStringContainsString('wb-gallery-viewer-prev', $html);
    $this->assertStringContainsString('wb-gallery-viewer-next', $html);
  }

  #[Test]
  public function used_download_asset_cannot_be_deleted(): void
  {
    $user = User::factory()->create();
    $page = Page::create([
      'title' => 'Downloads',
      'slug' => 'downloads',
      'page_type' => 'default',
      'status' => 'draft',
    ]);
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'download'],
      [
        'name' => 'Download',
        'source_type' => 'static',
        'status' => 'published',
        'sort_order' => 3,
      ]
    );
    $slotType = $this->slotType();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/documents/download-used.pdf',
      'filename' => 'download-used.pdf',
      'original_name' => 'download-used.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 120,
      'kind' => 'document',
      'visibility' => 'public',
    ]);

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'download',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Download now',
      'asset_id' => $asset->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $response = $this->actingAs($user)->delete(route('admin.media.destroy', $asset));

    $response->assertRedirect(route('admin.media.edit', $asset));
    $this->assertDatabaseHas('media', ['id' => $asset->id]);
  }

  #[Test]
  public function media_index_actions_use_edit_routes_preview_modal_and_modal_delete_pattern(): void
  {
    $user = User::factory()->superAdmin()->create();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/list-actions.jpg',
      'filename' => 'list-actions.jpg',
      'original_name' => 'list-actions.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'List actions asset',
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index', ['search' => 'List actions asset', 'sort' => 'title', 'direction' => 'asc']));
    $returnUrl = route('admin.media.index', ['search' => 'List actions asset', 'sort' => 'title', 'direction' => 'asc']);

    $response->assertOk();
    $response->assertSee(route('admin.media.edit', ['media' => $asset, 'return_url' => $returnUrl]), false);
    $response->assertSee('class="wb-action-btn wb-action-btn-edit" title="Edit media" aria-label="Edit media"', false);
    $response->assertSee('class="wb-action-btn wb-action-btn-view" title="Preview media" aria-label="Preview media"', false);
    $response->assertSee('preview='.$asset->id, false);
    $response->assertDontSee('Copy asset URL');
    $response->assertDontSee('wb-icon-copy');
    $response->assertDontSee('onsubmit="return confirm', false);
    $response->assertSee(route('admin.media.edit', ['media' => $asset, 'return_url' => $returnUrl]), false);
    $response->assertSee('modal=delete-media', false);
  }

  #[Test]
  public function media_index_renders_bulk_delete_selection_ui_and_modal_without_browser_confirm(): void
  {
    $user = User::factory()->superAdmin()->create();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/bulk-ui.jpg',
      'filename' => 'bulk-ui.jpg',
      'original_name' => 'bulk-ui.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Bulk UI asset',
    ]);

    $response = $this->actingAs($user)->get(route('admin.media.index'));

    $response->assertOk();
    $response->assertSee('data-wb-admin-bulk-listing', false);
    $response->assertSee('data-wb-admin-select-all-visible', false);
    $response->assertSee('data-wb-admin-row-select', false);
    $response->assertSee('data-wb-target="#bulk-delete-media-modal"', false);
    $response->assertSee(route('admin.media.bulk-destroy'), false);
    $response->assertSee('name="media_ids[]"', false);
    $response->assertSee('value="'.$asset->id.'"', false);
    $response->assertSee('data-wb-admin-bulk-modal-count', false);
    $response->assertDontSee('confirm(', false);
  }

  #[Test]
  public function media_bulk_delete_requires_authentication(): void
  {
    $this->delete(route('admin.media.bulk-destroy'), [
      'media_ids' => [1],
    ])->assertRedirect(route('login'));
  }

  #[Test]
  public function admin_can_bulk_delete_selected_unused_media(): void
  {
    Storage::fake('public');

    $user = User::factory()->superAdmin()->create();
    $first = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/bulk-first.jpg',
      'filename' => 'bulk-first.jpg',
      'original_name' => 'bulk-first.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Bulk first asset',
    ]);
    $second = Asset::create([
      'disk' => 'public',
      'path' => 'media/documents/bulk-second.pdf',
      'filename' => 'bulk-second.pdf',
      'original_name' => 'bulk-second.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2048,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Bulk second asset',
    ]);
    $unselected = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/bulk-unselected.jpg',
      'filename' => 'bulk-unselected.jpg',
      'original_name' => 'bulk-unselected.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Bulk unselected asset',
    ]);

    foreach ([$first, $second, $unselected] as $media) {
      Storage::disk('public')->put($media->path, 'placeholder');
    }

    $response = $this->actingAs($user)->delete(route('admin.media.bulk-destroy'), [
      'media_ids' => [$first->id, $second->id],
    ]);

    $response->assertRedirect(route('admin.media.index'));
    $response->assertSessionHas('status', '2 selected media items deleted.');
    $this->assertDatabaseMissing('media', ['id' => $first->id]);
    $this->assertDatabaseMissing('media', ['id' => $second->id]);
    $this->assertDatabaseHas('media', ['id' => $unselected->id]);
    $this->assertFalse(Storage::disk('public')->exists($first->path));
    $this->assertFalse(Storage::disk('public')->exists($second->path));
    $this->assertTrue(Storage::disk('public')->exists($unselected->path));
  }

  #[Test]
  public function media_bulk_delete_rejects_missing_or_invalid_ids_safely(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->from(route('admin.media.index'))
      ->delete(route('admin.media.bulk-destroy'), [
        'media_ids' => [],
      ])
      ->assertRedirect(route('admin.media.index'))
      ->assertSessionHasErrors(['media_ids']);

    $this->actingAs($user)
      ->from(route('admin.media.index'))
      ->delete(route('admin.media.bulk-destroy'), [
        'media_ids' => [999999],
      ])
      ->assertRedirect(route('admin.media.index'))
      ->assertSessionHasErrors(['media_ids.0']);
  }

  #[Test]
  public function media_bulk_delete_respects_usage_guards_and_reports_partial_success(): void
  {
    Storage::fake('public');

    $user = User::factory()->superAdmin()->create();
    $safeAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/bulk-safe.jpg',
      'filename' => 'bulk-safe.jpg',
      'original_name' => 'bulk-safe.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Bulk safe asset',
    ]);
    $usedAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/bulk-used.jpg',
      'filename' => 'bulk-used.jpg',
      'original_name' => 'bulk-used.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Bulk used asset',
    ]);
    $page = Page::create([
      'title' => 'Bulk Media Usage Page',
      'slug' => 'bulk-media-usage-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $slotType = $this->slotType();
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Protected image',
      'asset_id' => $usedAsset->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    Storage::disk('public')->put($safeAsset->path, 'safe');
    Storage::disk('public')->put($usedAsset->path, 'used');

    $response = $this->actingAs($user)->delete(route('admin.media.bulk-destroy'), [
      'media_ids' => [$safeAsset->id, $usedAsset->id],
    ]);

    $response->assertRedirect(route('admin.media.index'));
    $response->assertSessionHas('status', '1 selected media item deleted. 1 could not be deleted.');
    $response->assertSessionHasErrors(['media']);
    $this->assertDatabaseMissing('media', ['id' => $safeAsset->id]);
    $this->assertDatabaseHas('media', ['id' => $usedAsset->id]);
    $this->assertFalse(Storage::disk('public')->exists($safeAsset->path));
    $this->assertTrue(Storage::disk('public')->exists($usedAsset->path));
  }

  #[Test]
  public function media_edit_keeps_usage_and_file_details_modal_without_inline_delete_ui(): void
  {
    $user = User::factory()->superAdmin()->create();
    $page = Page::create([
      'title' => 'Media Usage Page',
      'slug' => 'media-usage-page',
      'page_type' => 'default',
      'status' => 'published',
    ]);
    $slotType = $this->slotType();
    $blockType = BlockType::query()->firstOrCreate(
      ['slug' => 'image'],
      ['name' => 'Image', 'source_type' => 'static', 'status' => 'published', 'sort_order' => 1]
    );

    $usedAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/used-edit-screen.jpg',
      'filename' => 'used-edit-screen.jpg',
      'original_name' => 'used-edit-screen.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1200,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Used edit screen asset',
    ]);

    $unusedAsset = Asset::create([
      'disk' => 'public',
      'path' => 'media/documents/unused-edit-screen.pdf',
      'filename' => 'unused-edit-screen.pdf',
      'original_name' => 'unused-edit-screen.pdf',
      'extension' => 'pdf',
      'mime_type' => 'application/pdf',
      'size' => 2200,
      'kind' => 'document',
      'visibility' => 'public',
      'title' => 'Unused edit screen asset',
    ]);

    Block::create([
      'page_id' => $page->id,
      'parent_id' => null,
      'type' => 'image',
      'block_type_id' => $blockType->id,
      'source_type' => 'static',
      'slot' => 'main',
      'slot_type_id' => $slotType->id,
      'sort_order' => 0,
      'title' => 'Hero visual',
      'asset_id' => $usedAsset->id,
      'status' => 'published',
      'is_system' => false,
    ]);

    $usedResponse = $this->actingAs($user)->get(route('admin.media.edit', $usedAsset));
    $usedResponse->assertOk();
    $usedResponse->assertSee('Hero visual');
    $usedResponse->assertSee('Open');
    $usedResponse->assertSee('Media Usage Page');
    $usedResponse->assertSee('Preview');
    $usedResponse->assertSee('Usage');
    $usedResponse->assertSee('Media Information');
    $usedResponse->assertDontSee('Delete blocked');
    $usedResponse->assertDontSee('This media item is still used by protected CMS consumers, so it cannot be deleted yet.');
    $usedResponse->assertDontSee('Delete media');
    $usedResponse->assertDontSee('modal=delete-media', false);
    $usedResponse->assertDontSee('onsubmit="return confirm', false);

    $fileDetailsResponse = $this->actingAs($user)->get(route('admin.media.edit', ['media' => $unusedAsset, 'modal' => 'file-details']));
    $fileDetailsResponse->assertOk();
    $fileDetailsResponse->assertSee('role="dialog"', false);
    $fileDetailsResponse->assertSee('File Details');
    $fileDetailsResponse->assertSee('Filename:');
    $fileDetailsResponse->assertSee('Original Name:');
    $fileDetailsResponse->assertSee('MIME Type:');
    $fileDetailsResponse->assertSee('Extension:');
    $fileDetailsResponse->assertSee('Size:');
    $fileDetailsResponse->assertSee('Kind:');
    $fileDetailsResponse->assertSee('Disk:');
    $fileDetailsResponse->assertSee('Dimensions:');
    $fileDetailsResponse->assertSee('Path');
    $fileDetailsResponse->assertSee('Public URL');
    $fileDetailsResponse->assertSee('Created:');
    $fileDetailsResponse->assertSee('Updated:');
    $fileDetailsResponse->assertSee('aria-label="Copy public URL"', false);
    $fileDetailsResponse->assertSee('wb-btn-icon', false);
    $fileDetailsResponse->assertSee('wb-btn-ghost', false);
    $fileDetailsResponse->assertSee('<div class="wb-cluster wb-gap-2 wb-flex-wrap">', false);
    $fileDetailsResponse->assertDontSee('>Copy public URL<', false);

    $unusedResponse = $this->actingAs($user)->get(route('admin.media.edit', ['media' => $unusedAsset, 'modal' => 'delete-media']));
    $unusedResponse->assertOk();
    $unusedResponse->assertSee('Unused media');
    $unusedResponse->assertSee('This media item is not referenced by protected CMS consumers yet.');
    $unusedResponse->assertSee('Delete media');
    $unusedResponse->assertSee('role="dialog"', false);
    $unusedResponse->assertSee('Close delete media modal');
    $unusedResponse->assertDontSee('confirm(');
  }

  #[Test]
  public function media_edit_cancel_and_save_ignore_external_return_url_and_fall_back_to_index(): void
  {
    $user = User::factory()->superAdmin()->create();
    $asset = Asset::create([
      'disk' => 'public',
      'path' => 'media/images/return-url.jpg',
      'filename' => 'return-url.jpg',
      'original_name' => 'return-url.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => 1024,
      'kind' => 'image',
      'visibility' => 'public',
      'title' => 'Return URL asset',
    ]);

    $safeReturnUrl = route('admin.media.index', ['search' => 'Return URL asset', 'sort' => 'usage', 'direction' => 'asc', 'view' => 'grid']);

    $safeEdit = $this->actingAs($user)->get(route('admin.media.edit', ['media' => $asset, 'return_url' => $safeReturnUrl]));
    $safeEdit->assertSee('href="'.e($safeReturnUrl).'" class="wb-btn wb-btn-secondary"', false);

    $unsafeEdit = $this->actingAs($user)->get(route('admin.media.edit', ['media' => $asset, 'return_url' => 'https://evil.example.test/cms/media']));
    $unsafeEdit->assertSee('href="'.route('admin.media.index').'" class="wb-btn wb-btn-secondary"', false);
    $unsafeEdit->assertDontSee('evil.example.test');

    $safeUpdate = $this->actingAs($user)->put(route('admin.media.update', $asset), [
      'title' => 'Saved with safe return url',
      'alt_text' => null,
      'caption' => null,
      'description' => null,
      'folder_id' => null,
      'return_url' => $safeReturnUrl,
    ]);
    $safeUpdate->assertRedirect($safeReturnUrl);

    $unsafeUpdate = $this->actingAs($user)->put(route('admin.media.update', $asset), [
      'title' => 'Saved with fallback return url',
      'alt_text' => null,
      'caption' => null,
      'description' => null,
      'folder_id' => null,
      'return_url' => 'https://evil.example.test/cms/media',
    ]);
    $unsafeUpdate->assertRedirect(route('admin.media.index'));
  }

  private function assertBefore(string $html, string $first, string $second): void
  {
    $firstPosition = strpos($html, $first);
    $secondPosition = strpos($html, $second);

    $this->assertNotFalse($firstPosition, sprintf('Failed asserting that [%s] exists in the response.', $first));
    $this->assertNotFalse($secondPosition, sprintf('Failed asserting that [%s] exists in the response.', $second));
    $this->assertLessThan($secondPosition, $firstPosition, sprintf('Failed asserting that [%s] appears before [%s].', $first, $second));
  }
}
