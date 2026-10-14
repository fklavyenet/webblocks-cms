<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/** Retain the package tree until the whole update, including migrations, finishes. */
class CmsPackageRollback
{
  private ?string $target = null;

  private ?string $snapshot = null;

  public function capture(string $target): void
  {
    if ($this->snapshot !== null) {
      throw new RuntimeException('An update package snapshot is already in use.');
    }
    $snapshot = $target.'.cms-rollback-'.Str::uuid();
    if (! File::isDirectory($target) || ! File::copyDirectory($target, $snapshot)) {
      File::deleteDirectory($snapshot);
      throw new RuntimeException('The pre-update package could not be preserved.');
    }
    $this->target = $target;
    $this->snapshot = $snapshot;
  }

  public function restore(): bool
  {
    if ($this->snapshot === null) {
      return false;
    }
    $displaced = $this->target.'.cms-failed-'.Str::uuid();
    if (! @rename($this->target, $displaced)) {
      throw new RuntimeException('The failed update package could not be moved for restoration.');
    }
    if (! @rename($this->snapshot, $this->target)) {
      @rename($displaced, $this->target);
      throw new RuntimeException('The pre-update package could not be restored.');
    }
    // A restored copy may have an older timestamp than the opcode cached by
    // PHP-FPM while the new package was being applied.
    foreach (File::allFiles($this->target) as $file) {
      if ($file->getExtension() === 'php') {
        touch($file->getPathname());
      }
    }
    File::deleteDirectory($displaced);
    $this->target = $this->snapshot = null;

    return true;
  }

  public function commit(): void
  {
    if ($this->snapshot !== null) {
      File::deleteDirectory($this->snapshot);
    }
    $this->target = $this->snapshot = null;
  }
}
