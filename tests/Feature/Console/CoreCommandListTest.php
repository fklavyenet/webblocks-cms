<?php

namespace Tests\Feature\Console;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CoreCommandListTest extends TestCase
{
    private ?string $projectLayerBackupPath = null;

    protected function setUp(): void
    {
        $this->isolateProjectLayerBeforeBoot();

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->restoreProjectLayerAfterBoot();
    }

    #[Test]
    public function core_command_list_does_not_expose_website_specific_project_commands(): void
    {
        $removedSyncCommand = 'webblocks:'.implode('-', ['sync', 'ui', 'docs', 'pilot']);
        $removedRebuildCommand = 'webblocks:'.implode('-', ['rebuild', 'ui', 'docs']);
        $removedHomeMainCommand = 'webblocks:'.implode('-', ['sync', 'ui', 'docs', 'home', 'main']);
        $projectGettingStartedCommand = 'webblocks:'.implode('-', ['sync', 'ui', 'docs', 'getting', 'started']);
        $projectNavigationCommand = implode(':', ['project', implode('-', ['sync', 'ui', 'docs', 'navigation'])]);

        $this->artisan('list')
            ->doesntExpectOutputToContain($removedSyncCommand)
            ->doesntExpectOutputToContain($removedRebuildCommand)
            ->doesntExpectOutputToContain($removedHomeMainCommand)
            ->doesntExpectOutputToContain($projectGettingStartedCommand)
            ->doesntExpectOutputToContain($projectNavigationCommand)
            ->expectsOutputToContain('project:init')
            ->assertExitCode(0);
    }

    private function isolateProjectLayerBeforeBoot(): void
    {
        $projectPath = base_path('project');

        if (! is_dir($projectPath)) {
            return;
        }

        $backupPath = storage_path('app/testing-project-layer/core-command-list-backup');
        if (! is_dir(dirname($backupPath)) && ! @mkdir(dirname($backupPath), 0755, true) && ! is_dir(dirname($backupPath))) {
            throw new \RuntimeException('Failed to create the temporary project-layer backup directory.');
        }

        if (is_dir($backupPath)) {
            $this->deleteDirectory($backupPath);
        }

        if (! @rename($projectPath, $backupPath)) {
            throw new \RuntimeException('Failed to isolate the project layer before booting the application.');
        }

        $this->projectLayerBackupPath = $backupPath;
    }

    private function restoreProjectLayerAfterBoot(): void
    {
        $projectPath = base_path('project');

        if (is_dir($projectPath)) {
            $this->deleteDirectory($projectPath);
        }

        if ($this->projectLayerBackupPath && is_dir($this->projectLayerBackupPath)) {
            @rename($this->projectLayerBackupPath, $projectPath);
        }

        $this->projectLayerBackupPath = null;
    }

    private function deleteDirectory(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $items = scandir($path);

        if ($items === false) {
            throw new \RuntimeException(sprintf('Failed to read directory [%s] for cleanup.', $path));
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $path.DIRECTORY_SEPARATOR.$item;

            if (is_dir($itemPath) && ! is_link($itemPath)) {
                $this->deleteDirectory($itemPath);

                continue;
            }

            if (! @unlink($itemPath) && file_exists($itemPath)) {
                throw new \RuntimeException(sprintf('Failed to delete file [%s] during cleanup.', $itemPath));
            }
        }

        if (! @rmdir($path) && is_dir($path)) {
            throw new \RuntimeException(sprintf('Failed to delete directory [%s] during cleanup.', $path));
        }
    }
}
