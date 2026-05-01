<?php

namespace App\Support\System\Updates;

use Database\Seeders\CoreCatalogSeeder;
use Illuminate\Support\Facades\File;

class UpdateInstaller
{
    public function __construct(
        private readonly UpdateCommandRunner $commandRunner,
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

        if (! File::isDirectory($targetPath)) {
            throw new UpdateException('The application root configured for updates does not exist.', 'Missing update target path: '.$targetPath);
        }

        foreach (File::allFiles($packageRoot) as $file) {
            $sourcePath = $file->getPathname();
            $relativePath = str_replace('\\', '/', ltrim(str_replace($packageRoot, '', $sourcePath), DIRECTORY_SEPARATOR));

            if ($this->shouldSkipPath($relativePath)) {
                continue;
            }

            $destinationPath = $targetPath.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if (File::exists($destinationPath) && ! is_writable($destinationPath)) {
                throw new UpdateException(
                    'The update could not write application files. Check file permissions and try again.',
                    'Write permission denied for '.$relativePath.'.',
                );
            }

            File::ensureDirectoryExists(dirname($destinationPath));

            $temporaryPath = $destinationPath.'.wb-update-tmp';

            if (File::exists($temporaryPath)) {
                File::delete($temporaryPath);
            }

            File::copy($sourcePath, $temporaryPath);

            $permissions = @fileperms($sourcePath);

            if ($permissions !== false) {
                @chmod($temporaryPath, $permissions & 0777);
            }

            if (! @rename($temporaryPath, $destinationPath)) {
                File::delete($temporaryPath);

                throw new UpdateException(
                    'The update could not apply the downloaded package.',
                    'Atomic replace failed for '.$relativePath.'.',
                );
            }

            $output[] = 'Applied '.$relativePath;
        }
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
        foreach ([
            ['migrate', '--force'],
            ['db:seed', '--class='.CoreCatalogSeeder::class, '--force'],
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

    private function shouldSkipPath(string $relativePath): bool
    {
        $normalizedPath = trim(str_replace('\\', '/', $relativePath), '/');

        if ($normalizedPath === '' || $normalizedPath === '.env' || str_starts_with($normalizedPath, '.env.')) {
            return true;
        }

        foreach ((array) config('webblocks-updates.installer.excluded_paths', []) as $excludedPath) {
            $excludedPath = trim(str_replace('\\', '/', (string) $excludedPath), '/');

            if ($excludedPath === '') {
                continue;
            }

            if ($normalizedPath === $excludedPath || str_starts_with($normalizedPath, $excludedPath.'/')) {
                return true;
            }
        }

        return false;
    }
}
