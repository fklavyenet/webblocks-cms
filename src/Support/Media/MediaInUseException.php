<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Collection;
use RuntimeException;

class MediaInUseException extends RuntimeException
{
  public function __construct(private readonly Collection $usages)
  {
    parent::__construct('Media cannot be deleted because it is in use.');
  }

  public function usages(): Collection
  {
    return $this->usages;
  }

  public function summary(int $limit = 3): string
  {
    return $this->usages
      ->take($limit)
      ->map(fn (array $usage): string => $usage['context'].': '.$usage['label'])
      ->implode(', ');
  }
}
