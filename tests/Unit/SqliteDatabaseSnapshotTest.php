<?php

namespace WebBlocks\Cms\Tests\Unit;

use LogicException;
use PDO;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Tests\Support\SqliteDatabaseSnapshot;

class SqliteDatabaseSnapshotTest extends TestCase
{
  public function test_roundtrip_preserves_schema_values_sequences_and_trigger_history(): void
  {
    $source = $this->database();
    $source->exec("CREATE TABLE parents (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL);
      CREATE TABLE children (id INTEGER PRIMARY KEY, parent_id INTEGER REFERENCES parents(id) ON DELETE CASCADE, title TEXT NOT NULL UNIQUE CHECK(length(title) > 0), payload BLOB, body TEXT, amount REAL, generated TEXT GENERATED ALWAYS AS (upper(title)) STORED);
      CREATE TABLE audit (message TEXT);
      CREATE INDEX child_parent ON children(parent_id);
      CREATE VIEW titles AS SELECT title FROM children;
      CREATE TRIGGER child_created AFTER INSERT ON children BEGIN INSERT INTO audit VALUES (new.title); END;
      INSERT INTO parents VALUES (3, 'Keep');
      INSERT INTO parents VALUES (10, 'Deleted');
      DELETE FROM parents WHERE id = 10;");
    $insert = $source->prepare('INSERT INTO children (id, parent_id, title, payload, body, amount) VALUES (1, 3, ?, ?, ?, 1.25)');
    $insert->bindValue(1, "İçerik 'quoted'");
    $insert->bindValue(2, "\x00\xff\x01", PDO::PARAM_LOB);
    $insert->bindValue(3, "before\x00after");
    $insert->execute();
    $target = $this->database();

    SqliteDatabaseSnapshot::capture($source)->restore($target);

    $schema = 'SELECT type, name, sql FROM sqlite_master ORDER BY name';
    $this->assertSame($source->query($schema)->fetchAll(), $target->query($schema)->fetchAll());
    foreach (['parents', 'children', 'audit', 'titles', 'sqlite_sequence'] as $table) {
      $this->assertSame($source->query('SELECT * FROM '.$table)->fetchAll(), $target->query('SELECT * FROM '.$table)->fetchAll());
    }
    $this->assertSame(['blob', 'text', 'real'], $target->query('SELECT typeof(payload), typeof(body), typeof(amount) FROM children')->fetch(PDO::FETCH_NUM));
    $this->assertSame([], $target->query('PRAGMA foreign_key_check')->fetchAll());
    $target->exec("INSERT INTO parents (name) VALUES ('Next')");
    $this->assertSame('11', $target->lastInsertId());
    $target->exec('DELETE FROM parents WHERE id = 3');
    $this->assertSame(0, $target->query('SELECT count(*) FROM children')->fetchColumn());
  }

  public function test_restored_connections_cannot_mutate_the_source_or_next_copy(): void
  {
    $source = $this->database();
    $source->exec('CREATE TABLE records (id INTEGER PRIMARY KEY, value TEXT); INSERT INTO records VALUES (1, NULL)');
    $snapshot = SqliteDatabaseSnapshot::capture($source);
    $first = $this->database();
    $snapshot->restore($first);
    $first->exec("UPDATE records SET value = 'changed'; ALTER TABLE records ADD COLUMN leaked TEXT");
    $second = $this->database();
    $snapshot->restore($second);

    $this->assertSame($source->query('SELECT * FROM records')->fetchAll(), $second->query('SELECT * FROM records')->fetchAll());
    $this->assertCount(2, $second->query('PRAGMA table_info(records)')->fetchAll());
    $this->assertFalse($second->inTransaction());
    $second->beginTransaction();
    $second->exec('DELETE FROM records');
    $second->rollBack();
    $this->assertSame(1, $second->query('SELECT count(*) FROM records')->fetchColumn());
  }

  public static function foreignKeyModes(): array
  {
    return ['enabled' => [1], 'disabled' => [0]];
  }

  #[DataProvider('foreignKeyModes')]
  public function test_restore_preserves_foreign_key_mode_and_constraints(int $enabled): void
  {
    $source = $this->database();
    $source->exec('CREATE TABLE parents (id INTEGER PRIMARY KEY); CREATE TABLE children (parent_id INTEGER REFERENCES parents(id))');
    $target = $this->database();
    $target->exec('PRAGMA foreign_keys = '.$enabled);
    SqliteDatabaseSnapshot::capture($source)->restore($target);
    $this->assertSame($enabled, $target->query('PRAGMA foreign_keys')->fetchColumn());
    if ($enabled) {
      $this->expectException(PDOException::class);
    }
    $target->exec('INSERT INTO children VALUES (999)');
  }

  public function test_failed_restore_rolls_back_partial_schema_and_restores_foreign_keys(): void
  {
    $source = $this->database();
    $source->exec('CREATE TABLE first_table (id INTEGER); CREATE TABLE collision (id INTEGER)');
    $target = $this->database();
    $target->exec('CREATE TABLE collision (original TEXT)');
    try {
      SqliteDatabaseSnapshot::capture($source)->restore($target);
      $this->fail('A conflicting schema must reject restoration.');
    } catch (PDOException) {
      $this->assertFalse($target->inTransaction());
      $this->assertSame(1, $target->query('PRAGMA foreign_keys')->fetchColumn());
      $this->assertSame(0, $target->query("SELECT count(*) FROM sqlite_master WHERE name = 'first_table'")->fetchColumn());
      $this->assertSame('original', $target->query('PRAGMA table_info(collision)')->fetch(PDO::FETCH_ASSOC)['name']);
    }
  }

  public function test_restore_does_not_end_an_existing_transaction(): void
  {
    $source = $this->database();
    $source->exec('CREATE TABLE records (id INTEGER)');
    $target = $this->database();
    $target->beginTransaction();
    try {
      SqliteDatabaseSnapshot::capture($source)->restore($target);
      $this->fail('An existing transaction must be left to its caller.');
    } catch (LogicException) {
      $this->assertTrue($target->inTransaction());
      $this->assertSame(1, $target->query('PRAGMA foreign_keys')->fetchColumn());
      $target->rollBack();
    }
  }

  private function database(): PDO
  {
    $database = new PDO('sqlite::memory:');
    $database->exec('PRAGMA foreign_keys = ON');

    return $database;
  }
}
