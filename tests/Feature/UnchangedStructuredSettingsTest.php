<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Relational\SettingDefinitions;
use WebBlocks\Cms\Tests\TestCase;

class UnchangedStructuredSettingsTest extends TestCase
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
    $tables = ['wbcms_setting_documents', 'wbcms_setting_nodes', ...array_map(fn ($table) => 'wbcms_'.$table, array_keys(SettingDefinitions::tables()))];
    DB::listen(function ($query) use ($tables): void {
      if ($this->recording && preg_match('/^(?:insert into|update|delete from) ["`]?([a-z_]+)/i', $query->sql, $match)
        && in_array($match[1], $tables, true)) {
        $this->writes[] = $query->sql;
      }
    });
  }

  #[Test]
  public function parent_title_and_order_updates_keep_settings_and_all_update_events(): void
  {
    $block = $this->block('{"alignment":"left","extension":[false,null,1,"1"]}');
    $parent = $this->block(null);
    $events = [];
    foreach (['saving', 'updating', 'updated', 'saved'] as $event) {
      Block::{$event}(function (Block $model) use ($block, $event, &$events): void {
        if ($model->id === $block->id) {
          $events[] = $event;
          $this->assertSame('left', $model->setting('alignment'));
        }
      });
    }
    $this->assertSame([], $this->recordWrites(fn () => $block->update(['title' => 'Moved', 'sort_order' => 5, 'parent_id' => $parent->id])));
    $this->assertSame(['saving', 'updating', 'updated', 'saved'], $events);
    $this->assertSame($parent->id, $block->fresh()->parent_id);
    $this->assertFalse($block->wasChanged('settings'));
    $this->assertTrue($block->wasChanged('title'));
    $this->assertSame([false, null, 1, '1'], $block->fresh()->setting('extension'));
    $this->assertSame([], $this->recordWrites(fn () => $block->update(['settings' => '{ "alignment": "left", "extension": [false,null,1,"1"] }'])));
    $this->assertTrue($block->wasChanged('settings'), 'Skipping SQL must retain the existing input-based change reporting.');
  }

  #[Test]
  public function saving_observers_can_change_settings_and_raw_updates_are_not_compared_to_stale_cache(): void
  {
    $block = $this->block('{"alignment":"left"}');
    Block::saving(function (Block $model): void {
      if ($model->title === 'Observer change') {
        $model->settings = '{"alignment":"center","flag":false}';
      }
    });
    $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['title' => 'Observer change'])));
    $this->assertTrue($block->wasChanged('settings'));
    $this->assertSame(['alignment' => 'center', 'flag' => false], $block->fresh()->decodedSettings());
    Block::updating(function (Block $model): void {
      if ($model->title === 'Raw observer change') {
        $id = DB::table('wbcms_setting_documents')->where('block_id', $model->id)->value('id');
        DB::table('wbcms_block_appearance_settings')->where('document_id', $id)->update(['alignment' => 'right']);
      }
    });
    $block->update(['title' => 'Raw observer change']);
    // The save's pending value must still win over a different raw observer write.
    $this->assertSame('center', $block->fresh()->setting('alignment'));
  }

  #[Test]
  public function stale_unrelated_saves_keep_latest_settings_but_explicit_stale_values_still_write(): void
  {
    $block = $this->block('{"alignment":"left"}');
    $stale = $block->fresh();
    $block->fresh()->update(['settings' => '{"alignment":"right"}']);
    $this->assertSame([], $this->recordWrites(fn () => $stale->update(['title' => 'Unrelated'])));
    $this->assertSame('right', $block->fresh()->setting('alignment'));
    $this->assertTrue($stale->wasChanged('settings'), 'Refreshing a stale original keeps the existing change reporting.');
    $stale->settings = '{"alignment":"right"}';
    $block->fresh()->update(['settings' => '{"alignment":"center"}']);
    $this->assertNotEmpty($this->recordWrites(fn () => $stale->save()));
    $this->assertSame('right', $block->fresh()->setting('alignment'));
  }

  #[Test]
  public function null_empty_object_list_scalar_types_and_raw_values_remain_distinct(): void
  {
    $values = [null, '[]', '{}', 'false', '0', '"0"', '{"x":false}', '{"x":0}', '{"x":"0"}', '{"x":null}', '{"x":[]}', '{"x":{}}', 'malformed', ['x' => [false, null, 1, '1']]];
    foreach ($values as $value) {
      $block = $this->block($value);
      $before = $block->fresh()->settings;
      $this->assertSame([], $this->recordWrites(fn () => $block->update(['settings' => $value, 'title' => 'Same'])));
      $this->assertSame($before, $block->fresh()->settings);
      $this->assertFalse($block->wasChanged('settings'));
    }
    $block = $this->block(null);
    foreach (array_slice($values, 1) as $value) {
      $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['settings' => $value])));
    }
  }

  #[Test]
  public function missing_documents_are_created_and_application_defaults_are_not_rewritten(): void
  {
    $block = $this->block(null);
    DB::table('wbcms_setting_documents')->where('block_id', $block->id)->delete();
    $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['settings' => null])));
    $this->assertDatabaseHas('wbcms_setting_documents', ['block_id' => $block->id, 'attribute' => 'settings']);
    $application = EmbeddedApplication::create(['handle' => 'unchanged', 'name' => 'Synthetic', 'render_mode' => 'inline', 'supports' => ['locale' => false], 'css_assets' => ['/one.css', '/one.css']]);
    $this->assertSame([], $this->recordWrites(fn () => $application->update(['name' => 'Renamed'])));
    $this->assertSame(['/one.css', '/one.css'], $application->fresh()->css_assets);
    $this->assertNotEmpty($this->recordWrites(fn () => $application->update(['supports' => ['locale' => true]])));
    $this->assertTrue($application->fresh()->supports['locale']);
  }

  #[Test]
  public function unchanged_template_settings_still_repair_an_unresolved_reference(): void
  {
    $template = $this->block(null);
    $block = $this->block(json_encode(['content_collection' => ['source' => 'synthetic::items', 'template_block_id' => $template->id]]));
    $id = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('id');
    DB::table('wbcms_block_content_collections')->where('document_id', $id)->update(['resolved_template_id' => null]);
    $this->assertNotEmpty($this->recordWrites(fn () => $block->update(['title' => 'Repair reference'])));
    $this->assertDatabaseHas('wbcms_block_content_collections', ['document_id' => $id, 'template_block_id' => $template->id, 'resolved_template_id' => $template->id]);
    $this->assertSame([], $this->recordWrites(fn () => $block->update(['title' => 'Reference intact'])));
    $template->delete();
    $block->update(['title' => 'Keep dangling template']);
    $this->assertDatabaseHas('wbcms_block_content_collections', ['document_id' => $id, 'template_block_id' => $template->id, 'resolved_template_id' => null]);
  }

  #[Test]
  public function update_vetoes_and_saved_observer_failures_keep_transactional_behavior(): void
  {
    $block = $this->block('{"alignment":"left"}');
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
    $this->assertFalse($block->update(['title' => 'Vetoed', 'settings' => '{"alignment":"right"}']));
    $this->assertSame('Original', $block->fresh()->title);
    $this->assertSame('left', $block->fresh()->setting('alignment'));
    $veto = false;
    $fail = true;
    try {
      $block->update(['title' => 'Rolled back']);
      $this->fail('The saved observer must still run.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic saved failure', $exception->getMessage());
    }
    $this->assertSame('Original', $block->fresh()->title);
    $this->assertSame('left', $block->fresh()->setting('alignment'));
    $fail = false;
    $this->assertTrue($block->update(['title' => 'Committed']));
    $this->assertSame('Committed', $block->fresh()->title);
  }

  private function recordWrites(callable $work): array
  {
    $this->writes = [];
    $this->recording = true;
    try {
      $work();

      return $this->writes;
    } finally {
      $this->recording = false;
    }
  }

  private function block(mixed $settings): Block
  {
    $site = Site::first() ?? Site::create(['name' => 'Synthetic', 'handle' => 'unchanged']);
    $page = Page::first() ?? Page::create(['site_id' => $site->id, 'title' => 'Synthetic', 'status' => 'draft']);

    return Block::create(['page_id' => $page->id, 'type' => 'plain_text', 'title' => 'Original', 'settings' => $settings]);
  }
}
