<?php

namespace WebBlocks\Cms\Support\Relational;

use Illuminate\Database\Connection;
use PDOException;

/** Connection-local limits, rediscovered for each complete snapshot write. */
final readonly class SnapshotInsertLimits
{
  public function __construct(
    public int $parameters,
    public ?int $packetBytes = null,
    public int $rows = 1000,
  ) {}

  public static function forConnection(Connection $connection): self
  {
    if ($connection->getDriverName() === 'sqlite') {
      // A compile-time limit can be lowered on this PDO connection. Preparing an
      // unexecuted, constant-only statement checks the effective limit without
      // touching data or retrying an INSERT that might already have effects.
      $parameters = 16000;
      $pdo = $connection->getPdo();
      while ($parameters > 1) {
        try {
          $probe = $pdo->prepare('select 1 where 1 in ('.implode(',', array_fill(0, $parameters, '?')).')');
          if ($probe === false) {
            if (str_contains(strtolower((string) ($pdo->errorInfo()[2] ?? '')), 'too many sql variables')) {
              $parameters = intdiv($parameters, 2);

              continue;
            }
            throw new SnapshotWriteLimitException('discovery');
          }
          unset($probe);

          return new self($parameters);
        } catch (PDOException $exception) {
          if (! str_contains(strtolower($exception->getMessage()), 'too many sql variables')) {
            throw $exception;
          }
          $parameters = intdiv($parameters, 2);
        }
      }

      return new self(1);
    }
    if (in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
      $row = $connection->selectOne('select @@session.max_allowed_packet as packet_bytes', [], false);
      $packet = (int) ($row?->packet_bytes ?? 0);
      if ($packet <= 0) {
        throw new SnapshotWriteLimitException('discovery');
      }

      return new self(16000, $packet);
    }

    return new self(900, rows: 100);
  }

  public function memoryBytes(): int
  {
    $limit = ini_parse_quantity((string) ini_get('memory_limit'));

    // Leave room for bindings, SQL compilation and the encoder's typed records.
    return $limit < 0 ? 2097152 : max(1, min(2097152, intdiv(max(0, $limit - memory_get_usage(true)), 8)));
  }
}
