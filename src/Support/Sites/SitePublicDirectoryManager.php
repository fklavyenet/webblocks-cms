<?php

namespace WebBlocks\Cms\Support\Sites;

use Illuminate\Support\Facades\File;
use Throwable;
use WebBlocks\Cms\Models\Site;

class SitePublicDirectoryManager
{
  /**
   * Move a site's public override directory (site.css, site.js, and anything
   * else under public/site/{handle}) to follow a handle change.
   *
   * Nothing else in the codebase keys these files by site id, only by
   * handle, so a rename that skips this step silently orphans them: the
   * asset resolver looks under the new handle, finds nothing, and the public
   * layout just omits the <link>/<script> tags with no error anywhere.
   */
  public function relocateAssetDirectory(string $previousHandle, Site $site): array
  {
    $warnings = [];
    $previousHandle = SiteHandle::normalize($previousHandle);
    $currentHandle = $this->handle($site);

    if ($previousHandle === '' || $currentHandle === '' || $previousHandle === $currentHandle) {
      return $warnings;
    }

    $sourcePath = public_path('site/'.$previousHandle);

    if (! is_dir($sourcePath)) {
      return $warnings;
    }

    $targetPath = public_path('site/'.$currentHandle);

    try {
      if (is_dir($targetPath)) {
        File::copyDirectory($sourcePath, $targetPath);
        File::deleteDirectory($sourcePath);
      } else {
        File::ensureDirectoryExists(dirname($targetPath));
        File::moveDirectory($sourcePath, $targetPath);
      }
    } catch (Throwable $exception) {
      $warnings[] = 'CMS could not move the site asset directory from [site/'.$previousHandle.'] to [site/'.$currentHandle.']. Move public/site/'.$previousHandle.' to public/site/'.$currentHandle.' by hand so site.css and site.js keep serving under the new handle.';
    }

    return $warnings;
  }

  public function ensureAssetDirectories(Site $site): array
  {
    $warnings = [];

    foreach (['css', 'js'] as $directory) {
      $path = public_path($this->siteRelativePath($site).'/'.$directory);

      try {
        File::ensureDirectoryExists($path);
      } catch (Throwable $exception) {
        $warnings[] = 'CMS could not create ['.$this->siteRelativePath($site).'/'.$directory.']. Make sure public/site/'.$this->handle($site).' is writable by the web server user or group.';
      }
    }

    return $warnings;
  }

  public function readiness(Site $site, string $type): array
  {
    $handle = $this->handle($site);
    $siteRelativePath = $this->siteRelativePath($site);
    $assetDirectoryRelativePath = $siteRelativePath.'/'.($type === SiteAssetStore::TYPE_CSS ? 'css' : 'js');
    $assetRelativePath = $assetDirectoryRelativePath.'/'.($type === SiteAssetStore::TYPE_CSS ? 'site.css' : 'site.js');
    $sitePath = public_path($siteRelativePath);
    $assetDirectoryPath = public_path($assetDirectoryRelativePath);
    $assetPath = public_path($assetRelativePath);

    foreach ([$sitePath, $assetDirectoryPath, $assetPath, public_path('site')] as $path) {
      clearstatcache(true, $path);
    }

    $siteDirectoryExists = is_dir($sitePath);
    $assetDirectoryExists = is_dir($assetDirectoryPath);
    $assetExists = is_file($assetPath);
    $fileWritable = $assetExists && is_writable($assetPath);
    $assetDirectoryWritable = $assetDirectoryExists && is_writable($assetDirectoryPath);
    $siteDirectoryWritable = $siteDirectoryExists && is_writable($sitePath);
    $writable = $assetExists ? $fileWritable : ($assetDirectoryExists ? $assetDirectoryWritable : $siteDirectoryWritable);
    $problem = null;

    if ($handle === '') {
      $problem = 'Site handle is required before site assets can be managed.';
    } elseif (! $siteDirectoryExists) {
      $publicSitePath = public_path('site');
      $problem = $this->directoryCanBeCreated($publicSitePath)
        ? null
        : 'The public/site directory is not writable, so CMS cannot create the site asset directory.';
      $writable = $problem === null;
    } elseif (! $assetDirectoryExists && ! $siteDirectoryWritable) {
      $problem = 'The site public directory is not writable, so CMS cannot create '.$type.' asset files.';
    } elseif ($assetDirectoryExists && ! $assetDirectoryWritable && ! $assetExists) {
      $problem = 'The '.$type.' asset directory is not writable, so CMS cannot create the asset file.';
    } elseif ($assetExists && ! $fileWritable) {
      $problem = 'The existing '.$type.' asset file is not writable by the web server user.';
    }

    return [
      'ready' => $problem === null,
      'writable' => $writable && $problem === null,
      'problem' => $problem,
      'site_directory_exists' => $siteDirectoryExists,
      'site_directory_writable' => $siteDirectoryWritable,
      'asset_directory_exists' => $assetDirectoryExists,
      'asset_directory_writable' => $assetDirectoryWritable,
      'file_writable' => $fileWritable,
    ];
  }

  private function siteRelativePath(Site $site): string
  {
    return 'site/'.$this->handle($site);
  }

  private function handle(Site $site): string
  {
    return SiteHandle::normalize((string) $site->handle);
  }

  private function directoryCanBeCreated(string $path): bool
  {
    $candidate = $path;

    while (! file_exists($candidate)) {
      $parent = dirname($candidate);

      if ($parent === $candidate) {
        return false;
      }

      $candidate = $parent;
    }

    return is_dir($candidate) && is_writable($candidate);
  }
}
