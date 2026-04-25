<?php

namespace Tests\Feature;

use App\Models\BlockType;
use App\Models\Locale;
use App\Models\Page;
use App\Models\Site;
use App\Models\SlotType;
use App\Support\Imports\LegacyFklavyeSandboxImporter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class LegacyFklavyeSandboxImporterTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function importer_writes_pages_into_the_primary_site_with_default_translation_rows(): void
    {
        SlotType::query()->updateOrCreate(['slug' => 'header'], ['name' => 'Header', 'status' => 'published', 'sort_order' => 1, 'is_system' => true]);
        SlotType::query()->updateOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published', 'sort_order' => 2, 'is_system' => true]);
        SlotType::query()->updateOrCreate(['slug' => 'footer'], ['name' => 'Footer', 'status' => 'published', 'sort_order' => 3, 'is_system' => true]);
        BlockType::query()->updateOrCreate(['slug' => 'text'], ['name' => 'Text', 'source_type' => 'static', 'status' => 'published']);
        BlockType::query()->updateOrCreate(['slug' => 'navigation-auto'], ['name' => 'Navigation Auto', 'source_type' => 'navigation', 'status' => 'published']);
        BlockType::query()->updateOrCreate(['slug' => 'rich-text'], ['name' => 'Rich Text', 'source_type' => 'static', 'status' => 'published']);

        $legacyDatabasePath = storage_path('framework/testing/legacy-fklavye-source.sqlite');

        if (file_exists($legacyDatabasePath)) {
            unlink($legacyDatabasePath);
        }

        touch($legacyDatabasePath);

        config()->set('database.connections.legacy_fklavye_source', [
            'driver' => 'sqlite',
            'database' => $legacyDatabasePath,
            'prefix' => '',
            'foreign_key_constraints' => false,
        ]);
        DB::purge('legacy_fklavye_source');

        $schema = Schema::connection('legacy_fklavye_source');
        $schema->create('pages', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->string('slug')->nullable();
            $table->string('status')->default('published');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('blocks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->nullable();
            $table->foreignId('block_type_id')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->text('content')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('asset_id')->nullable();
            $table->text('settings')->nullable();
            $table->string('status')->default('published');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->string('location');
            $table->foreignId('page_id')->nullable();
            $table->string('title')->nullable();
            $table->string('url')->nullable();
            $table->string('target')->nullable();
            $table->integer('sort_order')->default(0);
            $table->string('status')->default('published');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('assets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('asset_folder_id')->nullable();
            $table->string('disk')->nullable();
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->string('original_name')->nullable();
            $table->string('extension')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('kind')->nullable();
            $table->string('visibility')->nullable();
            $table->string('title')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('caption')->nullable();
            $table->text('description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('asset_folders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable();
            $table->string('name')->nullable();
            $table->string('slug')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
        $schema->create('block_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug');
        });

        $legacy = DB::connection('legacy_fklavye_source');
        $legacy->table('pages')->insert([
            'id' => 1,
            'title' => 'Legacy About',
            'slug' => 'legacy-about',
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $legacy->table('navigation_items')->insert([
            'id' => 1,
            'location' => 'primary',
            'page_id' => 1,
            'title' => 'Legacy About',
            'url' => null,
            'target' => null,
            'sort_order' => 1,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            app(LegacyFklavyeSandboxImporter::class)->import([
                'host' => 'localhost',
                'port' => 3306,
                'database' => $legacyDatabasePath,
                'username' => '',
                'password' => '',
            ]);
        } finally {
            DB::purge('legacy_fklavye_source');

            if (file_exists($legacyDatabasePath)) {
                unlink($legacyDatabasePath);
            }
        }

        $primarySite = Site::query()->where('is_primary', true)->firstOrFail();
        $defaultLocaleId = Locale::query()->where('is_default', true)->value('id');
        $page = Page::query()->where('site_id', $primarySite->id)->firstOrFail();

        $this->assertDatabaseHas('page_translations', [
            'page_id' => $page->id,
            'site_id' => $primarySite->id,
            'locale_id' => $defaultLocaleId,
            'name' => 'Legacy About',
            'slug' => 'legacy-about',
        ]);
        $this->assertDatabaseHas('navigation_items', [
            'site_id' => $primarySite->id,
            'page_id' => $page->id,
            'title' => 'Legacy About',
        ]);
    }
}
