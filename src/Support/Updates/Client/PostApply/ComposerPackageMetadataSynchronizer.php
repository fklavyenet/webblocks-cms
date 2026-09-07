<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\PostApply;

use Composer\InstalledVersions;
use Illuminate\Support\Facades\File;
use JsonException;
use WebBlocks\Cms\Support\Updates\Client\Contracts\ApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/**
 * Keeps Composer's generated package registry aligned after a package-scoped
 * update replaces vendor code without running composer update/install.
 */
final class ComposerPackageMetadataSynchronizer
{
    /** @var array<string, string|null>|null */
    private ?array $snapshot = null;

    public function __construct(
        private readonly ApplyStrategy $strategy,
        private readonly VersionResolver $versions,
        private readonly UpdateCommandRunner $commandRunner,
    ) {
    }

    /** @param list<string> $output */
    public function synchronize(string $commandDir, array &$output): void
    {
        if ($this->strategy->name() !== 'package'
            || ! (bool) config('publisher-client.apply.sync_composer_metadata', true)) {
            return;
        }

        $package = trim((string) config('publisher-client.package.name', ''));
        $version = $this->versions->appliedVersion($this->strategy->targetRoot());

        if ($package === '' || $version === null || trim($version) === '') {
            throw new UpdateException(
                'The updated package version could not be synchronized with Composer metadata.',
                'Package name or applied package version is unavailable.',
            );
        }

        $version = trim($version);
        $this->snapshot = $this->snapshotMetadata($commandDir);
        $changed = [];

        if ($this->synchronizeRootRequirement($commandDir.'/composer.json', $package, $version)) {
            $changed[] = 'composer.json';
        }

        foreach (['composer.lock', 'vendor/composer/installed.json'] as $relative) {
            if ($this->synchronizeJson($commandDir.'/'.$relative, $package, $version)) {
                $changed[] = $relative;
            }
        }

        $this->commandRunner->run(
            $this->commandRunner->composerCommand(['dump-autoload', '--optimize', '--no-interaction']),
            $commandDir,
            $output,
        );

        $installed = $this->installedPhpData($commandDir);
        $installed = $this->synchronizeInstalledPhp($commandDir, $installed, $package, $version);
        $this->assertInstalledPhp($installed, $package, $version);
        InstalledVersions::reload($installed);

        $output[] = 'Synchronized Composer package metadata for '.$package.' '.$version
            .($changed === [] ? '.' : ' ('.implode(', ', $changed).').');
    }

    /** @param list<string> $output */
    public function rollback(array &$output): void
    {
        if ($this->snapshot === null) {
            return;
        }

        foreach ($this->snapshot as $path => $contents) {
            if ($contents === null) {
                File::delete($path);

                continue;
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $contents);
        }

        $this->snapshot = null;
        $output[] = 'Restored Composer package metadata after the failed update.';
    }

    public function commit(): void
    {
        $this->snapshot = null;
    }

    private function synchronizeJson(string $path, string $packageName, string $version): bool
    {
        if (! File::isFile($path)) {
            return false;
        }

        try {
            $document = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UpdateException(
                'Composer package metadata could not be synchronized.',
                'Invalid JSON in '.$path.': '.$exception->getMessage(),
            );
        }

        if (! is_array($document)) {
            return false;
        }

        $original = $document;
        $lists = array_is_list($document) ? [null] : ['packages', 'packages-dev'];

        foreach ($lists as $list) {
            $packages = $list === null ? $document : ($document[$list] ?? null);

            if (! is_array($packages)) {
                continue;
            }

            foreach ($packages as $index => $metadata) {
                if (! is_array($metadata) || ($metadata['name'] ?? null) !== $packageName) {
                    continue;
                }

                $metadata['version'] = $this->normalizedVersion($version);
                $metadata['pretty_version'] = $version;

                if (array_key_exists('version_normalized', $metadata)) {
                    $metadata['version_normalized'] = $this->normalizedVersion($version);
                }

                if ($list === null) {
                    $document[$index] = $metadata;
                } else {
                    $document[$list][$index] = $metadata;
                }
            }
        }

        if ($document === $original) {
            return false;
        }

        File::put($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        return true;
    }

    /** @return array<string, mixed> */
    private function installedPhpData(string $commandDir): array
    {
        $path = $commandDir.'/vendor/composer/installed.php';

        if (! File::isFile($path)) {
            throw new UpdateException('Composer runtime metadata was not regenerated.', 'Missing vendor/composer/installed.php.');
        }

        if (function_exists('opcache_invalidate')) {
            opcache_invalidate($path, true);
        }

        $installed = (static fn (string $file): mixed => require $file)($path);

        if (! is_array($installed)) {
            throw new UpdateException('Composer runtime metadata was not regenerated.', 'Invalid vendor/composer/installed.php data.');
        }

        return $installed;
    }

    /** @param array<string, mixed> $installed */
    private function assertInstalledPhp(array $installed, string $packageName, string $version): void
    {
        $metadata = is_array($installed) ? ($installed['versions'][$packageName] ?? null) : null;
        $actual = is_array($metadata) ? ($metadata['version'] ?? $metadata['pretty_version'] ?? null) : null;

        if (! is_string($actual) || $this->normalizedVersion($actual) !== $this->normalizedVersion($version)) {
            throw new UpdateException(
                'Composer runtime metadata still reports the previous package version.',
                'Expected '.$packageName.' '.$version.' in vendor/composer/installed.php; found '.(is_string($actual) ? $actual : 'unknown').'.',
            );
        }
    }

    /**
     * Composer's dump-autoload command does not rewrite installed.php. Keep the
     * generated runtime registry aligned explicitly after a package replacement.
     *
     * @param array<string, mixed> $installed
     * @return array<string, mixed>
     */
    private function synchronizeInstalledPhp(
        string $commandDir,
        array $installed,
        string $packageName,
        string $version,
    ): array {
        $metadata = $installed['versions'][$packageName] ?? null;

        if (! is_array($metadata)) {
            throw new UpdateException(
                'Composer runtime metadata could not be synchronized.',
                'Missing '.$packageName.' in vendor/composer/installed.php.',
            );
        }

        $metadata['pretty_version'] = $version;
        $metadata['version'] = $this->normalizedVersion($version);
        $installed['versions'][$packageName] = $metadata;

        $path = $commandDir.'/vendor/composer/installed.php';
        File::put($path, '<?php return '.var_export($installed, true).';'.PHP_EOL);

        return $this->installedPhpData($commandDir);
    }

    private function normalizedVersion(string $version): string
    {
        $segments = explode('.', preg_replace('/[^0-9.].*$/', '', $version) ?: $version);

        while (count($segments) < 4) {
            $segments[] = '0';
        }

        return implode('.', $segments);
    }

    private function synchronizeRootRequirement(string $path, string $packageName, string $version): bool
    {
        if (! File::isFile($path)) {
            return false;
        }

        try {
            $document = json_decode((string) File::get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new UpdateException('Composer package metadata could not be synchronized.', 'Invalid JSON in '.$path.': '.$exception->getMessage());
        }

        $constraint = is_array($document) ? ($document['require'][$packageName] ?? null) : null;

        if (! is_string($constraint) || preg_match('/^v?\d+(?:\.\d+){1,3}$/', trim($constraint)) !== 1) {
            return false;
        }

        $document['require'][$packageName] = $version;
        File::put($path, json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        return true;
    }

    /** @return array<string, string|null> */
    private function snapshotMetadata(string $commandDir): array
    {
        $paths = [$commandDir.'/composer.json', $commandDir.'/composer.lock'];
        $composerDir = $commandDir.'/vendor/composer';

        if (File::isDirectory($composerDir)) {
            foreach (File::allFiles($composerDir) as $file) {
                $paths[] = $file->getPathname();
            }
        }

        $snapshot = [];

        foreach ($paths as $path) {
            $snapshot[$path] = File::isFile($path) ? (string) File::get($path) : null;
        }

        return $snapshot;
    }
}
