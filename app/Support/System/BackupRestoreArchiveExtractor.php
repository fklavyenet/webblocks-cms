<?php

namespace App\Support\System;

use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

class BackupRestoreArchiveExtractor
{
    public function extractTo(string $archivePath, string $destinationPath): void
    {
        File::ensureDirectoryExists($destinationPath);

        $archive = new ZipArchive;
        $result = $archive->open($archivePath);

        if ($result !== true) {
            throw new RuntimeException('Backup archive could not be opened for extraction.');
        }

        try {
            for ($index = 0; $index < $archive->numFiles; $index++) {
                $rawName = $archive->getNameIndex($index);

                if (! is_string($rawName) || $rawName === '') {
                    continue;
                }

                $entryName = str_replace('\\', '/', $rawName);

                if ($this->isInvalidArchiveEntryPath($entryName)) {
                    throw new RuntimeException('Backup archive contains an unsafe entry path: '.$entryName);
                }

                $targetPath = $destinationPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, rtrim($entryName, '/'));

                if (str_ends_with($entryName, '/')) {
                    File::ensureDirectoryExists($targetPath);

                    continue;
                }

                File::ensureDirectoryExists(dirname($targetPath));

                $stream = $archive->getStream($rawName);

                if (! is_resource($stream)) {
                    throw new RuntimeException('Backup archive entry could not be read: '.$entryName);
                }

                $handle = fopen($targetPath, 'wb');

                if ($handle === false) {
                    fclose($stream);

                    throw new RuntimeException('Restore destination could not be opened for writing: '.$entryName);
                }

                try {
                    stream_copy_to_stream($stream, $handle);
                } finally {
                    fclose($stream);
                    fclose($handle);
                }
            }
        } finally {
            $archive->close();
        }
    }

    private function isInvalidArchiveEntryPath(string $path): bool
    {
        $normalizedPath = trim($path);

        if ($normalizedPath === '' || str_starts_with($normalizedPath, '/') || preg_match('/^[A-Za-z]:\//', $normalizedPath) === 1) {
            return true;
        }

        foreach (explode('/', $normalizedPath) as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }
}
