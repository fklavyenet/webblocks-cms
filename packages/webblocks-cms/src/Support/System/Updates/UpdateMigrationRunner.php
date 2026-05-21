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
        $report = $this->strategyReport($targetPath);
        $strategy = $report['strategy'];

        if ($strategy === 'source') {
            $output[] = 'Migration strategy: source-maintained root migrations. Reason: '.$report['reason'];
            $this->commandRunner->run(
                $this->commandRunner->artisanCommand(['migrate', '--force']),
                $targetPath,
                $output,
            );

            return;
        }

        $migrationPath = $this->packageUpdateMigrationsPath($targetPath);

        if (! $this->hasMigrationFiles($migrationPath)) {
            $output[] = 'Migration strategy: package-native update migrations. Reason: '.$report['reason'].'. No package update migrations found; host application migrations were skipped.';

            return;
        }

        $output[] = 'Migration strategy: package-native update migrations at '.$migrationPath.'. Reason: '.$report['reason'].'. Host application migrations were skipped.';
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

    /**
     * @return array{strategy: 'package'|'source', reason: string, package_update_migrations_path: string, has_package_update_migrations: bool}
     */
    public function strategyReport(string $targetPath): array
    {
        $configured = (string) config('webblocks-updates.installer.migration_strategy', 'auto');
        $migrationPath = $this->packageUpdateMigrationsPath($targetPath);

        if (in_array($configured, ['package', 'source'], true)) {
            return [
                'strategy' => $configured,
                'reason' => 'configured by webblocks-updates.installer.migration_strategy',
                'package_update_migrations_path' => $migrationPath,
                'has_package_update_migrations' => $this->hasMigrationFiles($migrationPath),
            ];
        }

        $sourceReason = $this->sourceMaintainedReason($targetPath);

        if ($sourceReason !== null) {
            return [
                'strategy' => 'source',
                'reason' => $sourceReason,
                'package_update_migrations_path' => $migrationPath,
                'has_package_update_migrations' => $this->hasMigrationFiles($migrationPath),
            ];
        }

        return [
            'strategy' => 'package',
            'reason' => 'no source-maintained root Composer autoload authority detected',
            'package_update_migrations_path' => $migrationPath,
            'has_package_update_migrations' => $this->hasMigrationFiles($migrationPath),
        ];
    }

    private function sourceMaintainedReason(string $targetPath): ?string
    {
        $composerPath = rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'composer.json';

        if (! File::isFile($composerPath)) {
            return null;
        }

        try {
            $composer = json_decode((string) File::get($composerPath), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $autoload = $composer['autoload']['psr-4'] ?? [];

        if (($composer['name'] ?? null) !== 'fklavyenet/webblocks-cms') {
            return null;
        }

        if (($autoload['WebBlocks\\Cms\\'] ?? null) !== 'packages/webblocks-cms/src/') {
            return null;
        }

        if (($autoload['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null) !== 'packages/webblocks-cms/database/seeders/') {
            return null;
        }

        if (! File::isDirectory(rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'database/migrations')) {
            return null;
        }

        if (! File::isDirectory(rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'packages/webblocks-cms')) {
            return null;
        }

        return 'root composer.json owns WebBlocks\\Cms\\ autoloading from packages/webblocks-cms/src/';
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
