<?php

namespace App\Support\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class UpdateWorkspaceManager
{
    public function create(): array
    {
        $workspaceRoot = storage_path(trim((string) config('webblocks-updates.installer.workspace_root', 'app/system-updates'), '/'));
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
