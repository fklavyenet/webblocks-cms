<?php

namespace WebBlocks\Cms\Support\System;

class SystemBackupBulkDeleteResult
{
  public function __construct(
  private readonly int $requestedCount,
  private readonly array $deletedIds,
  private readonly array $failed,
  ) {}

  public function requestedCount(): int
  {
  return $this->requestedCount;
  }

  public function deletedCount(): int
  {
  return count($this->deletedIds);
  }

  public function failedCount(): int
  {
  return count($this->failed);
  }

  public function hasFailures(): bool
  {
  return $this->failedCount() > 0;
  }

  public function message(): string
  {
  if ($this->deletedCount() === 0 && $this->hasFailures()) {
      return 'No selected backups were deleted.';
  }

  if ($this->hasFailures()) {
      return $this->deletedCount().' selected '.str('backup')->plural($this->deletedCount()).' deleted. '.$this->failedCount().' could not be deleted.';
  }

  return $this->deletedCount().' selected '.str('backup')->plural($this->deletedCount()).' deleted.';
  }

  public function failureMessages(): array
  {
  return array_values(array_map(
      fn (array $failure): string => 'Backup #'.$failure['id'].' could not be deleted: '.$failure['message'],
      $this->failed,
  ));
  }
}
