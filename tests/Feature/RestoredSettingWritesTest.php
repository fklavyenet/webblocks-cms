<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageRevisionManager;
use WebBlocks\Cms\Support\Relational\PersistentDocuments;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Revisions\RevisionContentUpdates;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RestoredSettingWritesTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(RestoredSettingsTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
  }

  public static function counts(): array
  {
    return [[6], [101]];
  }

  #[Test]
  #[DataProvider('counts')]
  public function new_restore_owners_group_auxiliary_writes_and_preserve_every_primary_event(int $count): void
  {
    [$page, $type] = $this->fixture();
    $watch = false;
    $writes = [];
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      Block::{$event}(function () use (&$events, $event): void {
        $events[] = $event;
      });
    }
    DB::listen(function ($query) use (&$watch, &$writes): void {
      if ($watch && preg_match('/^insert into ["`](wbcms_[a-z_]+)["`]/i', $query->sql, $match)) {
        $writes[$match[1]] = ($writes[$match[1]] ?? 0) + 1;
      }
    });
    $ids = [];
    $settings = ['alignment' => 'left', 'plugin' => [false, null, 1, '1']];
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $count, $settings, &$ids, &$watch): void {
      $watch = true;
      for ($i = 0; $i < $count; $i++) {
        $ids[] = $this->create($page, $type, $settings)->id;
      }
    });
    $watch = false;
    $expectedEvents = [];
    for ($i = 0; $i < $count; $i++) {
      array_push($expectedEvents, 'saving', 'creating', 'created', 'saved');
    }
    $this->assertSame($expectedEvents, $events);
    $groups = (int) ceil($count / 50);
    foreach (['block_application_references', 'setting_documents', 'block_appearance_settings', 'setting_nodes'] as $table) {
      $this->assertSame($groups, $writes['wbcms_'.$table] ?? 0, $table);
    }
    $this->assertSame($count, $writes['wbcms_blocks']);
    foreach (Block::whereKey($ids)->get() as $block) {
      $this->assertSame($settings, $block->decodedSettings());
      $this->assertSame(1, DB::table('wbcms_setting_documents')->where('block_id', $block->id)->count());
      $this->assertSame(1, DB::table('wbcms_block_application_references')->where('block_id', $block->id)->count());
    }
  }

  #[Test]
  public function saved_observers_can_read_and_amend_current_sql_settings_and_application_references(): void
  {
    [$page, $type] = $this->fixture();
    $first = $this->application('first');
    $second = $this->application('second');
    $seen = 0;
    Block::created(function (Block $block): void {
      $this->assertFalse(DB::table('wbcms_setting_documents')->where('block_id', $block->id)->exists());
    });
    Block::saved(function (Block $block) use ($first, $second, &$seen): void {
      $id = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('id');
      $this->assertNotNull($id);
      $this->assertSame('left', DB::table('wbcms_block_appearance_settings')->where('document_id', $id)->value('alignment'));
      $this->assertSame($first->id, DB::table('wbcms_block_application_references')->where('block_id', $block->id)->value('resolved_application_id'));
      DB::table('wbcms_block_appearance_settings')->where('document_id', $id)->update(['alignment' => 'right']);
      DB::table('wbcms_block_application_references')->where('block_id', $block->id)->update(['application_handle' => $second->handle, 'resolved_application_id' => $second->id]);
      $seen++;
    });
    $ids = [];
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $first, &$ids): void {
      for ($i = 0; $i < 3; $i++) {
        $ids[] = $this->create($page, $type, ['alignment' => 'left', 'application_handle' => $first->handle])->id;
      }
    });
    $this->assertSame(3, $seen);
    foreach (Block::whereKey($ids)->get() as $block) {
      $this->assertSame(['alignment' => 'right', 'application_handle' => $second->handle], $block->decodedSettings());
    }
  }

  #[Test]
  public function resolved_application_references_are_grouped_without_sharing_owner_values(): void
  {
    [$page, $type] = $this->fixture();
    $applications = [$this->application('first'), $this->application('second')];
    $watch = false;
    $inserts = 0;
    DB::listen(function ($query) use (&$watch, &$inserts): void {
      if ($watch && str_starts_with($query->sql, 'insert into "wbcms_block_application_references"')) {
        $inserts++;
      }
    });
    $expected = [];
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $applications, &$watch, &$expected): void {
      $watch = true;
      for ($i = 0; $i < 6; $i++) {
        $application = $applications[$i % 2];
        $block = $this->create($page, $type, ['application_handle' => $application->handle]);
        $expected[$block->id] = $application;
      }
    });
    $watch = false;
    $this->assertSame(1, $inserts);
    foreach ($expected as $id => $application) {
      $row = DB::table('wbcms_block_application_references')->where('block_id', $id)->sole();
      $this->assertSame($application->id, $row->resolved_application_id);
      $this->assertSame(['application_handle' => $application->handle], Block::findOrFail($id)->decodedSettings());
    }
  }

  #[Test]
  public function creation_observer_documents_are_replaced_without_stale_rows_or_duplicate_headers(): void
  {
    [$page, $type] = $this->fixture();
    $application = $this->application('kept');
    Block::created(function (Block $block): void {
      app(StructuredSettings::class)->write($block, 'settings', '{"alignment":"right","obsolete":[1,2,3]}');
      DB::table('wbcms_block_application_references')->insert(['block_id' => $block->id, 'document_kind' => 'null']);
    });
    $block = null;
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $application, &$block): void {
      $block = $this->create($page, $type, ['alignment' => 'left', 'application_handle' => $application->handle]);
    });
    $this->assertSame(['alignment' => 'left', 'application_handle' => $application->handle], $block->fresh()->decodedSettings());
    $id = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->sole()->id;
    $this->assertFalse(DB::table('wbcms_setting_nodes')->where('document_id', $id)->where('key_text', 'obsolete')->exists());
    $this->assertSame($application->id, DB::table('wbcms_block_application_references')->where('block_id', $block->id)->sole()->resolved_application_id);
  }

  #[Test]
  public function observers_reading_settings_through_an_unknown_view_still_see_current_sql(): void
  {
    [$page, $type] = $this->fixture();
    DB::statement('create view synthetic_restore_settings as select d.block_id, a.alignment from wbcms_setting_documents d join wbcms_block_appearance_settings a on a.document_id = d.id');
    $seen = 0;
    Block::saved(function (Block $block) use (&$seen): void {
      $this->assertSame('left', DB::table('synthetic_restore_settings')->where('block_id', $block->id)->value('alignment'));
      $seen++;
    });
    app(RevisionContentUpdates::class)->during(fn () => $this->create($page, $type, ['alignment' => 'left']));
    $this->assertSame(1, $seen);
  }

  #[Test]
  public function observers_using_a_custom_sql_function_see_current_settings_even_from_a_known_table(): void
  {
    [$page, $type] = $this->fixture();
    DB::connection()->getPdo()->sqliteCreateFunction('synthetic_settings_value', fn ($id) => DB::table('wbcms_setting_documents as d')->join('wbcms_block_appearance_settings as a', 'a.document_id', '=', 'd.id')->where('d.block_id', $id)->value('a.alignment'), 1);
    $seen = 0;
    Block::saved(function (Block $block) use (&$seen): void {
      $value = DB::table('wbcms_blocks')->where('id', $block->id)->selectRaw('synthetic_settings_value(id) as alignment')->value('alignment');
      $this->assertSame('left', $value);
      $seen++;
    });
    app(RevisionContentUpdates::class)->during(fn () => $this->create($page, $type, ['alignment' => 'left']));
    $this->assertSame(1, $seen);
  }

  #[Test]
  public function an_earlier_buffered_observer_write_is_replaced_through_the_native_existing_owner_path(): void
  {
    [$page, $type] = $this->fixture();
    Block::created(function (Block $block): void {
      $writer = app(StructuredSettings::class);
      $writer->write($block, 'settings', '{"alignment":"right","obsolete":[1,2,3]}', $writer->writeVersion());
    });
    $block = null;
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, &$block): void {
      $block = $this->create($page, $type, ['alignment' => 'left']);
    });
    $this->assertSame(['alignment' => 'left'], $block->fresh()->decodedSettings());
    $id = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->sole()->id;
    $this->assertFalse(DB::table('wbcms_setting_nodes')->where('document_id', $id)->where('key_text', 'obsolete')->exists());
  }

  #[Test]
  public function content_template_references_flush_before_deletion_and_preserve_historical_values(): void
  {
    [$page, $type] = $this->fixture();
    $template = PublicSearchIndexer::coalescing(fn () => $this->create($page, $type, null));
    $settings = ['alignment' => 'left', 'content_collection' => ['source' => 'synthetic::records', 'template_block_id' => $template->id, 'limit' => 6, 'source_settings' => ['mixed' => [false, null, 1, '1']]]];
    $block = null;
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $template, $settings, &$block): void {
      $block = $this->create($page, $type, $settings);
      DB::table('wbcms_blocks')->where('id', $template->id)->delete();
    });
    $this->assertSame($settings, $block->fresh()->decodedSettings());
    $id = DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('id');
    $row = DB::table('wbcms_block_content_collections')->where('document_id', $id)->sole();
    $this->assertSame($template->id, $row->template_block_id);
    $this->assertNull($row->resolved_template_id);
  }

  #[Test]
  public function owner_identity_mapping_does_not_depend_on_insert_order_or_contiguous_ids(): void
  {
    [$page, $type] = $this->fixture();
    app(RevisionContentUpdates::class)->during(function () use ($page, $type): void {
      foreach ([90, 12, 70] as $id) {
        $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'settings' => json_encode(['alignment' => 'left', 'plugin' => ['owner' => $id]]), 'meta' => null]);
        $block->forceFill(['id' => $id]);
        $this->assertTrue(app(PersistentDocuments::class)->createRestored($block));
      }
    });
    foreach ([90, 12, 70] as $id) {
      $this->assertSame($id, Block::findOrFail($id)->decodedSettings()['plugin']['owner']);
    }
  }

  #[Test]
  public function a_remaining_settings_batch_does_not_disable_the_first_bulk_read_window(): void
  {
    [$page, $type] = $this->fixture();
    $watch = false;
    $reads = 0;
    DB::listen(function ($query) use (&$watch, &$reads): void {
      if ($watch && preg_match('/^select \* from ["`]wbcms_blocks["`]/i', $query->sql)) {
        $reads++;
      }
    });
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, &$watch): void {
      $blocks = collect();
      for ($i = 0; $i < 6; $i++) {
        $blocks->push($this->create($page, $type, ['alignment' => 'left']));
      }
      $watch = true;
      app(RestoredBlockReads::class)->each(
        $blocks->map(fn ($block) => ['snapshot_id' => $block->id]),
        $blocks->mapWithKeys(fn ($block) => [$block->id => $block->id])->all(),
        fn (Block $block) => $this->assertSame(['alignment' => 'left'], $block->decodedSettings()),
      );
      $watch = false;
    });
    $this->assertSame(1, $reads);
  }

  #[Test]
  public function savepoints_preserve_earlier_settings_and_discard_rolled_back_owners(): void
  {
    [$page, $type] = $this->fixture();
    $ids = [];
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, &$ids): void {
      $ids[] = $this->create($page, $type, ['alignment' => 'left'])->id;
      try {
        DB::transaction(function () use ($page, $type): void {
          $this->create($page, $type, ['alignment' => 'right']);
          throw new RuntimeException('Synthetic rollback');
        });
      } catch (RuntimeException) {
      }
      $ids[] = $this->create($page, $type, ['alignment' => 'center'])->id;
    });
    $this->assertSame($ids, $page->blocks()->orderBy('id')->pluck('id')->all());
    $this->assertSame(['alignment' => 'left'], Block::findOrFail($ids[0])->decodedSettings());
    $this->assertSame(['alignment' => 'center'], Block::findOrFail($ids[1])->decodedSettings());
    $this->assertSame(2, DB::table('wbcms_setting_documents')->count());
    $this->assertSame(2, DB::table('wbcms_block_application_references')->count());
  }

  #[Test]
  public function caught_batch_failures_still_abort_and_do_not_poison_the_next_restore_scope(): void
  {
    [$page, $type] = $this->fixture();
    $fail = false;
    DB::connection()->beforeExecuting(function ($sql) use (&$fail): void {
      if ($fail && str_starts_with($sql, 'insert into "wbcms_setting_nodes"')) {
        $fail = false;
        throw new RuntimeException('Synthetic setting batch failure');
      }
    });
    try {
      app(RevisionContentUpdates::class)->during(function () use ($page, $type, &$fail): void {
        $this->create($page, $type, ['alignment' => 'left']);
        $fail = true;
        try {
          DB::table('wbcms_setting_documents')->count();
        } catch (RuntimeException) {
        }
      });
      $this->fail('A partial settings batch must roll back the restoration.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Synthetic setting batch failure', $exception->getMessage());
    }
    $this->assertSame(0, $page->blocks()->count());
    $this->assertSame(0, DB::table('wbcms_setting_documents')->count());
    app(RevisionContentUpdates::class)->during(fn () => $this->create($page, $type, ['alignment' => 'right']));
    $this->assertSame(['alignment' => 'right'], $page->blocks()->sole()->decodedSettings());
  }

  #[Test]
  public function large_strings_and_node_trees_keep_complete_values_with_bounded_inserts(): void
  {
    [$page, $type] = $this->fixture();
    $settings = ['alignment' => 'left', 'plugin' => ['long' => str_repeat('ü', 550000), 'nodes' => range(1, 5100), 'mixed' => [false, null, 1, '1', [], (object) []]]];
    $block = null;
    app(RevisionContentUpdates::class)->during(function () use ($page, $type, $settings, &$block): void {
      $block = $this->create($page, $type, $settings);
    });
    $this->assertJsonStringEqualsJsonString(json_encode($settings), $block->fresh()->settings);
    $this->assertSame(1, DB::table('wbcms_setting_documents')->where('block_id', $block->id)->count());
  }

  #[Test]
  public function cancelled_primary_creation_rolls_back_buffered_settings_and_full_revisions(): void
  {
    [$page, $type] = $this->fixture();
    PublicSearchIndexer::coalescing(function () use ($page, $type): void {
      $this->create($page, $type, ['alignment' => 'left']);
      $this->create($page, $type, ['alignment' => 'right']);
    });
    $manager = app(PageRevisionManager::class);
    $revision = $manager->capture($page);
    $old = $page->blocks()->pluck('id')->all();
    $cancel = true;
    $created = 0;
    Block::creating(function () use (&$cancel, &$created): bool {
      return ! $cancel || ++$created !== 2;
    });
    $class = 'App\\Models\\User';
    $actor = $this->cmsActor((new $class)->forceFill(['role' => 'super_admin', 'is_active' => true]));
    try {
      $manager->restore($page, $revision, $actor);
      $this->fail('Cancelled restoration must not retain a partial settings batch.');
    } catch (RuntimeException) {
    }
    $this->assertSame($old, $page->blocks()->pluck('id')->all());
    $this->assertSame(1, $page->revisions()->count());
    $cancel = false;
    $manager->restore($page, $revision, $actor);
    $this->assertSame(3, $page->revisions()->count());
    $this->assertSame(2, $page->blocks()->count());
    $this->assertSame(['left', 'right'], $page->blocks()->orderBy('id')->get()->map(fn ($block) => $block->decodedSettings()['alignment'])->all());
  }

  private function create(Page $page, BlockType $type, mixed $settings): Block
  {
    $block = new Block(['page_id' => $page->id, 'block_type_id' => $type->id, 'type' => 'plain_text', 'settings' => json_encode($settings), 'meta' => null]);
    $this->assertTrue(app(PersistentDocuments::class)->createRestored($block));

    return $block;
  }

  private function application(string $handle): EmbeddedApplication
  {
    return EmbeddedApplication::create(['handle' => $handle, 'name' => $handle, 'version' => '1.0.0', 'render_mode' => 'inline', 'mount_element' => 'div']);
  }

  private function fixture(): array
  {
    return PublicSearchIndexer::coalescing(function (): array {
      $site = Site::create(['name' => 'Restore settings', 'handle' => 'restore-settings']);
      $locales = collect(['en', 'tr', 'de', 'es', 'it', 'fr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $page = Page::create(['site_id' => $site->id, 'title' => 'Settings page', 'status' => 'draft']);
      $type = BlockType::create(['name' => 'Plain text', 'slug' => 'plain_text', 'source_type' => 'static']);

      return [$page, $type];
    });
  }
}

class RestoredSettingsTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
