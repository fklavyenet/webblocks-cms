<?php

namespace Tests\Feature\Console;

use App\Models\BlockType;
use Database\Seeders\BlockTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncCoreBlockTypesCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function missing_core_block_types_are_created_by_the_sync_command(): void
    {
        BlockType::query()->create([
            'name' => 'Custom Banner',
            'slug' => 'custom-banner',
            'category' => 'custom',
            'description' => 'Install-specific block type.',
            'source_type' => 'static',
            'is_system' => false,
            'is_container' => false,
            'sort_order' => 500,
            'status' => 'published',
        ]);

        $this->artisan('block-types:sync-core')->assertExitCode(0);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'header',
            'name' => 'Header',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('block_types', [
            'slug' => 'custom-banner',
            'name' => 'Custom Banner',
            'status' => 'published',
        ]);
    }

    #[Test]
    public function existing_core_block_type_metadata_is_updated_by_the_sync_command(): void
    {
        BlockType::query()->create([
            'name' => 'Old Header',
            'slug' => 'header',
            'category' => 'legacy',
            'description' => 'Outdated description.',
            'source_type' => 'custom',
            'is_system' => true,
            'is_container' => true,
            'sort_order' => 999,
            'status' => 'draft',
        ]);

        $this->artisan('block-types:sync-core')->assertExitCode(0);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'header',
            'name' => 'Header',
            'category' => 'content',
            'description' => 'Primitive translated heading text with a shared heading level.',
            'source_type' => 'static',
            'is_system' => 0,
            'is_container' => 0,
            'sort_order' => 5,
            'status' => 'published',
        ]);
    }

    #[Test]
    public function custom_block_types_are_preserved_by_the_sync_command(): void
    {
        BlockType::query()->create([
            'name' => 'Custom Hero',
            'slug' => 'custom-hero',
            'category' => 'custom',
            'description' => 'Install-owned block type.',
            'source_type' => 'dynamic',
            'is_system' => false,
            'is_container' => true,
            'sort_order' => 321,
            'status' => 'published',
        ]);

        $this->artisan('block-types:sync-core')->assertExitCode(0);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'custom-hero',
            'name' => 'Custom Hero',
            'category' => 'custom',
            'description' => 'Install-owned block type.',
            'source_type' => 'dynamic',
            'is_container' => 1,
            'sort_order' => 321,
            'status' => 'published',
        ]);
    }

    #[Test]
    public function running_the_sync_command_twice_is_idempotent(): void
    {
        $this->artisan('block-types:sync-core')->assertExitCode(0);

        $this->artisan('block-types:sync-core')->assertExitCode(0);

        $this->assertSame(1, BlockType::query()->where('slug', 'header')->count());
        $this->assertSame(30, BlockType::query()->whereIn('slug', [
            'header',
            'plain_text',
            'rich-text',
            'section',
            'container',
            'cluster',
            'grid',
            'content_header',
            'code',
            'button_link',
            'card',
            'stat-card',
            'table',
            'quote',
            'link-list',
            'link-list-item',
            'toc',
            'alert',
            'breadcrumb',
            'header-actions',
            'sticky-navbar',
            'navbar-brand',
            'navbar-navigation',
            'sidebar-brand',
            'sidebar-navigation',
            'sidebar-nav-item',
            'sidebar-nav-group',
            'search-form',
            'sidebar-footer',
            'html',
        ])->count());
    }

    #[Test]
    public function block_type_seeder_still_runs_through_the_shared_sync_path(): void
    {
        BlockType::query()->create([
            'name' => 'Legacy Custom',
            'slug' => 'custom-legacy',
            'category' => 'custom',
            'description' => 'Should be drafted by the seeder.',
            'source_type' => 'static',
            'is_system' => false,
            'is_container' => false,
            'sort_order' => 50,
            'status' => 'published',
        ]);

        BlockType::query()->create([
            'name' => 'Wrong Header',
            'slug' => 'header',
            'category' => 'legacy',
            'description' => 'Wrong metadata.',
            'source_type' => 'custom',
            'is_system' => true,
            'is_container' => true,
            'sort_order' => 500,
            'status' => 'draft',
        ]);

        $this->seed(BlockTypeSeeder::class);

        $this->assertDatabaseHas('block_types', [
            'slug' => 'header',
            'name' => 'Header',
            'status' => 'published',
        ]);
        $this->assertDatabaseHas('block_types', [
            'slug' => 'custom-legacy',
            'status' => 'draft',
        ]);
        $this->assertDatabaseHas('block_types', [
            'slug' => 'text',
            'category' => 'legacy',
            'status' => 'draft',
        ]);
    }
}
