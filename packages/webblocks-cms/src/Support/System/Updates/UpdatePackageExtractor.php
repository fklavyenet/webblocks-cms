<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use JsonException;
use ZipArchive;

class UpdatePackageExtractor
{
  private const MAX_ARCHIVE_ENTRIES = 10000;

  private const MAX_ENTRY_UNCOMPRESSED_BYTES = 10 * 1024 * 1024;

  private const MAX_TOTAL_UNCOMPRESSED_BYTES = 250 * 1024 * 1024;

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

    if ($archive->numFiles > self::MAX_ARCHIVE_ENTRIES) {
      $archive->close();

      throw new UpdateException('The downloaded update package exceeds the safe archive entry limit.');
    }

    $normalizedEntries = [];
    $totalUncompressedBytes = 0;

    for ($index = 0; $index < $archive->numFiles; $index++) {
      $entryName = (string) $archive->getNameIndex($index);
      $normalizedPath = $this->normalizeEntryPath($entryName);

      if ($normalizedPath === null) {
        continue;
      }

      if (isset($normalizedEntries[$normalizedPath])) {
        $archive->close();

        throw new UpdateException('The downloaded update package contains duplicate or ambiguous paths.', 'Duplicate archive path: '.$normalizedPath.'.');
      }

      $normalizedEntries[$normalizedPath] = true;

      if ($this->entryIsSymlink($archive, $index)) {
        $archive->close();

        throw new UpdateException('The downloaded update package contains unsupported symbolic links.', 'Symlink archive entry: '.$entryName.'.');
      }

      $entry = $archive->statIndex($index);
      $entrySize = is_array($entry) ? (int) ($entry['size'] ?? 0) : 0;

      if ($entrySize > self::MAX_ENTRY_UNCOMPRESSED_BYTES) {
        $archive->close();

        throw new UpdateException('The downloaded update package contains an unexpectedly large file.', 'Oversized archive entry: '.$entryName.'.');
      }

      $totalUncompressedBytes += $entrySize;

      if ($totalUncompressedBytes > self::MAX_TOTAL_UNCOMPRESSED_BYTES) {
        $archive->close();

        throw new UpdateException('The downloaded update package exceeds the safe extracted-size limit.');
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

  private function entryIsSymlink(ZipArchive $archive, int $index): bool
  {
    $operatingSystem = 0;
    $attributes = 0;

    if (! $archive->getExternalAttributesIndex($index, $operatingSystem, $attributes)) {
      return false;
    }

    return $operatingSystem === ZipArchive::OPSYS_UNIX
      && (($attributes >> 16) & 0170000) === 0120000;
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
      'Package validation failed because a package-style WebBlocks CMS composer.json and src/ were not found at the archive root.',
    );
  }

  private function isValidPackageRoot(string $path): bool
  {
    if (! File::isFile($path.'/composer.json') || ! File::isDirectory($path.'/src')) {
      return false;
    }

    if (File::isFile($path.'/artisan')) {
      return false;
    }

    if (File::isDirectory($path.'/packages/webblocks-cms')) {
      return false;
    }

    try {
      $composer = json_decode((string) File::get($path.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
      return false;
    }

    if (($composer['name'] ?? null) !== 'fklavyenet/webblocks-cms') {
      return false;
    }

    $autoload = $composer['autoload']['psr-4'] ?? [];

    if (($autoload['WebBlocks\\Cms\\'] ?? null) !== 'src/') {
      return false;
    }

    if (array_key_exists('WebBlocks\\Cms\\Database\\Seeders\\', $autoload)
      && ($autoload['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null) !== 'database/seeders/') {
      return false;
    }

    return true;
  }
}
