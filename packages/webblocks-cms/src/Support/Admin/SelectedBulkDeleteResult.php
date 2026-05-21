<?php

namespace WebBlocks\Cms\Support\Admin;

class SelectedBulkDeleteResult
{
  public function __construct(
    private readonly string $singularLabel,
    private readonly string $pluralLabel,
    private readonly array $deletedIds,
    private readonly array $failed,
  ) {}

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
      return 'No selected '.$this->pluralLabel.' were deleted.';
    }

    if ($this->hasFailures()) {
      return $this->deletedCount().' selected '.$this->labelFor($this->deletedCount()).' deleted. '.$this->failedCount().' could not be deleted.';
    }

    return $this->deletedCount().' selected '.$this->labelFor($this->deletedCount()).' deleted.';
  }

  public function failureMessages(): array
  {
    return array_values(array_map(
      fn (array $failure): string => ucfirst($this->singularLabel).' #'.$failure['id'].' could not be deleted: '.$failure['message'],
      $this->failed,
    ));
  }

  private function labelFor(int $count): string
  {
    return $count === 1 ? $this->singularLabel : $this->pluralLabel;
  }
}
