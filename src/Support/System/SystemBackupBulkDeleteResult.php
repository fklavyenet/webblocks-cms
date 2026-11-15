<?php

namespace WebBlocks\Cms\Support\System;

use WebBlocks\Cms\Support\Admin\SelectedBulkDeleteResult;

class SystemBackupBulkDeleteResult extends SelectedBulkDeleteResult
{
  public function __construct(
  private readonly int $requestedCount,
  array $deletedIds,
  array $failed,
  ) {
  parent::__construct('backup', 'backups', $deletedIds, $failed);
  }

  public function requestedCount(): int
  {
  return $this->requestedCount;
  }
}
