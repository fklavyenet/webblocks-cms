<?php

namespace WebBlocks\Cms\Tests\Unit;

use PDO;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Tests\Support\FreshDatabaseSnapshots;
use WebBlocks\Cms\Tests\Support\SqliteDatabaseSnapshot;

class FreshDatabaseSnapshotsTest extends TestCase
{
  public function test_shared_snapshots_keep_rows_schema_sequences_and_transactions_isolated(): void
  {
    $source = new PDO('sqlite::memory:');
    $source->exec('CREATE TABLE records (id INTEGER PRIMARY KEY AUTOINCREMENT, label TEXT); INSERT INTO records VALUES (5, "seed")');
    $key = FreshDatabaseSnapshots::key(['fixture' => true], ['provider' => true], 'source-a');
    FreshDatabaseSnapshots::put($key, SqliteDatabaseSnapshot::capture($source));
    $first = new PDO('sqlite::memory:');
    FreshDatabaseSnapshots::get($key)->restore($first);
    $first->exec('UPDATE records SET label = "changed"; ALTER TABLE records ADD COLUMN leaked TEXT');
    $second = new PDO('sqlite::memory:');
    FreshDatabaseSnapshots::get($key)->restore($second);
    $this->assertSame('seed', $second->query('SELECT label FROM records')->fetchColumn());
    $this->assertCount(2, $second->query('PRAGMA table_info(records)')->fetchAll());
    $second->exec('INSERT INTO records (label) VALUES ("next")');
    $this->assertSame('6', $second->lastInsertId());
    $this->assertFalse($second->inTransaction());
    $this->assertSame('seed', $source->query('SELECT label FROM records')->fetchColumn());
    FreshDatabaseSnapshots::forget($key);
  }

  public function test_configuration_provider_source_and_custom_boundary_change_identity(): void
  {
    $key = FreshDatabaseSnapshots::key(['mode' => 1], ['A' => true], 'source', null);
    $this->assertSame($key, FreshDatabaseSnapshots::key(['mode' => 1], ['A' => true], 'source'));
    foreach ([[['mode' => 2], ['A' => true], 'source', null], [['mode' => 1], ['B' => true], 'source', null],
      [['mode' => 1], ['A' => true], 'changed', null], [['mode' => 1], ['A' => true], 'source', 'custom class']] as $arguments) {
      $this->assertNotSame($key, FreshDatabaseSnapshots::key(...$arguments));
    }
  }

  public function test_pool_is_bounded_and_recently_used_entries_survive_eviction(): void
  {
    $snapshot = SqliteDatabaseSnapshot::capture(new PDO('sqlite::memory:'));
    for ($i = 0; $i < 32; $i++) {
      FreshDatabaseSnapshots::put('eviction-'.$i, $snapshot);
    }
    $this->assertSame($snapshot, FreshDatabaseSnapshots::get('eviction-0'));
    FreshDatabaseSnapshots::put('eviction-32', $snapshot);
    $this->assertNull(FreshDatabaseSnapshots::get('eviction-1'));
    $this->assertSame($snapshot, FreshDatabaseSnapshots::get('eviction-0'));
    for ($i = 0; $i <= 32; $i++) {
      FreshDatabaseSnapshots::forget('eviction-'.$i);
    }
  }
}
