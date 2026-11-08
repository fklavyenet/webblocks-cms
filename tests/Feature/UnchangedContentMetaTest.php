<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Tests\TestCase;

class UnchangedContentMetaTest extends TestCase
{
  private bool $recording = false;

  private array $writes = [];

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    DB::listen(function ($query): void {
      if ($this->recording && preg_match('/^(?:insert into|update|delete from) ["`]wbcms_(?:persistent_|import_)/i', $query->sql)) {
        $this->writes[] = $query->sql;
      }
    });
  }

  #[Test]
  public function ordinary_block_and_translation_saves_keep_events_without_rewriting_meta(): void
  {
    [$block, $translation] = $this->fixtures();
    $events = [];
    foreach ([$block, $translation] as $model) {
      $class = $model::class;
      foreach (['saving', 'updating', 'updated', 'saved'] as $event) {
        $class::{$event}(function ($saved) use ($model, $event, &$events): void {
          if ($saved->id === $model->id) {
            $events[$model::class][] = $event;
          }
        });
      }
      $before = $model->fresh()->meta;
      $this->assertSame([], $this->recordWrites(fn () => $model->update(['title' => 'Changed title'])));
      $this->assertSame(['saving', 'updating', 'updated', 'saved'], $events[$class]);
      $this->assertSame($before, $model->fresh()->meta);
      $this->assertFalse($model->wasChanged('meta'));
      $this->assertTrue($model->wasChanged('title'));
    }
    $parent = Block::create(['page_id' => $block->page_id, 'type' => 'section']);
    $this->assertSame([], $this->recordWrites(fn () => $block->update(['parent_id' => $parent->id, 'sort_order' => 5])));
    $this->assertSame($parent->id, $block->fresh()->parent_id);
  }

  #[Test]
  public function null_raw_text_json_scalars_containers_and_value_types_remain_distinct(): void
  {
    [$block] = $this->fixtures();
    $values = [null, '', 'null', 'false', '0', '"0"', 'malformed', '{}', '[]', '{"x":false}', '{"x":0}', '{"x":"0"}', '{"x":null}', '{"x":[]}', '{"x":{}}', '[false,null,1,"1"]', false, 0, 1.0, ['x' => [false, null, 1, '1']]];
    foreach ($values as $value) {
      $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['meta' => $value])));
      $before = $block->fresh()->meta;
      $this->assertSame([], $this->recordWrites(fn () => $block->update(['meta' => $value, 'title' => 'Same value'])));
      $this->assertSame(serialize($before), serialize($block->fresh()->meta));
    }
  }

  #[Test]
  public function stale_instances_observer_changes_and_missing_documents_follow_current_sql(): void
  {
    [$block, $translation] = $this->fixtures();
    foreach ([$block, $translation] as $model) {
      $stale = $model->fresh();
      $model->fresh()->update(['meta' => '{"source":"latest"}']);
      $this->assertSame([], $this->recordWrites(fn () => $stale->update(['title' => 'Unrelated'])));
      $this->assertJsonStringEqualsJsonString('{"source":"latest"}', $model->fresh()->meta);
      $stale->meta = '{"source":"latest"}';
      $model->fresh()->update(['meta' => '{"source":"newer"}']);
      $this->assertNotEmpty($this->recordWrites(fn () => $stale->save()));
      $this->assertJsonStringEqualsJsonString('{"source":"latest"}', $model->fresh()->meta);
      $owner = $model instanceof Block ? 'block_id' : 'block_text_translation_id';
      DB::table('wbcms_persistent_documents')->where($owner, $model->id)->where('attribute', 'meta')->delete();
      $this->assertNotEmpty($this->recordWrites(fn () => $model->update(['meta' => null])));
      $this->assertDatabaseHas('wbcms_persistent_documents', [$owner => $model->id, 'attribute' => 'meta']);
    }
    Block::updating(function (Block $model): void {
      $model->meta = '{"source":"observer"}';
    });
    $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['title' => 'Observer'])));
    $this->assertJsonStringEqualsJsonString('{"source":"observer"}', $block->fresh()->meta);
  }

  #[Test]
  public function raw_metadata_updates_are_not_hidden_by_the_models_cached_value(): void
  {
    [$block] = $this->fixtures();
    Block::updating(function (Block $model): void {
      $id = DB::table('wbcms_persistent_documents')->where('block_id', $model->id)->where('attribute', 'meta')->value('id');
      DB::table('wbcms_persistent_metadata')->where('document_id', $id)->update(['source' => 'raw-change']);
    });
    $block->update(['title' => 'Raw observer']);
    $this->assertJsonStringEqualsJsonString('{"source":"original","items":[false,null,1,"1"]}', $block->fresh()->meta);
  }

  #[Test]
  public function vetoes_rollbacks_and_complete_independent_snapshots_are_preserved(): void
  {
    [$block] = $this->fixtures();
    $page = $block->page;
    $manager = app(PageRevisionManager::class);
    $before = $manager->capture($page);
    $veto = true;
    $fail = false;
    Block::updating(function () use (&$veto): bool {
    return ! $veto;
    });
    Block::saved(function () use (&$fail): void {
      if ($fail) {
      throw new RuntimeException('Synthetic saved failure');
      }
    });
    $this->assertFalse($block->update(['meta' => '{"source":"cancelled"}']));
    $this->assertJsonStringEqualsJsonString('{"source":"original","items":[false,null,1,"1"]}', $block->fresh()->meta);
    $veto = false;
    $fail = true;
    try {
      $block->save();
      $this->fail('The saved observer must run.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic saved failure', $exception->getMessage());
    }
    $this->assertJsonStringEqualsJsonString('{"source":"original","items":[false,null,1,"1"]}', $block->fresh()->meta);
    $fail = false;
    $block->update(['meta' => '{"source":"changed"}']);
    $after = $manager->capture($page->fresh());
    $this->assertNotSame($before->id, $after->id);
    $this->assertJsonStringEqualsJsonString('{"source":"original","items":[false,null,1,"1"]}', $before->fresh()->snapshot['blocks'][0]['meta']);
    $before->delete();
    $this->assertJsonStringEqualsJsonString('{"source":"changed"}', $after->fresh()->snapshot['blocks'][0]['meta']);
  }

  private function recordWrites(callable $work): array
  {
    $this->recording = true;
    $this->writes = [];
    try {
      $work();

      return $this->writes;
    } finally {
      $this->recording = false;
    }
  }

  private function fixtures(): array
  {
    $site = Site::create(['name' => 'Synthetic metadata', 'handle' => 'metadata']);
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']);
    $block = Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => '{"source":"original","items":[false,null,1,"1"]}']);
    $translation = $block->textTranslations()->create(['locale_id' => $locale->id, 'content' => 'Synthetic', 'meta' => '{"status":"reviewed"}']);

    return [$block, $translation];
  }
}
