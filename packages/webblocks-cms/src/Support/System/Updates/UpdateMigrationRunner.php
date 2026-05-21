<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;

class UpdateMigrationRunner
{
    private const PACKAGE_UPDATE_MIGRATIONS_PATH = 'packages/webblocks-cms/database/migrations/updates';

    public function __construct(
        private readonly UpdateCommandRunner $commandRunner,
    ) {}

    public function run(string $targetPath, array &$output): void
    {
        $strategy = $this->strategy($targetPath);

        if ($strategy === 'source') {
            $output[] = 'Migration strategy: source-maintained root migrations.';
            $this->commandRunner->run(
                $this->commandRunner->artisanCommand(['migrate', '--force']),
                $targetPath,
                $output,
            );

            return;
        }

        $migrationPath = $this->packageUpdateMigrationsPath($targetPath);

        if (! $this->hasMigrationFiles($migrationPath)) {
            $output[] = 'Migration strategy: package-native update migrations. No package update migrations found; host application migrations were skipped.';

            return;
        }

        $output[] = 'Migration strategy: package-native update migrations at '.$migrationPath.'. Host application migrations were skipped.';
        $this->commandRunner->run(
            $this->commandRunner->artisanCommand([
                'migrate',
                '--path='.$migrationPath,
                '--realpath',
                '--force',
            ]),
            $targetPath,
            $output,
        );
    }

    private function strategy(string $targetPath): string
    {
        $configured = (string) config('webblocks-updates.installer.migration_strategy', 'auto');

        if (in_array($configured, ['package', 'source'], true)) {
            return $configured;
        }

        return $this->isSourceMaintainedInstall($targetPath) ? 'source' : 'package';
    }

    private function isSourceMaintainedInstall(string $targetPath): bool
    {
        $composerPath = rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        if (! File::isFile($composerPath)) {
            return false;
        }

        try {
            $composer = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        return ($composer['name'] ?? null) === 'fklavyenet/webblocks-cms'
          && File::isDirectory(rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database/migrations')
          && File::isDirectory(rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'packages/webblocks-cms');
    }

    private function packageUpdateMigrationsPath(string $targetPath): string
    {
        $relativePath = (string) config(
            'webblocks-updates.installer.package_update_migrations_path',
            self::PACKAGE_UPDATE_MIGRATIONS_PATH,
        );

        return rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, trim($relativePath, '/'));
    }

    private function hasMigrationFiles(string $migrationPath): bool
    {
        if (! File::isDirectory($migrationPath)) {
            return false;
        }

        return collect(File::files($migrationPath))
            ->contains(fn (\SplFileInfo $file): bool => $file->getExtension() === 'php');
    }
}
