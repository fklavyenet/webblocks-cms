<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdatePackageExtractor;

class CmsUpdateExtractor extends UpdatePackageExtractor
{
  public function extract(string $archivePath, string $destinationDirectory): string
  {
    app(CmsUpdateProgress::class)->stage('extract');

    return parent::extract($archivePath, $destinationDirectory);
  }
}
