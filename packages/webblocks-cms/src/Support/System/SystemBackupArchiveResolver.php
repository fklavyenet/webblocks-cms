<?php

namespace WebBlocks\Cms\Support\System;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupArchiveResolver
{
  public function archiveDisk(): FilesystemAdapter
  {
    $configuredRoot = config('filesystems.disks.'.SystemBackupManager::ARCHIVE_DISK.'.root');
    $archiveRoot = is_string($configuredRoot) && trim($configuredRoot) !== ''
      ? $configuredRoot
      : storage_path('app/backups');

    config()->set('filesystems.disks.'.SystemBackupManager::ARCHIVE_DISK, array_merge(
      [
        'driver' => 'local',
        'root' => $archiveRoot,
        'throw' => false,
        'report' => false,
      ],
      (array) config('filesystems.disks.'.SystemBackupManager::ARCHIVE_DISK, [])
    ));

    File::ensureDirectoryExists($archiveRoot);

    return Storage::disk(SystemBackupManager::ARCHIVE_DISK);
  }

  public function assertValidArchivePath(string $path): void
  {
    $resolution = $this->resolvePath($path, requireExistingFile: false, requireReadableFile: false);

    if ($resolution->isUnsafe()) {
      throw new \RuntimeException($resolution->feedbackMessage());
    }
  }

  public function resolveForBackup(SystemBackup $backup, bool $requireReadableFile = true): SystemBackupArchiveResolution
  {
    if (! $backup->isSuccessful() || $backup->archive_path === null || $backup->archive_filename === null) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNAVAILABLE,
        message: 'Backup archive is unavailable.',
      );
    }

    return $this->resolvePath((string) $backup->archive_path, requireReadableFile: $requireReadableFile);
  }

  public function resolvePath(
    string $storedPath,
    bool $requireExistingFile = true,
    bool $requireReadableFile = true,
  ): SystemBackupArchiveResolution {
    $storedPath = trim($storedPath);

    if ($storedPath === '' || str_contains($storedPath, "\0")) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNSAFE,
        message: 'Backup archive path is invalid.',
      );
    }

    $disk = $this->archiveDisk();
    $root = realpath($disk->path(''));

    if ($root === false) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNAVAILABLE,
        message: 'Backup archive storage is unavailable.',
      );
    }

    $candidate = $this->candidateAbsolutePath($disk, $storedPath);
    $relativePath = $this->relativePath($root, $candidate);

    if ($relativePath === null || $this->hasInvalidRelativePath($relativePath)) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNSAFE,
        message: 'Backup archive path is invalid.',
      );
    }

    if (! file_exists($candidate)) {
      return $requireExistingFile
        ? new SystemBackupArchiveResolution(
          SystemBackupArchiveResolution::STATUS_MISSING,
          relativePath: $relativePath,
          message: 'Backup file not found.',
        )
        : new SystemBackupArchiveResolution(
          SystemBackupArchiveResolution::STATUS_AVAILABLE,
          absolutePath: $candidate,
          relativePath: $relativePath,
        );
    }

    $resolvedPath = realpath($candidate);

    if ($resolvedPath === false || ! $this->isInsideRoot($resolvedPath, $root)) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNSAFE,
        message: 'Backup archive path is invalid.',
      );
    }

    if (! is_file($resolvedPath)) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNSAFE,
        relativePath: $relativePath,
        message: 'Backup archive path is invalid.',
      );
    }

    if ($requireReadableFile && ! is_readable($resolvedPath)) {
      return new SystemBackupArchiveResolution(
        SystemBackupArchiveResolution::STATUS_UNREADABLE,
        absolutePath: $resolvedPath,
        relativePath: $relativePath,
        message: 'Backup file is not readable.',
      );
    }

    return new SystemBackupArchiveResolution(
      SystemBackupArchiveResolution::STATUS_AVAILABLE,
      absolutePath: $resolvedPath,
      relativePath: $relativePath,
    );
  }

  private function candidateAbsolutePath(FilesystemAdapter $disk, string $storedPath): string
  {
    if ($this->isAbsolutePath($storedPath)) {
      return $storedPath;
    }

    return $disk->path($storedPath);
  }

  private function relativePath(string $root, string $candidate): ?string
  {
    $normalizedRoot = rtrim($this->normalizePath($root), '/');
    $normalizedCandidate = $this->normalizePath($candidate);

    if (! str_starts_with($normalizedCandidate, $normalizedRoot.'/') && $normalizedCandidate !== $normalizedRoot) {
      return null;
    }

    return ltrim(substr($normalizedCandidate, strlen($normalizedRoot)), '/');
  }

  private function isAbsolutePath(string $path): bool
  {
    return str_starts_with($path, '/')
      || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
  }

  private function isInsideRoot(string $path, string $root): bool
  {
    $normalizedPath = rtrim($this->normalizePath($path), '/');
    $normalizedRoot = rtrim($this->normalizePath($root), '/');

    return $normalizedPath === $normalizedRoot
      || str_starts_with($normalizedPath, $normalizedRoot.'/');
  }

  private function normalizePath(string $path): string
  {
    return str_replace('\\', '/', $path);
  }

  private function hasInvalidRelativePath(string $path): bool
  {
    return str_contains($path, '..')
      || str_starts_with($path, '/')
      || preg_match('/^[A-Za-z]:[\/\\\\]/', $path) === 1;
  }
}
