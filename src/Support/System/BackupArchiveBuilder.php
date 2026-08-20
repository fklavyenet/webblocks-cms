<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

class BackupArchiveBuilder
{
  public function build(string $archivePath, string $databaseDumpPath, array $manifest, array &$output = []): array
  {
    File::ensureDirectoryExists(dirname($archivePath));

    $archive = new ZipArchive;
    $result = $archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    if ($result !== true) {
      throw new RuntimeException('Could not create backup archive.');
    }

    $archive->addEmptyDir('database');
    $archive->addFile($databaseDumpPath, 'database/database.sql');
    $archive->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $uploadsCount = $this->addUploads($archive);
    $sitePublicCount = $this->addSitePublicFiles($archive);
    $archive->close();

    $output[] = 'Archive created as '.basename($archivePath).'.';
    $output[] = 'Added '.$uploadsCount.' upload file(s) from storage/app/public.';
    $output[] = 'Added '.$sitePublicCount.' site public file(s) from public/site.';

    return [
      'uploads_file_count' => $uploadsCount,
      'site_public_file_count' => $sitePublicCount,
    ];
  }

  private function addSitePublicFiles(ZipArchive $archive): int
  {
    $archive->addEmptyDir('site-public');
    $root = public_path('site');
    $count = 0;

    if (! File::isDirectory($root)) {
      return 0;
    }

    foreach (File::allFiles($root) as $file) {
      $archive->addFile($file->getPathname(), 'site-public/'.$file->getRelativePathname());
      $count++;
    }

    return $count;
  }

  private function addUploads(ZipArchive $archive): int
  {
    $archive->addEmptyDir('uploads');
    $archive->addEmptyDir('uploads/public');

    $count = 0;
    $disk = Storage::disk('public');

    foreach ($disk->allFiles() as $file) {
      $path = $disk->path($file);

      if (! is_file($path)) {
        continue;
      }

      $archive->addFile($path, 'uploads/public/'.$file);
      $count++;
    }

    return $count;
  }
}
