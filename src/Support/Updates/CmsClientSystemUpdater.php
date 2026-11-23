<?php

namespace WebBlocks\Cms\Support\Updates;

use Illuminate\Support\Facades\Log;
use WebBlocks\Cms\Support\System\Updates\CmsPackageRollback;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherArtifact;
use WebBlocks\Cms\Support\System\Updates\CmsPublisherTestAccess;
use WebBlocks\Cms\Support\Updates\Client\Support\RunStatus;
use WebBlocks\Cms\Support\Updates\Client\Updates\SystemUpdater;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateResult;

class CmsClientSystemUpdater extends SystemUpdater
{
  public function run(?int $userId = null): UpdateResult
  {
    $artifact = app(CmsPublisherArtifact::class);
    $artifact->start('');
    try {
      $result = parent::run($userId);
    } catch (\Throwable $failure) {
      app(CmsPublisherTestAccess::class)->report(false);

      throw $failure;
    }
    app(CmsPackageRollback::class)->commit();
    if ($artifact->receiptProof !== null
      && ! app(CmsPublisherTestAccess::class)->report(in_array($result->status, [RunStatus::SUCCESS, RunStatus::SUCCESS_WITH_WARNINGS], true))) {
      Log::warning('Publisher did not confirm the CMS pilot receipt.');
    }

    return $result;
  }
}
