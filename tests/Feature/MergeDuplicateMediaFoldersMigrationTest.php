<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\MediaFolder;
use WebBlocks\Cms\Support\Sites\ExportImport\ImportDataMapper;
use WebBlocks\Cms\Tests\TestCase;

class MergeDuplicateMediaFoldersMigrationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function it_merges_duplicate_folder_trees_and_preserves_media_assignments(): void
  {
    $brand = MediaFolder::query()->create(['name' => 'Branding', 'slug' => 'branding']);
    $duplicateBrand = MediaFolder::query()->create(['name' => 'branding', 'slug' => 'branding-copy']);
    $logos = MediaFolder::query()->create(['parent_id' => $brand->id, 'name' => 'Logos', 'slug' => 'logos']);
    $duplicateLogos = MediaFolder::query()->create(['parent_id' => $duplicateBrand->id, 'name' => 'LOGOS', 'slug' => 'logos-copy']);
    $media = Media::query()->create([
      'folder_id' => $duplicateLogos->id,
      'path' => 'logos/mark.svg',
      'filename' => 'mark.svg',
    ]);

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_21_120000_merge_duplicate_media_folders.php';
    $migration->up();

    $this->assertSame(2, MediaFolder::query()->count());
    $this->assertDatabaseMissing('wbcms_media_folders', ['id' => $duplicateBrand->id]);
    $this->assertDatabaseMissing('wbcms_media_folders', ['id' => $duplicateLogos->id]);
    $this->assertSame($logos->id, $media->fresh()->folder_id);
    $this->assertSame($brand->id, $logos->fresh()->parent_id);
    $this->assertSame(1, DB::table('wbcms_media')->where('folder_id', $logos->id)->count());
  }

  #[Test]
  public function importing_the_same_folder_tree_reuses_the_global_folders(): void
  {
    $reflection = new ReflectionClass(ImportDataMapper::class);
    $mapper = $reflection->newInstanceWithoutConstructor();
    $method = $reflection->getMethod('importAssetFolders');
    $payload = ['media_folders' => [
      ['id' => 10, 'parent_id' => null, 'name' => 'Branding', 'slug' => 'branding'],
      ['id' => 11, 'parent_id' => 10, 'name' => 'Logos', 'slug' => 'logos'],
    ]];
    $firstOutput = [];
    $secondOutput = [];

    $firstMap = $method->invokeArgs($mapper, [$payload, &$firstOutput]);
    $secondMap = $method->invokeArgs($mapper, [$payload, &$secondOutput]);

    $this->assertSame(2, MediaFolder::query()->count());
    $this->assertSame($firstMap, $secondMap);
  }
}
