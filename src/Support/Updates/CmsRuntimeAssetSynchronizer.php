<?php

namespace WebBlocks\Cms\Support\Updates;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\System\Updates\UpdateException;

final class CmsRuntimeAssetSynchronizer
{
  public function sync(string $packageRoot, string $targetRoot, array &$output): void
  {
    $source = rtrim($packageRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'cms';

    if (! File::isDirectory($source)) {
      return;
    }

    $target = rtrim($targetRoot, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'cms';
    File::ensureDirectoryExists($target);

    $targetUiRuntime = $target.DIRECTORY_SEPARATOR.'webblocks-ui';

    if (File::isDirectory($targetUiRuntime) && ! File::deleteDirectory($targetUiRuntime)) {
      throw new UpdateException(
        'The update could not publish the CMS browser assets.',
        'Failed to replace the host public WebBlocks UI runtime at '.$targetUiRuntime.'.',
      );
    }

    foreach (File::allFiles($source) as $file) {
      $relativePath = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
      $destination = $target.DIRECTORY_SEPARATOR.$relativePath;

      File::ensureDirectoryExists(dirname($destination));

      if (! File::copy($file->getPathname(), $destination)) {
        throw new UpdateException(
          'The update could not publish the CMS browser assets.',
          'Failed to copy package browser asset to '.$destination.'.',
        );
      }
    }

    $output[] = 'Synced package public/cms assets into public/cms runtime compatibility path.';

    $retiredCmsIndex = $target.DIRECTORY_SEPARATOR.'index.php';

    if (File::exists($retiredCmsIndex) && ! File::exists($source.DIRECTORY_SEPARATOR.'index.php')) {
      File::delete($retiredCmsIndex);
      $output[] = 'Removed retired public/cms/index.php front-controller handoff.';
    }
  }
}
