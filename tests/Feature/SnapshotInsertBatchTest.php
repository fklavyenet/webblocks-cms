<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Connection;
use Illuminate\Database\SQLiteConnection;
use Illuminate\Support\Facades\DB;
use Mockery;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageRevision;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Relational\SnapshotInsertBatch;
use WebBlocks\Cms\Support\Relational\SnapshotInsertLimits;
use WebBlocks\Cms\Support\Relational\SnapshotWriteLimitException;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Tests\TestCase;

class SnapshotInsertBatchTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function complete_snapshots_keep_shapes_events_and_independence_with_larger_inserts(): void
  {
    $site = Site::create(['name' => 'Snapshot studio', 'handle' => 'snapshot']);
    $page = Page::create(['site_id' => $site->id, 'status' => 'draft']);
    $snapshot = ['schema_version' => 2, 'captured_at' => '2026-11-07T00:00:00Z', 'page' => ['title' => 'Snapshot', 'settings' => ['extension' => [false, null, 1, '1', 1.25, [], new stdClass]]], 'blocks' => []];
    for ($i = 0; $i < 350; $i++) {
      $snapshot['blocks'][] = [
        'snapshot_id' => $i + 1, 'parent_snapshot_id' => $i === 0 ? null : 1,
        'type' => 'plain_text', 'sort_order' => $i, 'status' => 'published', 'is_system' => false,
        'settings' => '{"alignment":"left","extension":{"raw":[false,null,1,"1"]}}',
        'meta' => '{"status":"reviewed","extension":[1,"1"]}',
        'text_translations' => array_map(fn ($locale) => ['locale_id' => $locale, 'title' => '', 'content' => str_repeat('Synthetic content ', 8), 'meta' => null], range(1, 6)),
      ];
    }
    $events = [];
    foreach (['saving', 'creating', 'created', 'updating', 'updated', 'saved'] as $event) {
      PageRevision::{$event}(function () use ($event, &$events): void {
        $events[] = $event;
      });
    }
    $inserts = [];
    $watch = true;
    DB::listen(function ($query) use (&$watch, &$inserts): void {
      if ($watch && preg_match('/^insert into ["`]wbcms_(?:persistent_nodes|snapshot_)/', $query->sql)) {
        $inserts[] = count($query->bindings);
      }
    });
    $first = PageRevision::create(['page_id' => $page->id, 'site_id' => $site->id, 'snapshot' => $snapshot]);
    $second = PageRevision::create(['page_id' => $page->id, 'site_id' => $site->id, 'snapshot' => $snapshot]);
    $second->update(['label' => 'Retained version']);
    $watch = false;
    // Match the model's existing array-cast boundary before SQL persistence.
    $expected = json_decode(json_encode($snapshot, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    $this->assertSame(['saving', 'creating', 'created', 'saved', 'saving', 'creating', 'created', 'saved', 'saving', 'updating', 'updated', 'saved'], $events);
    $this->assertGreaterThan(5100, max($inserts));
    $this->assertLessThanOrEqual(16000, max($inserts));
    $actual = $second->fresh()->snapshot;
    $this->assertSame($expected['page'], $actual['page']);
    $this->assertSame($expected['blocks'][0], $actual['blocks'][0]);
    $this->assertSame(hash('sha256', serialize($expected)), hash('sha256', serialize($actual)));
    $first->delete();
    $this->assertSame(hash('sha256', serialize($expected)), hash('sha256', serialize($second->fresh()->snapshot)));
    $this->assertSame(strlen(json_encode($snapshot, JSON_THROW_ON_ERROR)), $second->fresh()->snapshot_bytes);
  }

  #[Test]
  public function sqlite_preparation_honors_a_lower_connection_limit_without_retrying_any_insert(): void
  {
    foreach ([PDO::ERRMODE_EXCEPTION, PDO::ERRMODE_SILENT] as $mode) {
      $pdo = new LimitedSnapshotPDO;
      $pdo->setAttribute(PDO::ATTR_ERRMODE, $mode);
      $connection = new SQLiteConnection($pdo, ':memory:', '', ['driver' => 'sqlite']);
      $connection->statement('create table synthetic_snapshot_rows (id integer primary key, value text)');
      $limits = SnapshotInsertLimits::forConnection($connection);
      $this->assertLessThanOrEqual(75, $limits->parameters);
      $this->assertGreaterThan(2, $limits->parameters);
      $batch = new SnapshotInsertBatch($connection, 'synthetic_snapshot_rows', ['id' => null, 'value' => null], $limits);
      for ($i = 0; $i < 200; $i++) {
        $batch->add(['id' => $i, 'value' => 'Value '.$i]);
      }
      $batch->finish();
      $this->assertSame(200, $connection->table('synthetic_snapshot_rows')->count());
      $this->assertSame(0, $pdo->rejectedInserts);
      $this->assertGreaterThan(1, $pdo->insertPrepares);
    }
  }

  #[Test]
  public function mysql_reads_the_writer_packet_limit_each_time_and_unknown_drivers_keep_small_batches(): void
  {
    $mysql = Mockery::mock(Connection::class);
    $mysql->shouldReceive('getDriverName')->andReturn('mysql');
    $mysql->shouldReceive('selectOne')->with('select @@session.max_allowed_packet as packet_bytes', [], false)->twice()->andReturn((object) ['packet_bytes' => 32768], (object) ['packet_bytes' => 8192]);
    $this->assertSame(32768, SnapshotInsertLimits::forConnection($mysql)->packetBytes);
    $this->assertSame(8192, SnapshotInsertLimits::forConnection($mysql)->packetBytes);
    $unknown = Mockery::mock(Connection::class);
    $unknown->shouldReceive('getDriverName')->andReturn('sqlsrv');
    $limits = SnapshotInsertLimits::forConnection($unknown);
    $this->assertSame(900, $limits->parameters);
    $this->assertSame(100, $limits->rows);
  }

  #[Test]
  public function packet_and_large_text_budgets_split_batches_without_truncating_values_and_fail_atomically(): void
  {
    DB::statement('create table synthetic_snapshot_rows (id integer primary key, value text)');
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
      if (str_starts_with($query->sql, 'insert into "synthetic_snapshot_rows"')) {
        $writes[] = count($query->bindings);
      }
    });
    $limits = new SnapshotInsertLimits(16000, 32768);
    $value = str_repeat("quote'\\\0", 64);
    $batch = new SnapshotInsertBatch(DB::connection(), 'synthetic_snapshot_rows', ['id' => null, 'value' => null], $limits);
    for ($i = 0; $i < 30; $i++) {
      $batch->add(['id' => $i, 'value' => $value]);
    }
    $batch->finish();
    $this->assertGreaterThan(1, count($writes));
    $this->assertSame(array_fill(0, 30, $value), DB::table('synthetic_snapshot_rows')->orderBy('id')->pluck('value')->all());
    try {
      DB::transaction(function () use ($limits): void {
        DB::table('synthetic_snapshot_rows')->delete();
        $replacement = new SnapshotInsertBatch(DB::connection(), 'synthetic_snapshot_rows', ['id' => null, 'value' => null], $limits);
        $replacement->add(['id' => 100, 'value' => 'Replacement']);
        $replacement->finish();
        $replacement->add(['id' => 101, 'value' => str_repeat('x', 32768)]);
      });
      $this->fail('A single record over the packet budget must not be truncated or partially committed.');
    } catch (SnapshotWriteLimitException $exception) {
      $this->assertSame('packet', $exception->reason);
    }
    $this->assertSame(30, DB::table('synthetic_snapshot_rows')->count());
    $this->assertSame($value, DB::table('synthetic_snapshot_rows')->where('id', 0)->value('value'));
  }

  #[Test]
  public function low_memory_headroom_reduces_wide_row_batches(): void
  {
    $columns = array_fill_keys(array_map(fn ($i) => 'field_'.$i, range(1, 100)), null);
    DB::statement('create table synthetic_snapshot_wide ('.implode(', ', array_map(fn ($column) => $column.' integer null', array_keys($columns))).')');
    $inserts = [];
    DB::listen(function ($query) use (&$inserts): void {
      if (str_starts_with($query->sql, 'insert into "synthetic_snapshot_wide"')) {
        $inserts[] = count($query->bindings);
      }
    });
    $original = ini_get('memory_limit');
    try {
      $this->assertNotFalse(ini_set('memory_limit', (string) (memory_get_usage(true) + 4194304)));
      $batch = new SnapshotInsertBatch(DB::connection(), 'synthetic_snapshot_wide', $columns, new SnapshotInsertLimits(16000));
      for ($i = 0; $i < 250; $i++) {
        $batch->add(['field_1' => $i]);
      }
      $batch->finish();
    } finally {
      ini_set('memory_limit', $original);
    }
    $this->assertLessThan(16000, max($inserts));
    $this->assertSame(range(0, 249), DB::table('synthetic_snapshot_wide')->orderBy('field_1')->pluck('field_1')->all());
  }

  #[Test]
  public function operational_limit_messages_are_present_in_all_six_languages(): void
  {
    foreach (['parameters', 'packet', 'discovery'] as $reason) {
      $english = app(CmsTranslator::class)->get('storage.snapshot_limit_'.$reason, 'en');
      $this->assertStringNotContainsString('storage.snapshot_limit_', $english);
      foreach (['tr', 'de', 'fr', 'es', 'it'] as $locale) {
        $translated = app(CmsTranslator::class)->get('storage.snapshot_limit_'.$reason, $locale);
        $this->assertNotSame($english, $translated);
        $this->assertStringNotContainsString('storage.snapshot_limit_', $translated);
      }
    }
  }
}

class LimitedSnapshotPDO extends PDO
{
  private bool $limitError = false;

  public int $rejectedInserts = 0;

  public int $insertPrepares = 0;

  public function __construct()
  {
    parent::__construct('sqlite::memory:');
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  }

  public function prepare(string $query, array $options = []): PDOStatement|false
  {
    $insert = str_starts_with($query, 'insert into');
    if (substr_count($query, '?') > 75) {
      $this->rejectedInserts += (int) $insert;
      $this->limitError = true;
      if ($this->getAttribute(PDO::ATTR_ERRMODE) === PDO::ERRMODE_SILENT) {
        return false;
      }
      throw new PDOException('too many SQL variables');
    }
    $this->limitError = false;
    $this->insertPrepares += (int) $insert;

    return parent::prepare($query, $options);
  }

  public function errorInfo(): array
  {
    return $this->limitError ? ['HY000', 1, 'too many SQL variables'] : parent::errorInfo();
  }
}
