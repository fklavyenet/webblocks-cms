<?php

namespace WebBlocks\Cms\Support\Updates;

use WebBlocks\Cms\Support\System\Updates\CmsPackageRollback;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult;

class CmsClientSystemUpdater extends SystemUpdater
{
  public function run(?int $userId = null): UpdateResult
  {
    $result = parent::run($userId);
    app(CmsPackageRollback::class)->commit();

    return $result;
  }
}
