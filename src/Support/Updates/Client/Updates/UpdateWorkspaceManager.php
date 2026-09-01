<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

namespace WebBlocks\Cms\Support\Updates\Client\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Creates and tears down a per-run scratch workspace (download + extract dirs)
 * under storage.
 */
class UpdateWorkspaceManager
{
  public function create(): array
  {
    $workspaceRoot = storage_path(trim((string) config('publisher-client.apply.workspace_root', 'app/publisher-client'), '/'));
    $runDirectory = $workspaceRoot.'/'.now()->format('Ymd-His').'-'.Str::uuid();
    $archiveDirectory = $runDirectory.'/download';
    $extractDirectory = $runDirectory.'/extract';

    File::ensureDirectoryExists($archiveDirectory);
    File::ensureDirectoryExists($extractDirectory);

    return [
      'root' => $runDirectory,
      'archive' => $archiveDirectory.'/package.zip',
      'extract' => $extractDirectory,
    ];
  }

  public function cleanup(?string $workspacePath): void
  {
    if (! is_string($workspacePath) || $workspacePath === '' || ! File::exists($workspacePath)) {
      return;
    }

    File::deleteDirectory($workspacePath);
  }
}
