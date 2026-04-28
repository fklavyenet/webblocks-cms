<?php

namespace Tests\Feature\Admin;

use App\Models\Asset;
use App\Models\AssetFolder;
use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

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

        $response->assertOk();
        $response->assertSee('Upload Asset');
        $response->assertSee('New Folder');
        $response->assertSee('All folders');
        $response->assertSee('Example image');
        $response->assertDontSee('Accepted: images, videos, PDF, Office files, text, CSV, ZIP.');
        $response->assertDontSee('Organize shared assets into compact folders.');
        $response->assertDontSee('MIME Type');
        $response->assertDontSee('Size');
        $response->assertSee(route('admin.media.show', $asset), false);
        $response->assertSee('Copy asset URL');
        $response->assertSee('List');
        $response->assertSee('Grid');
    }

    #[Test]
    public function media_index_handles_expired_upload_sessions_with_a_full_login_redirect(): void
    {
        $user = User::factory()->superAdmin()->create();

        $response = $this->actingAs($user)->get(route('admin.media.index'));

        $response->assertOk();
        $response->assertSee('assets/webblocks-cms/js/admin/core.js', false);
        $response->assertSee('assets/webblocks-cms/js/admin/asset-picker.js', false);
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
            'usage_asset' => $usedAsset->id,
        ]));

        $response->assertOk();
        $response->assertSee('Used grid asset');
        $response->assertDontSee('Unused guide');
        $response->assertSee('Used in 1');
        $response->assertSee('Asset usage');
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
    public function asset_detail_can_link_back_to_preview_modal(): void
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

        $response = $this->actingAs($user)->get(route('admin.media.show', ['asset' => $asset, 'back_to_preview' => 1]));

        $response->assertOk();
        $response->assertSee('Back to Preview');
        $response->assertSee(route('admin.media.index', ['preview' => $asset->id]), false);
    }

    #[Test]
    public function asset_edit_preserves_back_to_preview_context(): void
    {
        $user = User::factory()->superAdmin()->create();

        $asset = Asset::create([
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
        ]);

        $editResponse = $this->actingAs($user)->get(route('admin.media.edit', ['asset' => $asset, 'back_to_preview' => 1]));

        $editResponse->assertOk();
        $editResponse->assertSee(route('admin.media.show', ['asset' => $asset, 'back_to_preview' => 1]), false);
        $editResponse->assertSee('name="back_to_preview" value="1"', false);

        $updateResponse = $this->actingAs($user)->put(route('admin.media.update', $asset), [
            'title' => 'Updated title',
            'alt_text' => 'Alt text',
            'caption' => 'Caption',
            'description' => 'Description',
            'folder_id' => null,
            'back_to_preview' => 1,
        ]);

        $updateResponse->assertRedirect(route('admin.media.show', ['asset' => $asset, 'back_to_preview' => 1]));
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
        $this->assertDatabaseHas('assets', [
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

        $deleteResponse->assertRedirect(route('admin.media.show', $asset));
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
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

        $response->assertRedirect(route('admin.media.show', $asset));

        $this->assertDatabaseHas('assets', [
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
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
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
        $this->assertStringContainsString('/admin/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
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

        $response->assertRedirect(route('admin.media.show', $asset));
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
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
        $this->assertStringContainsString('/admin/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
        $this->assertStringContainsString('/blocks', (string) $response->headers->get('Location'));

        $block = Block::query()->latest('id')->first();

        $this->assertNotNull($block);
        $this->assertSame([$firstAsset->id, $secondAsset->id], $block->galleryAssetIds());
        $this->assertDatabaseHas('block_assets', [
            'block_id' => $block->id,
            'asset_id' => $firstAsset->id,
            'role' => 'gallery_item',
            'position' => 0,
        ]);
        $this->assertDatabaseHas('block_assets', [
            'block_id' => $block->id,
            'asset_id' => $secondAsset->id,
            'role' => 'gallery_item',
            'position' => 1,
        ]);
        $this->assertNull($block->fresh()->settings);
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
        $this->assertStringContainsString('/admin/pages/'.$page->id.'/slots/', (string) $response->headers->get('Location'));
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

        $response->assertRedirect(route('admin.media.show', $asset));
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
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
        $this->assertStringNotContainsString('id="wb-overlay-root"', $html);
        $this->assertStringNotContainsString('id="wb-gallery-viewer"', $html);
        $this->assertStringNotContainsString('data-wb-gallery-target="#wb-gallery-viewer"', $galleryHtml);
        $this->assertStringContainsString('/storage/media/images/gallery-public-1.jpg', $galleryHtml);
        $this->assertStringContainsString('data-wb-gallery-alt="Gallery image one alt"', $galleryHtml);
        $this->assertStringContainsString('data-wb-gallery-caption="First gallery caption"', $galleryHtml);
        $this->assertStringContainsString('data-wb-gallery-meta="First gallery meta"', $galleryHtml);
        $this->assertStringNotContainsString('wb-gallery-viewer-prev', $html);
        $this->assertStringNotContainsString('wb-gallery-viewer-next', $html);
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

        $response->assertRedirect(route('admin.media.show', $asset));
        $this->assertDatabaseHas('assets', ['id' => $asset->id]);
    }
}
