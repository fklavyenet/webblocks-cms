<?php

// Generated from the shared Publisher Client runtime. Do not edit directly.

declare(strict_types=1);

namespace WebBlocks\Cms\Support\Updates\Client\Apply;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Updates\Client\Contracts\TransactionalApplyStrategy;
use WebBlocks\Cms\Support\Updates\Client\Support\Version\VersionResolver;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateCommandRunner;
use WebBlocks\Cms\Support\Updates\Client\Updates\UpdateException;

/** Composer owns resolution, lock data, installation and generated registries. */
class ComposerPackageApplyStrategy extends AbstractApplyStrategy implements TransactionalApplyStrategy
{
    private ?string $snapshot = null;
    private ?string $preparedRoot = null;
    private ?string $version = null;
    private string $composerBefore = '';
    private string $lockBefore = '';
    private bool $advancePin = false;

    public function __construct(
        private readonly UpdateCommandRunner $commands,
        private readonly VersionResolver $versions,
    ) {}

    public function name(): string
    {
        return 'composer-package';
    }

    public function targetRoot(): string
    {
        return rtrim((string) config('publisher-client.apply.target_path', ''), '/\\');
    }

    public function prepare(string $stagedRoot, array &$output): void
    {
        if ($this->snapshot !== null) {
            throw $this->failure('An unfinished package recovery must be resolved before updating.');
        }
        $this->assertSafeStagedContents($stagedRoot);
        PackageArtifactValidator::fromConfig()->validate($stagedRoot);
        $package = $this->package();
        if ($this->composerName($stagedRoot) !== $package) {
            throw $this->failure('The downloaded update package does not match this product.');
        }
        $version = $this->versions->appliedVersion($stagedRoot);
        if (! is_string($version) || preg_match('/^\d+\.\d+\.\d+$/D', $version) !== 1) {
            throw $this->failure('The verified package does not declare a supported stable version.');
        }
        $root = $this->root();
        if ((getenv('COMPOSER') ?: 'composer.json') !== 'composer.json'
            || ! in_array(getenv('COMPOSER_VENDOR_DIR') ?: 'vendor', ['vendor', $root.'/vendor'], true)) {
            throw $this->failure('Custom Composer document or vendor environment overrides require a manual deployment.');
        }
        if (glob($root.'/.publisher-recovery-*') !== []) {
            throw $this->failure('A previous dependency recovery copy requires operator review before another update.');
        }
        if (is_link($root.'/bootstrap/cache')) {
            throw $this->failure('A linked bootstrap cache requires a manual deployment.');
        }
        $expected = $root.'/vendor/'.$package;
        if (is_link($root.'/vendor') || is_link($root.'/vendor/'.explode('/', $package)[0])
            || is_link($root.'/vendor/composer') || is_link($root.'/vendor/composer/installed.json')
            || is_link($this->targetRoot())
            || realpath($this->targetRoot()) === false
            || realpath($expected) !== realpath($this->targetRoot())) {
            throw $this->failure('Composer updates require the installed vendor package; source and linked installations are preserved.');
        }
        $composer = $this->json($root.'/composer.json');
        $this->json($root.'/composer.lock');
        if (! isset($composer['require'][$package])
            || ($composer['config']['vendor-dir'] ?? 'vendor') !== 'vendor'
            || is_link($root.'/composer.json') || is_link($root.'/composer.lock')) {
            throw $this->failure('The host must directly require this package in a standard Composer installation.');
        }
        $installed = $this->json($root.'/vendor/composer/installed.json');
        $entry = $this->packages($installed)[$package] ?? null;
        if (! is_array($entry) || ($entry['installation-source'] ?? '') !== 'dist'
            || ($entry['dist']['type'] ?? '') === 'path') {
            throw $this->failure('Composer source, path and linked installations require their own deployment workflow.');
        }
        $this->composerBefore = File::get($root.'/composer.json');
        $this->lockBefore = File::get($root.'/composer.lock');
        $this->advancePin = (bool) config('publisher-client.apply.composer_advance_exact_pin', false)
            && preg_match('/^v?\d+\.\d+\.\d+$/D', (string) $composer['require'][$package]) === 1;
        $this->version = $version;
        $this->preparedRoot = null;
        $this->commands->run($this->commands->composerCommand($this->updateArguments(dryRun: true)), $root, $output);
        $this->assertHostUnchanged();
        $this->preparedRoot = $stagedRoot;
    }

    public function apply(string $stagedRoot): array
    {
        $output = [];
        if ($this->preparedRoot !== $stagedRoot) {
            $this->prepare($stagedRoot, $output);
        }
        $this->assertHostUnchanged();
        $root = $this->root();
        // A sibling snapshot permits atomic directory restoration on the same disk.
        $snapshot = $this->recoveryDirectory();
        File::ensureDirectoryExists($snapshot, 0700);
        try {
            $this->copyTree($root.'/vendor', $snapshot.'/vendor');
            if (File::isDirectory($root.'/bootstrap/cache')) {
                $this->copyTree($root.'/bootstrap/cache', $snapshot.'/bootstrap-cache');
            }
            if (! File::copy($root.'/composer.json', $snapshot.'/composer.json')
                || ! File::copy($root.'/composer.lock', $snapshot.'/composer.lock')) {
                throw $this->failure('Composer recovery files could not be copied.');
            }
        } catch (\Throwable $exception) {
            File::deleteDirectory($snapshot);
            throw $this->failure('The complete dependency recovery copy could not be created.', previous: $exception);
        }
        $this->snapshot = $snapshot;
        $this->commands->run($this->commands->composerCommand($this->updateArguments()), $root, $output);
        $expectedRequirements = json_decode($this->composerBefore, true, flags: JSON_THROW_ON_ERROR);
        if ($this->advancePin) {
            $expectedRequirements['require'][$this->package()] = $this->version;
        }
        if ($this->json($root.'/composer.json') != $expectedRequirements) {
            throw $this->failure('The host Composer requirements changed outside the approved package pin.');
        }
        $this->assertDependencyScope();
        if ((bool) config('publisher-client.apply.composer_lock_only', false)) {
            $this->assertUnrelatedInstalledPackagesMatchLock();
            $arguments = ['install', '--prefer-dist', '--no-interaction', '--no-progress', '--no-scripts', '--no-plugins', '--optimize-autoloader'];
            if (($this->json($root.'/vendor/composer/installed.json')['dev'] ?? true) === false) {
                $arguments[] = '--no-dev';
            }
            $this->commands->run($this->commands->composerCommand($arguments), $root, $output);
        }
        // Reinstall also repairs older installations whose version was relabelled
        // while their source/dist references still pointed at a previous release.
        $this->commands->run($this->commands->composerCommand([
            'reinstall', $this->package(), '--prefer-dist', '--no-interaction',
            '--no-progress', '--no-scripts', '--no-plugins', '--optimize-autoloader',
        ]), $root, $output);
        $this->assertVerifiedFiles($stagedRoot);
        $this->assertInstalledMetadata();
        $output[] = 'Composer installed the verified package and resolved dependencies while preserving host version ranges and unrelated requirements.';

        return ['strategy' => $this->name(), 'target' => $this->targetRoot(), 'output' => $output];
    }

    public function rollback(array &$output): void
    {
        if ($this->snapshot === null) {
            return;
        }
        $root = $this->root();
        $failed = $this->snapshot.'/failed-vendor';
        if (File::exists($root.'/vendor') && ! @rename($root.'/vendor', $failed)) {
            throw $this->failure('Dependency recovery could not move the failed vendor directory. The recovery copy was retained.');
        }
        if (! @rename($this->snapshot.'/vendor', $root.'/vendor')) {
            @rename($failed, $root.'/vendor');
            throw $this->failure('Dependency recovery could not restore vendor. The recovery copy was retained.');
        }
        if (File::exists($root.'/bootstrap/cache')
            && ! @rename($root.'/bootstrap/cache', $this->snapshot.'/failed-bootstrap-cache')) {
            throw $this->failure('Bootstrap cache recovery failed. The recovery copy was retained.');
        }
        if (File::isDirectory($this->snapshot.'/bootstrap-cache')
            && ! @rename($this->snapshot.'/bootstrap-cache', $root.'/bootstrap/cache')) {
            throw $this->failure('Bootstrap cache recovery failed. The recovery copy was retained.');
        }
        if (File::put($root.'/composer.json', $this->composerBefore) === false
            || File::put($root.'/composer.lock', $this->lockBefore) === false) {
            throw $this->failure('Composer recovery files could not be restored. The recovery copy was retained.');
        }
        foreach (File::allFiles($root.'/vendor') as $file) {
            if ($file->getExtension() === 'php' && ! $file->isLink()) {
                touch($file->getPathname());
            }
        }
        $this->commit();
        $output[] = 'Restored the complete vendor tree and original Composer files.';
    }

    public function commit(): void
    {
        if ($this->snapshot !== null) {
            try {
                if (! File::deleteDirectory($this->snapshot)) {
                    \Illuminate\Support\Facades\Log::warning('Publisher dependency recovery cleanup is incomplete.');
                }
            } catch (\Throwable) {
                // A completed update must not roll back after cleanup has begun.
            }
        }
        $this->snapshot = $this->preparedRoot = $this->version = null;
    }

    private function updateArguments(bool $dryRun = false): array
    {
        $arguments = [
            $this->advancePin ? 'require' : 'update', $this->package().':'.$this->version, '--with-dependencies',
            '--minimal-changes', '--prefer-dist', '--no-interaction', '--no-progress',
            '--no-scripts', '--no-plugins', '--optimize-autoloader',
        ];
        $dependencies = (bool) config('publisher-client.apply.composer_update_dependencies', true);
        if (! $dependencies) {
            $arguments = array_values(array_diff($arguments, ['--with-dependencies']));
        }
        if ((bool) config('publisher-client.apply.composer_lock_only', false)) {
            if ($dependencies) {
                throw $this->failure('Lock-only installation must preserve every unrelated dependency.');
            }
            $arguments[] = '--no-install';
        }
        if (($this->json($this->root().'/vendor/composer/installed.json')['dev'] ?? true) === false) {
            $arguments[] = $this->advancePin ? '--update-no-dev' : '--no-dev';
        }
        if ($dryRun) {
            $arguments[] = '--dry-run';
        }

        return $arguments;
    }

    private function assertHostUnchanged(): void
    {
        if (File::get($this->root().'/composer.json') !== $this->composerBefore
            || File::get($this->root().'/composer.lock') !== $this->lockBefore) {
            throw $this->failure('Composer configuration changed after preflight; check updates again.');
        }
    }

    private function assertDependencyScope(): void
    {
        $before = $this->packages(json_decode($this->lockBefore, true, flags: JSON_THROW_ON_ERROR));
        $after = $this->packages($this->json($this->root().'/composer.lock'));
        $allowed = [];
        $pending = [$this->package()];
        while (($name = array_pop($pending)) !== null) {
            if (isset($allowed[$name])) {
                continue;
            }
            $allowed[$name] = true;
            foreach ([$before[$name] ?? [], $after[$name] ?? []] as $metadata) {
                foreach (array_keys($metadata['require'] ?? []) as $dependency) {
                    if (str_contains($dependency, '/')) {
                        $pending[] = $dependency;
                    }
                }
            }
        }
        if (! (bool) config('publisher-client.apply.composer_update_dependencies', true)) {
            $allowed = [$this->package() => true];
        }
        $composer = json_decode($this->composerBefore, true, flags: JSON_THROW_ON_ERROR);
        foreach (array_keys(($composer['require'] ?? []) + ($composer['require-dev'] ?? [])) as $name) {
            if ($name !== $this->package()) {
                unset($allowed[$name]);
            }
        }
        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $name) {
            if (! isset($allowed[$name]) && ($before[$name] ?? null) != ($after[$name] ?? null)) {
                throw $this->failure('Composer changed a dependency outside the permitted product scope.');
            }
        }
    }

    /** Product bridges can bind recovery copies to their existing SQL run. */
    protected function recoveryDirectory(): string
    {
        return $this->root().'/.publisher-recovery-'.bin2hex(random_bytes(12));
    }

    private function assertUnrelatedInstalledPackagesMatchLock(): void
    {
        $lock = $this->json($this->root().'/composer.lock');
        $installedDocument = $this->json($this->root().'/vendor/composer/installed.json');
        $expected = $lock['packages'] ?? [];
        if (($installedDocument['dev'] ?? true) !== false) {
            $expected = [...$expected, ...($lock['packages-dev'] ?? [])];
        }
        $expected = array_column($expected, null, 'name');
        $installed = $this->packages($installedDocument);
        foreach (array_unique([...array_keys($expected), ...array_keys($installed)]) as $name) {
            if ($name === $this->package()) {
                continue;
            }
            $left = $expected[$name] ?? [];
            $right = $installed[$name] ?? [];
            if (! $left || ! $right
                || ltrim((string) $left['version'], 'v') !== ltrim((string) $right['version'], 'v')
                || ($left['source'] ?? null) != ($right['source'] ?? null)
                || ($left['dist'] ?? null) != ($right['dist'] ?? null)) {
                throw $this->failure('A lock-only bridge cannot reconcile unrelated installed packages.');
            }
        }
    }

    private function assertVerifiedFiles(string $stagedRoot): void
    {
        $roots = config('publisher-client.apply.package_validation.allowed_roots', []);
        if (! is_array($roots) || $roots === []) {
            throw $this->failure('Composer package verification requires an explicit product file boundary.');
        }
        foreach ($roots as $relative) {
            $expected = $this->fileHashes($stagedRoot.'/'.$relative);
            $actual = $this->fileHashes($this->targetRoot().'/'.$relative);
            if ($expected !== $actual) {
                throw $this->failure('The Composer package files do not match the signed Publisher artifact.');
            }
        }
    }

    private function fileHashes(string $path): array
    {
        if (is_link($path)) {
            throw $this->failure('Linked files cannot be compared with the signed package.');
        }
        if (File::isFile($path)) {
            return ['' => hash_file('sha256', $path)];
        }
        $hashes = [];
        if (File::isDirectory($path)) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::SELF_FIRST);
            foreach ($files as $file) {
                if ($file->isLink()) {
                    throw $this->failure('Linked files cannot be compared with the signed package.');
                }
                if ($file->isFile()) {
                    $hashes[substr($file->getPathname(), strlen($path) + 1)] = hash_file('sha256', $file->getPathname());
                }
            }
        }
        ksort($hashes);

        return $hashes;
    }

    private function assertInstalledMetadata(): void
    {
        $name = $this->package();
        $lock = $this->packages($this->json($this->root().'/composer.lock'))[$name] ?? [];
        $installed = $this->packages($this->json($this->root().'/vendor/composer/installed.json'))[$name] ?? [];
        if (ltrim((string) ($lock['version'] ?? ''), 'v') !== $this->version
            || ($lock['source'] ?? null) !== ($installed['source'] ?? null)
            || ($lock['dist'] ?? null) !== ($installed['dist'] ?? null)
            || ($lock['require'] ?? []) != ($this->json($this->targetRoot().'/composer.json')['require'] ?? [])) {
            throw $this->failure('Composer lock, installed package and verified release are inconsistent.');
        }
    }

    private function failure(string $detail, ?\Throwable $previous = null): UpdateException
    {
        return new UpdateException(
            __('publisher-client::updates.composer_package_failed'),
            $detail,
            previous: $previous,
        );
    }

    private function root(): string
    {
        return base_path();
    }

    private function package(): string
    {
        $name = (string) config('publisher-client.package.name', '');
        if (preg_match('/^[a-z0-9_.-]+\/[a-z0-9_.-]+$/D', $name) !== 1) {
            throw $this->failure('The Composer package identity is invalid.');
        }

        return $name;
    }

    private function json(string $path): array
    {
        try {
            $data = json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\Throwable $exception) {
            throw $this->failure('A required Composer document is missing or invalid.', previous: $exception);
        }
        if (! is_array($data)) {
            throw $this->failure('A required Composer document is invalid.');
        }

        return $data;
    }

    private function packages(array $document): array
    {
        $result = [];
        foreach ([...($document['packages'] ?? []), ...($document['packages-dev'] ?? [])] as $package) {
            $result[$package['name']] = $package;
        }

        return $result;
    }

    private function copyTree(string $source, string $target): void
    {
        File::ensureDirectoryExists($target, 0700);
        foreach (new \FilesystemIterator($source, \FilesystemIterator::SKIP_DOTS) as $file) {
            $destination = $target.'/'.$file->getFilename();
            if ($file->isLink()) {
                $ok = symlink(readlink($file->getPathname()), $destination);
            } elseif ($file->isDir()) {
                $this->copyTree($file->getPathname(), $destination);
                $ok = true;
            } else {
                $ok = copy($file->getPathname(), $destination);
                if ($ok) {
                    chmod($destination, $file->getPerms() & 07777);
                }
            }
            if (! $ok) {
                throw $this->failure('A complete dependency recovery copy could not be created.');
            }
        }
        chmod($target, fileperms($source) & 07777);
    }
}
