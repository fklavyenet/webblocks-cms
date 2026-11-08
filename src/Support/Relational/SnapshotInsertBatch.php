<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Connection;
use InvalidArgumentException;

/** Bounded INSERTs only; the caller still owns the full revision transaction. */
final class SnapshotInsertBatch
{
  private array $batch = [];

  private int $memoryBytes = 0;

  private int $wireBytes = 0;

  private readonly int $rows;

  private readonly int $headerBytes;

  public function __construct(
    private readonly Connection $connection,
    private readonly string $table,
    private readonly array $columns,
    private readonly SnapshotInsertLimits $limits,
  ) {
    if ($columns === [] || $limits->parameters < 1 || $limits->rows < 1) {
      throw new InvalidArgumentException('Snapshot INSERT limits and columns must be nonempty.');
    }
    $this->rows = min($limits->rows, intdiv($limits->parameters, count($columns)));
    if ($this->rows < 1) {
      throw new SnapshotWriteLimitException('parameters');
    }
    $headerBytes = 256 + strlen($table) * 2;
    foreach (array_keys($columns) as $column) {
      $headerBytes += strlen($column) * 2 + 8;
    }
    $this->headerBytes = $headerBytes;
  }

  public function add(array $row): void
  {
    if (array_diff_key($row, $this->columns) !== []) {
      throw new InvalidArgumentException('A snapshot record contains undeclared INSERT columns.');
    }
    $row = array_replace($this->columns, $row);
    $payload = 0;
    foreach ($row as $value) {
      $payload += is_string($value) ? strlen($value) : 8;
    }
    $memory = 256 + count($row) * 96 + $payload;
    // Include placeholder/quoting overhead, including emulated PDO prepares.
    $wire = 128 + count($row) * 32 + $payload * 4;
    $packet = $this->limits->packetBytes;
    if ($packet !== null && $wire + $this->headerBytes > intdiv($packet * 3, 4)) {
      throw new SnapshotWriteLimitException('packet');
    }
    $wireLimit = $packet === null ? 1048576 : min(1048576, max(1, intdiv($packet, 4) - $this->headerBytes));
    if ($this->batch !== [] && (count($this->batch) >= $this->rows
      || $this->memoryBytes + $memory > $this->limits->memoryBytes()
      || $this->wireBytes + $wire > $wireLimit)) {
      $this->finish();
    }
    $this->batch[] = $row;
    $this->memoryBytes += $memory;
    $this->wireBytes += $wire;
  }

  public function finish(): void
  {
    if ($this->batch !== []) {
      // The query builder normalizes and flattens rows. Do not keep a second
      // owner of the whole batch while it creates its SQL/binding arrays.
      $this->connection->table($this->table)->insert($this->takeRows());
    }
  }

  private function takeRows(): array
  {
    $rows = $this->batch;
    $this->batch = [];
    $this->memoryBytes = $this->wireBytes = 0;

    return $rows;
  }
}
