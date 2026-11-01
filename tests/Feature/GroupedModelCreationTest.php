<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

class GroupedModelCreationTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function fixture(): array
  {
    $locale = Locale::create(['code' => 'en', 'name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::create(['name' => 'Synthetic atomic creation', 'handle' => 'atomic-creation']);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Synthetic page', 'status' => 'draft']);

    return [$page, $locale];
  }

  public function test_grouped_creation_runs_model_events_and_keeps_typed_values_in_the_enclosing_transaction(): void
  {
    [$page, $locale] = $this->fixture();
    $events = [];
    Block::created(function () use (&$events): void {
    $events[] = ['created', DB::transactionLevel()];
    });
    Block::saved(function () use (&$events): void {
    $events[] = ['saved', DB::transactionLevel()];
    });
    DB::transaction(function () use ($page, $locale): void {
      $block = (new Block(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => '{"kind":"fixture"}', 'status' => 'draft']))->setRelation('page', $page);
      $this->assertTrue($block->createInTransaction());
      $translation = (new BlockTextTranslation(['block_id' => $block->id, 'locale_id' => $locale->id, 'content' => 'Kept content', 'meta' => '{"nested":{"value":null}}']))->setRelation('block', $block);
      $this->assertTrue($translation->createInTransaction());
      $this->assertSame('{"nested":{"value":null}}', $translation->fresh()->meta);
      $this->assertSame('{"kind":"fixture"}', $block->fresh()->meta);
    });
    $this->assertSame([['created', 1], ['saved', 1]], $events);
  }

  public function test_a_group_failure_rolls_back_rows_and_relational_documents_together(): void
  {
    [$page] = $this->fixture();
    $before = DB::table('wbcms_persistent_documents')->count();
    try {
      DB::transaction(function () use ($page): void {
        $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'meta' => '{"copy":true}', 'status' => 'draft']);
        $block->createInTransaction();
        throw new \RuntimeException('Synthetic grouped failure');
      });
    } catch (\RuntimeException) {
      $this->assertSame(0, $page->blocks()->count());
      $this->assertSame($before, DB::table('wbcms_persistent_documents')->count());
    }
    $block = new Block(['page_id' => $page->id, 'type' => 'plain_text', 'status' => 'draft']);
    $this->assertTrue($block->save());
  }

  public function test_grouped_creation_refuses_to_write_without_an_enclosing_transaction(): void
  {
    [$page] = $this->fixture();
    $block = new Block(['page_id' => $page->id, 'type' => 'plain_text']);
    $this->expectException(\LogicException::class);
    $block->createInTransaction();
  }
}
