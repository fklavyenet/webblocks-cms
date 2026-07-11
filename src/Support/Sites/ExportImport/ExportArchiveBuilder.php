<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;
use ZipArchive;

class ExportArchiveBuilder
{
  public function __construct(
    private readonly SiteTransferPathGuard $pathGuard,
    private readonly PageAssetPathValidator $pageAssetPathValidator,
  ) {}

  public function build(string $archivePath, array $manifest, array $payload, bool $includesMedia, array &$output = []): int
  {
    File::ensureDirectoryExists(dirname($archivePath));

    $archive = new ZipArchive;
    $result = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($result !== true) {
      throw new RuntimeException('Could not create export package archive.');
    }

    try {
      $archive->addEmptyDir('data');
      $archive->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

      foreach (SiteTransferPackage::ALL_DATA_FILES as $file) {
        $key = pathinfo($file, PATHINFO_FILENAME);
        $archive->addFromString($file, json_encode($payload[$key] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
      }

      $fileCount = 0;

      if ($includesMedia) {
        foreach ($payload['media'] as $asset) {
          $sourcePath = (string) ($asset['path'] ?? '');
          $diskName = (string) ($asset['disk'] ?? 'public');

          $this->pathGuard->assertSafeRelativePath($sourcePath, 'Asset path');

          $disk = Storage::disk($diskName);

          if (! $disk->exists($sourcePath)) {
            throw new RuntimeException('Asset file is missing for export: '.$sourcePath);
          }

          $archiveEntry = 'files/'.$diskName.'/'.$sourcePath;
          $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive file path');
          $archive->addFile($disk->path($sourcePath), $archiveEntry);
          $fileCount++;
        }

        foreach ($payload['page_assets'] ?? [] as $pageAsset) {
          $sourcePath = $this->pageAssetPathValidator->relativePublicPath((string) ($pageAsset['path'] ?? ''));
          $absolutePath = public_path($sourcePath);

          if (! is_file($absolutePath)) {
            $output[] = 'Skipped missing page asset file '.$sourcePath.'.';

            continue;
          }

          $archiveEntry = 'files/public/'.$sourcePath;
          $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive file path');
          $archive->addFile($absolutePath, $archiveEntry);
          $fileCount++;
        }

        foreach ($payload['site_public_assets'] ?? [] as $sitePublicAsset) {
          $sourcePath = $this->canonicalSitePublicAssetPath($sitePublicAsset);
          $absolutePath = public_path($sourcePath);

          if (! is_file($absolutePath)) {
            $output[] = 'Skipped missing site public asset file '.$sourcePath.'.';

            continue;
          }

          $archiveEntry = 'files/public/'.$sourcePath;
          $this->pathGuard->assertSafeRelativePath($archiveEntry, 'Archive file path');
          $archive->addFile($absolutePath, $archiveEntry);
          $fileCount++;
        }
      }

      $archive->close();
      $output[] = 'Archive created as '.basename($archivePath).'.';
      $output[] = 'JSON manifests written for site, pages, shared slots, blocks, navigation, locales, and media.';

      if ($includesMedia) {
        $output[] = 'Added '.$fileCount.' media file(s) to package.';
      }

      $size = filesize($archivePath);

      return $size === false ? 0 : $size;
    } catch (Throwable $throwable) {
      $archive->close();
      throw $throwable;
    }
  }

  private function canonicalSitePublicAssetPath(array $sitePublicAsset): string
  {
    $relativePath = ltrim((string) ($sitePublicAsset['relative_path'] ?? $sitePublicAsset['path'] ?? ''), '/');
    $type = (string) ($sitePublicAsset['type'] ?? '');

    $this->pathGuard->assertSafeRelativePath($relativePath, 'Site public asset path');

    if (! preg_match('#^site/[a-z0-9]+(?:-[a-z0-9]+)*/(css/site\.css|js/site\.js)$#', $relativePath)) {
      throw new RuntimeException('Site public asset path is invalid.');
    }

    if (($type === 'css' && ! str_ends_with($relativePath, '/css/site.css'))
      || ($type === 'js' && ! str_ends_with($relativePath, '/js/site.js'))) {
      throw new RuntimeException('Site public asset type does not match its path.');
    }

    return $relativePath;
  }
}
