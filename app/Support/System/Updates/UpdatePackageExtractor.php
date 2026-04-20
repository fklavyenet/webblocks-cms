<?php

namespace App\Support\System\Updates;

use Illuminate\Support\Facades\File;
use ZipArchive;

class UpdatePackageExtractor
{
    public function extract(string $archivePath, string $destinationDirectory): string
    {
        if (! class_exists(ZipArchive::class)) {
            throw new UpdateException('ZIP archive support is not available on this server.');
        }

        $archive = new ZipArchive;
        $openResult = $archive->open($archivePath);

        if ($openResult !== true) {
            throw new UpdateException('The downloaded update package is not a valid ZIP archive.', 'Invalid archive: ZipArchive open failed.');
        }

        File::ensureDirectoryExists($destinationDirectory);

        for ($index = 0; $index < $archive->numFiles; $index++) {
            $entryName = (string) $archive->getNameIndex($index);
            $normalizedPath = $this->normalizeEntryPath($entryName);

            if ($normalizedPath === null) {
                continue;
            }

            $targetPath = $destinationDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

            if (str_ends_with($entryName, '/')) {
                File::ensureDirectoryExists($targetPath);

                continue;
            }

            $sourceStream = $archive->getStream($entryName);

            if ($sourceStream === false) {
                $archive->close();

                throw new UpdateException('The update package could not be extracted.', 'Extraction failed for '.$entryName.'.');
            }

            File::ensureDirectoryExists(dirname($targetPath));

            $targetStream = fopen($targetPath, 'wb');

            if ($targetStream === false) {
                fclose($sourceStream);
                $archive->close();

                throw new UpdateException('The update package could not be extracted.', 'Extraction failed because '.$targetPath.' could not be opened for writing.');
            }

            stream_copy_to_stream($sourceStream, $targetStream);
            fclose($sourceStream);
            fclose($targetStream);
        }

        $archive->close();

        return $this->detectPackageRoot($destinationDirectory);
    }

    private function normalizeEntryPath(string $entryName): ?string
    {
        $entryName = str_replace('\\', '/', $entryName);
        $entryName = preg_replace('/^(\.\/)+/', '', $entryName) ?? $entryName;
        $entryName = ltrim($entryName, '/');

        if ($entryName === '' || str_starts_with($entryName, '__MACOSX/')) {
            return null;
        }

        if (preg_match('/^[A-Za-z]:/', $entryName) === 1 || preg_match('/(^|\/)\.\.(\/|$)/', $entryName) === 1) {
            throw new UpdateException('The downloaded update package contains invalid paths.', 'Path traversal detected in archive entry '.$entryName.'.');
        }

        $segments = array_values(array_filter(explode('/', $entryName), static fn (string $segment): bool => $segment !== '' && $segment !== '.'));

        if ($segments === []) {
            return null;
        }

        return implode('/', $segments);
    }

    private function detectPackageRoot(string $destinationDirectory): string
    {
        if ($this->isValidPackageRoot($destinationDirectory)) {
            return $destinationDirectory;
        }

        $directories = array_values(array_filter(File::directories($destinationDirectory), function (string $directory): bool {
            return basename($directory) !== '__MACOSX';
        }));

        if (count($directories) === 1 && $this->isValidPackageRoot($directories[0])) {
            return $directories[0];
        }

        throw new UpdateException(
            'The downloaded update package does not match the expected WebBlocks CMS structure.',
            'Package validation failed because composer.json and artisan were not found at the archive root.',
        );
    }

    private function isValidPackageRoot(string $path): bool
    {
        return File::isFile($path.'/artisan') && File::isFile($path.'/composer.json');
    }
}
