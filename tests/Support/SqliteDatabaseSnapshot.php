<?php

namespace WebBlocks\Cms\Tests\Support;

use LogicException;
use PDO;
use Throwable;

/** A process-local fixture copied into a new connection, never a shared database. */
final readonly class SqliteDatabaseSnapshot
{
  private function __construct(private array $statements) {}

  public static function capture(PDO $database): self
  {
    $schema = $database->query("SELECT type, name, sql FROM sqlite_master WHERE sql IS NOT NULL AND name NOT GLOB 'sqlite_*' ORDER BY rowid")->fetchAll(PDO::FETCH_ASSOC);
    $tables = [];
    $data = [];
    $other = [];
    foreach ($schema as $object) {
      if ($object['type'] !== 'table') {
        $other[] = $object['sql'].';';

        continue;
      }
      $tables[] = $object['sql'].';';
      $table = self::identifier($object['name']);
      $columns = array_column(array_filter(
        $database->query('PRAGMA table_xinfo('.$table.')')->fetchAll(PDO::FETCH_ASSOC),
        static fn (array $column): bool => $column['hidden'] === 0,
      ), 'name');
      $columns = array_map(self::identifier(...), $columns);
      // Hex preserves embedded NULs, binary bytes and text/blob storage classes.
      $values = array_map(static fn (string $column): string => "CASE typeof($column) WHEN 'text' THEN 'CAST(X''' || hex($column) || ''' AS TEXT)' WHEN 'blob' THEN 'X''' || hex($column) || '''' ELSE quote($column) END", $columns);
      $prefix = $database->quote('INSERT INTO '.$table.' ('.implode(', ', $columns).') VALUES (');
      $query = 'SELECT '.$prefix.' || '.implode(" || ', ' || ", $values)." || ');' FROM ".$table;
      array_push($data, ...$database->query($query)->fetchAll(PDO::FETCH_COLUMN));
    }
    if ($database->query("SELECT count(*) FROM sqlite_master WHERE name = 'sqlite_sequence'")->fetchColumn()) {
      $data[] = 'DELETE FROM sqlite_sequence;';
      foreach ($database->query('SELECT name, seq FROM sqlite_sequence')->fetchAll(PDO::FETCH_ASSOC) as $sequence) {
        $data[] = 'INSERT INTO sqlite_sequence VALUES ('.$database->quote($sequence['name']).', '.(int) $sequence['seq'].');';
      }
    }

    return new self([...$tables, ...$data, ...$other]);
  }

  public function restore(PDO $database): void
  {
    if ($database->inTransaction()) {
      throw new LogicException('Restore a test fixture outside an existing transaction.');
    }
    $foreignKeys = (int) $database->query('PRAGMA foreign_keys')->fetchColumn();
    $database->exec('PRAGMA foreign_keys = OFF');
    try {
      $database->beginTransaction();
      $database->exec(implode("\n", $this->statements));
      $database->commit();
    } catch (Throwable $exception) {
      if ($database->inTransaction()) {
        $database->rollBack();
      }
      throw $exception;
    } finally {
      $database->exec('PRAGMA foreign_keys = '.$foreignKeys);
    }
  }

  private static function identifier(string $name): string
  {
    return '"'.str_replace('"', '""', $name).'"';
  }
}
