<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

trait InteractsWithProjectLayerFiles
{
    private ?string $projectLayerBackupPath = null;

    private function isolateProjectLayer(): void
    {
        $projectPath = base_path('project');

        if (! is_dir($projectPath)) {
            return;
        }

        $backupPath = storage_path('app/testing-project-layer/project-backup-'.Str::uuid());
        File::ensureDirectoryExists(dirname($backupPath));

        if (! @rename($projectPath, $backupPath)) {
            throw new \RuntimeException('Failed to isolate the existing project layer directory for testing.');
        }

        $this->projectLayerBackupPath = $backupPath;
    }

    private function restoreProjectLayer(): void
    {
        $projectPath = base_path('project');

        if (is_dir($projectPath)) {
            File::deleteDirectory($projectPath);
        }

        if ($this->projectLayerBackupPath && is_dir($this->projectLayerBackupPath)) {
            @rename($this->projectLayerBackupPath, $projectPath);
        }

        $this->projectLayerBackupPath = null;
    }

    private function writeProjectFile(string $relativePath, string $contents): void
    {
        $path = base_path('project/'.$relativePath);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $contents);
    }
}
