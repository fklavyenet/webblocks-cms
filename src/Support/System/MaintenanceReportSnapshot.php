<?php

declare(strict_types=1);

namespace WebBlocks\Cms\Support\System;

use RuntimeException;

/** Array/scalar transport for the optional reporting cache, never deletion authority. */
final class MaintenanceReportSnapshot
{
  private const VERSION = 1;

  private const CLEANUP_RESULTS = ['asset_revisions', 'media_variants', 'temporary_workspaces', 'page_revision_cleanup', 'shared_slot_revision_cleanup'];

  public function encode(array $report): array
  {
    $report['schema_version'] = self::VERSION;
    if (! ($report['backup'] ?? null) instanceof SystemBackupCleanupResult) {
      throw new RuntimeException('Invalid backup report.');
    }
    $report['backup'] = get_object_vars($report['backup']);
    foreach (self::CLEANUP_RESULTS as $key) {
      if (! ($report['cleanup'][$key] ?? null) instanceof MaintenanceCleanupResult) {
        throw new RuntimeException('Invalid cleanup report.');
      }
      $report['cleanup'][$key] = get_object_vars($report['cleanup'][$key]);
    }
    if ($this->decode($report) === null) {
      throw new RuntimeException('Invalid maintenance report snapshot.');
    }

    return $report;
  }

  public function decode(mixed $payload): ?array
  {
    if (! is_array($payload) || ! $this->plain($payload)
      || ($payload['schema_version'] ?? null) !== self::VERSION
      || ! is_string($payload['snapshot_id'] ?? null) || $payload['snapshot_id'] === ''
      || ! is_int($payload['checked_at'] ?? null) || $payload['checked_at'] < 0
      || ! is_string($payload['generation'] ?? null)
      || ! is_array($payload['backup'] ?? null) || ! is_array($payload['cleanup'] ?? null)) {
      return null;
    }
    $backup = $payload['backup'];
    if (! $this->counts($backup, ['candidateBytes', 'deletedBytes'])
      || ! $this->ids($backup['candidateIds'] ?? null) || ! $this->ids($backup['deletedIds'] ?? null)
      || ! is_array($backup['failures'] ?? null) || ! is_array($backup['overview']['storage'] ?? null)) {
      return null;
    }
    foreach (self::CLEANUP_RESULTS as $key) {
      $result = $payload['cleanup'][$key] ?? null;
      if (! is_array($result) || ! $this->counts($result, ['candidateCount', 'candidateBytes', 'deletedCount', 'deletedBytes']) || ! is_array($result['failures'] ?? null)) {
        return null;
      }
      $payload['cleanup'][$key] = new MaintenanceCleanupResult($result['candidateCount'], $result['candidateBytes'], $result['deletedCount'], $result['deletedBytes'], $result['failures']);
    }
    if (! is_array($payload['cleanup']['storage_report'] ?? null) || ! is_array($payload['cleanup']['media_storage'] ?? null)) {
      return null;
    }
    $payload['backup'] = new SystemBackupCleanupResult($backup['candidateIds'], $backup['candidateBytes'], $backup['deletedIds'], $backup['deletedBytes'], $backup['failures'], $backup['overview']);

    return $payload;
  }

  private function counts(array $values, array $keys): bool
  {
    foreach ($keys as $key) {
      if (! is_int($values[$key] ?? null) || $values[$key] < 0) {
        return false;
      }
    }

    return true;
  }

  private function ids(mixed $values): bool
  {
    if (! is_array($values) || ! array_is_list($values)) {
      return false;
    }
    foreach ($values as $value) {
      if (! is_int($value) || $value < 1) {
        return false;
      }
    }

    return true;
  }

  private function plain(mixed $value, int $depth = 0): bool
  {
    if ($depth > 32) {
      return false;
    }
    if (! is_array($value)) {
      return $value === null || is_scalar($value);
    }
    foreach ($value as $item) {
      if (! $this->plain($item, $depth + 1)) {
        return false;
      }
    }

    return true;
  }
}
