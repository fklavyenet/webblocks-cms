<?php

namespace App\Support\Sites\ExportImport;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class ExportArchiveBuilder
{
    public function __construct(
        private readonly SiteTransferPathGuard $pathGuard,
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

            foreach (SiteTransferPackage::REQUIRED_DATA_FILES as $file) {
                $key = pathinfo($file, PATHINFO_FILENAME);
                $archive->addFromString($file, json_encode($payload[$key] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
            }

            $fileCount = 0;

            if ($includesMedia) {
                foreach ($payload['assets'] as $asset) {
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
            }

            $archive->close();
            $output[] = 'Archive created as '.basename($archivePath).'.';
            $output[] = 'JSON manifests written for site, pages, blocks, navigation, locales, and assets.';

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
}
