<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ReleasePackageBoundaryTest extends TestCase
{
    private array $temporaryDirectories = [];

    #[Test]
    public function git_attributes_excludes_project_layer_from_release_exports(): void
    {
        $attributes = (string) file_get_contents(base_path('.gitattributes'));

        $this->assertStringContainsString('/project export-ignore', $attributes);
        $this->assertStringContainsString('/.ddev export-ignore', $attributes);
    }

    #[Test]
    public function publish_release_workflow_builds_archives_from_git_archive_with_worktree_attributes(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/publish-release.yml'));

        $this->assertStringContainsString('git archive --format=tar --worktree-attributes HEAD "$package_root" | tar -xf - -C "$staging_dir"', $workflow);
        $this->assertStringContainsString('cd "$package_dir"', $workflow);
        $this->assertStringContainsString('zip -qr "$GITHUB_WORKSPACE/$archive_path" .', $workflow);
        $this->assertStringNotContainsString('git ls-files --cached --others --exclude-standard', $workflow);
        $this->assertStringNotContainsString('git archive --format=zip --worktree-attributes --output "$archive_path" HEAD', $workflow);
    }

    #[Test]
    public function release_artifact_uses_the_package_composer_manifest_at_the_installed_package_root(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();
        $composer = json_decode((string) file_get_contents($installedPackageRoot.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('fklavyenet/webblocks-cms', $composer['name'] ?? null);
        $this->assertSame('src/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\'] ?? null);
        $this->assertSame('database/seeders/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null);
        $this->assertNotSame('packages/webblocks-cms/src/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\'] ?? null);
    }

    #[Test]
    public function release_artifact_places_updater_support_files_at_installed_package_root_src_paths(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();

        $this->assertFileDoesNotExist($installedPackageRoot.'/artisan');
        $this->assertFileExists($installedPackageRoot.'/src/Support/System/Updates/UpdateException.php');
        $this->assertFileExists($installedPackageRoot.'/src/Support/System/Updates/SystemUpdater.php');
        $this->assertFileExists($installedPackageRoot.'/src/Support/System/Updates/UpdateInstaller.php');
        $this->assertFileExists($installedPackageRoot.'/src/Support/System/Updates/UpdatePackageExtractor.php');
        $this->assertFileDoesNotExist($installedPackageRoot.'/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php');
    }

    #[Test]
    public function release_artifact_composer_psr4_mapping_resolves_update_exception_to_src_path(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();
        $composer = json_decode((string) file_get_contents($installedPackageRoot.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame(
            'src/Support/System/Updates/UpdateException.php',
            $this->resolvePsr4ClassPath($composer['autoload']['psr-4'] ?? [], 'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException')
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryDirectories as $directory) {
            File::deleteDirectory($directory);
        }

        parent::tearDown();
    }

    private function buildInstalledPackageSnapshot(): string
    {
        $stagingDirectory = $this->makeTemporaryDirectory('release-package-artifact');

        $archive = new Process([
            'git',
            'archive',
            '--format=tar',
            '--worktree-attributes',
            'HEAD',
            'packages/webblocks-cms',
        ], base_path());
        $archive->mustRun();

        $extract = new Process(['tar', '-xf', '-', '-C', $stagingDirectory], base_path());
        $extract->setInput($archive->getOutput());
        $extract->mustRun();

        $packageRoot = $stagingDirectory.'/packages/webblocks-cms';
        $installedPackageRoot = $stagingDirectory.'/installed-package-root';

        File::ensureDirectoryExists($installedPackageRoot);

        foreach (File::allFiles($packageRoot) as $file) {
            $relativePath = ltrim(str_replace($packageRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
            $targetPath = $installedPackageRoot.DIRECTORY_SEPARATOR.$relativePath;

            File::ensureDirectoryExists(dirname($targetPath));
            File::copy($file->getPathname(), $targetPath);
        }

        return $installedPackageRoot;
    }

    private function makeTemporaryDirectory(string $prefix): string
    {
        $path = storage_path('app/testing-release-package-boundary/'.$prefix.'-'.uniqid('', true));
        File::ensureDirectoryExists($path);
        $this->temporaryDirectories[] = $path;

        return $path;
    }

    /**
     * @param  array<string, string>  $autoload
     */
    private function resolvePsr4ClassPath(array $autoload, string $className): ?string
    {
        foreach ($autoload as $prefix => $path) {
            if (! str_starts_with($className, $prefix)) {
                continue;
            }

            $suffix = str_replace('\\', '/', substr($className, strlen($prefix)));

            return rtrim($path, '/').'/'.$suffix.'.php';
        }

        return null;
    }
}
