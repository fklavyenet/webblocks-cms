<?php

namespace WebBlocks\Cms\Support\Applications;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\SiteHandle;
use WebBlocks\Cms\Support\System\MaintenanceCleanup;

class ApplicationAssetStore
{
  public const TYPES = ['css', 'js', 'html'];

  public function all(Site $site, EmbeddedApplication $application): array
  {
    $assets = [];

    foreach (self::TYPES as $type) {
      if ($type === 'html') {
        $asset = $this->read($site, $application, 'html', 'index.html');

        if ($asset['exists']) {
          $assets[] = $asset;
        }

        continue;
      }

      $directory = $this->directory($site, $application, $type);

      if (! is_dir($directory)) {
        continue;
      }

      foreach (File::files($directory) as $file) {
        $assets[] = $this->read($site, $application, $type, $file->getFilename());
      }
    }

    usort($assets, fn (array $left, array $right): int => [$left['type'], $left['filename']] <=> [$right['type'], $right['filename']]);

    return $assets;
  }

  public function read(Site $site, EmbeddedApplication $application, string $type, string $filename): array
  {
    [$type, $filename] = $this->normalize($type, $filename);
    $path = $this->absolutePath($site, $application, $type, $filename);
    $exists = is_file($path);
    $contents = $exists ? (string) file_get_contents($path) : '';

    return [
      'type' => $type,
      'filename' => $filename,
      'relative_path' => $this->relativePath($site, $application, $type, $filename),
      'public_path' => $type === 'html'
        ? $this->managedEntryPath($application)
        : '/'.$this->relativePath($site, $application, $type, $filename),
      'exists' => $exists,
      'contents' => $contents,
      'checksum' => $exists ? hash('sha256', $contents) : null,
      'size' => $exists ? strlen($contents) : 0,
      'updated_at' => $exists ? filemtime($path) : null,
    ];
  }

  public function write(Site $site, EmbeddedApplication $application, string $type, string $filename, string $contents, ?string $expectedChecksum): array
  {
    $current = $this->read($site, $application, $type, $filename);

    if ($expectedChecksum !== $current['checksum']) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_changed_save'));
    }

    if ($current['exists'] && $current['contents'] !== $contents) {
      $this->writeRevision($site, $application, $current);
    }

    try {
      File::ensureDirectoryExists(dirname($this->absolutePath($site, $application, $type, $filename)));
      File::put($this->absolutePath($site, $application, $type, $filename), $contents);
      $this->pruneRevisions();
    } catch (Throwable $exception) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_write_failed'), previous: $exception);
    }

    return $this->read($site, $application, $type, $filename);
  }

  public function delete(Site $site, EmbeddedApplication $application, string $type, string $filename, ?string $expectedChecksum): array
  {
    $current = $this->read($site, $application, $type, $filename);

    if (! $current['exists']) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_missing'));
    }

    if ($expectedChecksum !== $current['checksum']) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_changed_delete'));
    }

    $this->writeRevision($site, $application, $current);
    File::delete($this->absolutePath($site, $application, $type, $filename));
    $this->pruneRevisions();

    return $current;
  }

  public function relativePath(Site $site, EmbeddedApplication $application, string $type, string $filename): string
  {
    [$type, $filename] = $this->normalize($type, $filename);
    $siteHandle = SiteHandle::normalize((string) $site->handle);
    $applicationHandle = trim((string) $application->handle);

    if ($siteHandle === '' || preg_match('/^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$/', $applicationHandle) !== 1) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.handles_required'));
    }

    return 'site/'.$siteHandle.'/applications/'.$applicationHandle.'/'.($type === 'html' ? $filename : $type.'/'.$filename);
  }

  public function managedEntryPath(EmbeddedApplication $application): string
  {
    return '/webblocks-applications/'.trim((string) $application->handle).'/index.html';
  }

  public function activateManagedEntry(EmbeddedApplication $application): void
  {
    $application->forceFill([
      'render_mode' => 'iframe',
      'entry_url' => $this->managedEntryPath($application),
    ])->save();
  }

  private function absolutePath(Site $site, EmbeddedApplication $application, string $type, string $filename): string
  {
    $path = public_path($this->relativePath($site, $application, $type, $filename));
    $root = public_path('site').DIRECTORY_SEPARATOR;

    if (! str_starts_with($path, $root)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_outside_site'));
    }

    return $path;
  }

  private function directory(Site $site, EmbeddedApplication $application, string $type): string
  {
    return dirname($this->absolutePath($site, $application, $type, 'asset.'.$type));
  }

  private function normalize(string $type, string $filename): array
  {
    $type = strtolower(trim($type));
    $filename = trim($filename);

    if (! in_array($type, self::TYPES, true)) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_type'));
    }

    if ($type === 'html' && $filename !== 'index.html') {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_entry_filename'));
    }

    if (preg_match('/^[a-zA-Z0-9][a-zA-Z0-9._-]{0,127}$/', $filename) !== 1 || strtolower(pathinfo($filename, PATHINFO_EXTENSION)) !== $type) {
      throw new RuntimeException(__('webblocks-cms::admin-feedback.application_assets.asset_filename', ['type' => $type]));
    }

    return [$type, $filename];
  }

  private function writeRevision(Site $site, EmbeddedApplication $application, array $asset): void
  {
    $path = storage_path('app/cms/application-assets/'.$site->id.'/'.$application->handle.'/'.$asset['type'].'/'.now()->format('YmdHis').'-'.$asset['checksum'].'-'.$asset['filename']);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, $asset['contents']);
  }

  private function pruneRevisions(): void
  {
    try {
      app(MaintenanceCleanup::class)->run(MaintenanceCleanup::ASSET_REVISIONS);
    } catch (Throwable $exception) {
      Log::warning('Application asset revision cleanup did not complete.', ['message' => $exception->getMessage()]);
    }
  }
}
