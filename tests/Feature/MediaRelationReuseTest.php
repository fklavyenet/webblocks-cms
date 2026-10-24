<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockMedia;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Tests\TestCase;

class MediaRelationReuseTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function both_names_reuse_loaded_media_for_blocks_and_role_rows_including_missing_media(): void
  {
    $media = Media::query()->create(['disk' => 'public', 'path' => 'fixture.svg', 'filename' => 'fixture.svg', 'kind' => 'image', 'visibility' => 'public']);
    foreach ([Block::class, BlockMedia::class] as $class) {
      foreach (['asset' => 'media', 'media' => 'asset'] as $loaded => $requested) {
        foreach ([$media, null] as $value) {
          $model = (new $class)->forceFill(['media_id' => $value?->id])->syncOriginal();
          $model->setRelation($loaded, $value);
          DB::flushQueryLog();
          DB::enableQueryLog();
          try {
            for ($index = 0; $index < 50; $index++) {
              $this->assertSame($value, $model->{$requested});
            }
            $this->assertSame([], DB::getQueryLog());
          } finally {
            DB::disableQueryLog();
          }
        }
      }
    }
  }

  #[Test]
  public function an_unsaved_media_replacement_does_not_copy_an_obsolete_alias(): void
  {
    $old = Media::query()->create(['disk' => 'public', 'path' => 'old.svg', 'filename' => 'old.svg', 'kind' => 'image', 'visibility' => 'public']);
    $new = Media::query()->create(['disk' => 'public', 'path' => 'new.svg', 'filename' => 'new.svg', 'kind' => 'image', 'visibility' => 'private']);
    foreach ([Block::class, BlockMedia::class] as $class) {
      foreach ([$old, null] as $loaded) {
        $model = (new $class)->forceFill(['media_id' => $loaded?->id])->syncOriginal();
        $model->setRelation('asset', $loaded);
        $model->media_id = $new->id;
        $this->assertSame($new->id, $model->media?->id);
        $this->assertNull($model->media?->url());
      }
    }
  }
}
