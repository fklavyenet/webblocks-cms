<?php

namespace WebBlocks\Cms\Support\System;

use RuntimeException;

class DatabaseExecutionStrategyResolver
{
  public function resolveMysqlStrategy(): string
  {
    $configuredStrategy = strtolower((string) config('cms.backup.execution', 'auto'));

    return match ($configuredStrategy) {
      'direct' => 'direct',
      'auto', '' => 'direct',
      default => throw new RuntimeException('Invalid cms.backup.execution value ['.$configuredStrategy.']. Supported values: auto, direct.'),
    };
  }
}
