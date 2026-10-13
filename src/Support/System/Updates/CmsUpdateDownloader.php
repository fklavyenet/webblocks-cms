<?php

namespace WebBlocks\Cms\Support\System\Updates;

use WebBlocks\Cms\Support\Updates\Client\Updates\UpdatePackageDownloader;

class CmsUpdateDownloader extends UpdatePackageDownloader
{
  public function download(string $url, string $destinationPath): void
  {
    app(CmsUpdateProgress::class)->stage('download');
    parent::download($url, $destinationPath);
  }
}
