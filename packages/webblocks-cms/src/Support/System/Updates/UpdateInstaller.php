<?php

namespace WebBlocks\Cms\Support\System\Updates;

use App\Support\Install\InstallationGitRemoteGuard;
use Database\Seeders\CoreCatalogSeeder;
use Illuminate\Support\Facades\File;

class UpdateInstaller
{
    private const PACKAGE_RUNTIME_PATH = 'packages/webblocks-cms';

    public function __construct(
        private readonly UpdateCommandRunner $commandRunner,
        private readonly UpdateMigrationRunner $migrationRunner,
        private readonly InstallationGitRemoteGuard $installationGitRemoteGuard,
    ) {}

    public function enterMaintenance(array &$output): void
    {
        $command = $this->commandRunner->artisanCommand([
            'down',
            '--render=errors::503',
        ]);

        $output[] = 'Using PHP binary: '.$command[0];

        $this->commandRunner->run($command, $this->targetPath(), $output);
    }

    public function applyPackage(string $packageRoot, array &$output): void
    {
        $targetPath = $this->targetPath();
        $packageRuntimePath = $this->packageRuntimePath($targetPath);
        $stagingPath = $packageRuntimePath.'.wb-update-new';
        $backupPath = $packageRuntimePath.'.wb-update-old';

        if (! File::isDirectory($targetPath)) {
            throw new UpdateException('The application root configured for updates does not exist.', 'Missing update target path: '.$targetPath);
        }

        if (! File::isDirectory($packageRoot)) {
            throw new UpdateException('The downloaded update package could not be applied.', 'Validated package root is missing: '.$packageRoot);
        }

        $this->assertSafePackageRuntimePath($targetPath, $packageRuntimePath);
        $this->assertSafePackageContents($packageRoot);

        File::deleteDirectory($stagingPath);
        File::deleteDirectory($backupPath);
        File::ensureDirectoryExists(dirname($packageRuntimePath));

        if (! File::copyDirectory($packageRoot, $stagingPath)) {
            throw new UpdateException('The update could not apply the downloaded package.', 'Failed to stage package contents into '.$stagingPath.'.');
        }

        if (File::exists($packageRuntimePath)) {
            if (! File::isDirectory($packageRuntimePath)) {
                File::deleteDirectory($stagingPath);

                throw new UpdateException('The update could not apply the downloaded package.', 'Package runtime path is not a directory: '.$packageRuntimePath);
            }

            if (! @rename($packageRuntimePath, $backupPath)) {
                File::deleteDirectory($stagingPath);

                throw new UpdateException(
                    'The update could not write application files. Check file permissions and try again.',
                    'Failed to move existing package runtime out of the way: '.$packageRuntimePath,
                );
            }
        }

        if (! @rename($stagingPath, $packageRuntimePath)) {
            File::deleteDirectory($stagingPath);

            if (File::isDirectory($backupPath)) {
                @rename($backupPath, $packageRuntimePath);
            }

            throw new UpdateException('The update could not apply the downloaded package.', 'Failed to replace package runtime at '.$packageRuntimePath.'.');
        }

        File::deleteDirectory($backupPath);

        $output[] = 'Replaced '.self::PACKAGE_RUNTIME_PATH.' with package artifact contents.';
    }

    public function installDependencies(array &$output): void
    {
        $this->commandRunner->run([
            'composer',
            'install',
            '--no-interaction',
            '--prefer-dist',
            '--optimize-autoloader',
        ], $this->targetPath(), $output);
    }

    public function runPostInstallCommands(array &$output): void
    {
        $this->migrationRunner->run($this->targetPath(), $output);

        foreach ([
            ['db:seed', '--class='.CoreCatalogSeeder::class, '--force'],
            ['block-types:sync-core', '--force'],
            ['config:clear'],
            ['view:clear'],
            ['cache:clear'],
            ['route:clear'],
        ] as $artisanCommand) {
            $this->commandRunner->run(
                $this->commandRunner->artisanCommand($artisanCommand),
                $this->targetPath(),
                $output,
            );
        }

        $this->installationGitRemoteGuard->protectCurrentInstall($this->targetPath(), $output);
    }

    public function leaveMaintenance(array &$output): void
    {
        $this->commandRunner->run(
            $this->commandRunner->artisanCommand(['up']),
            $this->targetPath(),
            $output,
        );
    }

    public function targetPath(): string
    {
        return (string) config('webblocks-updates.installer.target_path', base_path());
    }

    private function packageRuntimePath(string $targetPath): string
    {
        return rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PACKAGE_RUNTIME_PATH);
    }

    private function assertSafePackageRuntimePath(string $targetPath, string $packageRuntimePath): void
    {
        $normalizedTargetPath = rtrim(str_replace('\\', '/', $targetPath), '/');
        $normalizedPackageRuntimePath = rtrim(str_replace('\\', '/', $packageRuntimePath), '/');
        $expectedPath = $normalizedTargetPath.'/'.self::PACKAGE_RUNTIME_PATH;

        if ($normalizedPackageRuntimePath !== $expectedPath) {
            throw new UpdateException('The update could not apply the downloaded package.', 'Refusing to apply package outside '.self::PACKAGE_RUNTIME_PATH.'.');
        }
    }

    private function assertSafePackageContents(string $packageRoot): void
    {
        foreach (File::allFiles($packageRoot, true) as $file) {
            $relativePath = trim(str_replace('\\', '/', str_replace($packageRoot, '', $file->getPathname())), '/');

            if ($relativePath === '') {
                continue;
            }

            if (preg_match('/(^|\/)\.\.(\/|$)/', $relativePath) === 1 || str_starts_with($relativePath, '/')) {
                throw new UpdateException('The downloaded update package contains invalid paths.', 'Refusing to apply package file outside '.self::PACKAGE_RUNTIME_PATH.': '.$relativePath);
            }
        }
    }
}
