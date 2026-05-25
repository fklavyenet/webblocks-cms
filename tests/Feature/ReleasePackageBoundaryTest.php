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
        $this->assertStringNotContainsString('/public/cms export-ignore', $attributes);
        $this->assertStringNotContainsString('/public/cms/** export-ignore', $attributes);
        $this->assertStringNotContainsString('/packages/webblocks-cms/public export-ignore', $attributes);
        $this->assertStringNotContainsString('/packages/webblocks-cms/public/** export-ignore', $attributes);
    }

    #[Test]
    public function publish_release_workflow_builds_archives_from_git_archive_with_worktree_attributes(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/publish-release.yml'));

        $this->assertStringContainsString('git archive --format=tar --worktree-attributes HEAD "$package_root" | tar -xf - -C "$staging_dir"', $workflow);
        $this->assertStringContainsString('cd "$package_dir"', $workflow);
        $this->assertStringContainsString('zip -qr "$GITHUB_WORKSPACE/$archive_path" .', $workflow);
        $this->assertStringContainsString('MINIMUM_CLIENT_VERSION: 1.32.18', $workflow);
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
    public function release_artifact_includes_bulk_listing_admin_javascript_in_package_public_assets(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();

        $this->assertFileExists($installedPackageRoot.'/public/cms/js/admin/listing-bulk-actions.js');
    }

    #[Test]
    public function release_artifact_includes_cms_brand_assets_in_package_public_assets(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();

        $this->assertFileExists($installedPackageRoot.'/public/cms/brand/logo-64.png');
        $this->assertFileExists($installedPackageRoot.'/public/cms/brand/favicon-32x32.png');
        $this->assertFileExists($installedPackageRoot.'/public/cms/brand/apple-touch-icon.png');
        $this->assertFileDoesNotExist($installedPackageRoot.'/public/site');
    }

    #[Test]
    public function workflow_release_zip_installs_bulk_listing_admin_javascript_at_composer_vendor_package_root(): void
    {
        $vendorPackageRoot = $this->buildWorkflowReleaseZipVendorPackageSnapshot();

        $this->assertFileExists($vendorPackageRoot.'/public/cms/js/admin/listing-bulk-actions.js');
        $this->assertFileExists($vendorPackageRoot.'/public/cms/brand/logo-64.png');
        $this->assertFileExists($vendorPackageRoot.'/public/cms/brand/favicon-32x32.png');
        $this->assertFileDoesNotExist($vendorPackageRoot.'/packages/webblocks-cms/public/cms/js/admin/listing-bulk-actions.js');
    }

    #[Test]
    public function composer_source_dist_checkout_includes_bulk_listing_admin_javascript_in_root_and_package_paths(): void
    {
        $sourceCheckoutRoot = $this->buildRepositorySourceSnapshot();

        $this->assertFileExists($sourceCheckoutRoot.'/public/cms/js/admin/listing-bulk-actions.js');
        $this->assertFileExists($sourceCheckoutRoot.'/packages/webblocks-cms/public/cms/js/admin/listing-bulk-actions.js');
        $this->assertFileExists($sourceCheckoutRoot.'/public/cms/brand/logo-64.png');
        $this->assertFileExists($sourceCheckoutRoot.'/packages/webblocks-cms/public/cms/brand/logo-64.png');
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

    #[Test]
    public function release_artifact_and_source_checkout_package_controllers_use_namespaced_site_transfer_views(): void
    {
        $installedPackageRoot = $this->buildInstalledPackageSnapshot();
        $sourceCheckoutRoot = $this->buildRepositorySourceSnapshot();

        foreach ([
            $installedPackageRoot.'/src/Http/Controllers/Admin/SiteExportController.php',
            $installedPackageRoot.'/src/Http/Controllers/Admin/SiteImportController.php',
            $sourceCheckoutRoot.'/packages/webblocks-cms/src/Http/Controllers/Admin/SiteExportController.php',
            $sourceCheckoutRoot.'/packages/webblocks-cms/src/Http/Controllers/Admin/SiteImportController.php',
        ] as $controllerPath) {
            $contents = (string) file_get_contents($controllerPath);

            $this->assertStringContainsString('webblocks-cms::admin.site-transfers', $contents, $controllerPath);

            foreach ([
                "view('admin/site-transfers",
                'view("admin/site-transfers',
                "view('admin.site-transfers",
                'view("admin.site-transfers',
                "response()->view('admin/site-transfers",
                'response()->view("admin/site-transfers',
                "response()->view('admin.site-transfers",
                'response()->view("admin.site-transfers',
            ] as $forbiddenViewReference) {
                $this->assertStringNotContainsString($forbiddenViewReference, $contents, $controllerPath);
            }
        }
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

    private function buildRepositorySourceSnapshot(): string
    {
        $stagingDirectory = $this->makeTemporaryDirectory('release-source-checkout');

        $archive = new Process([
            'git',
            'archive',
            '--format=tar',
            '--worktree-attributes',
            'HEAD',
        ], base_path());
        $archive->mustRun();

        $extract = new Process(['tar', '-xf', '-', '-C', $stagingDirectory], base_path());
        $extract->setInput($archive->getOutput());
        $extract->mustRun();

        return $stagingDirectory;
    }

    private function buildWorkflowReleaseZipVendorPackageSnapshot(): string
    {
        $stagingDirectory = $this->makeTemporaryDirectory('release-workflow-zip');
        $archivePath = $stagingDirectory.'/webblocks-cms-test.zip';
        $packageRoot = 'packages/webblocks-cms';

        $archive = new Process([
            'git',
            'archive',
            '--format=tar',
            '--worktree-attributes',
            'HEAD',
            $packageRoot,
        ], base_path());
        $archive->mustRun();

        $extract = new Process(['tar', '-xf', '-', '-C', $stagingDirectory], base_path());
        $extract->setInput($archive->getOutput());
        $extract->mustRun();

        $packageDirectory = $stagingDirectory.'/'.$packageRoot;
        $zip = new Process(['zip', '-qr', $archivePath, '.'], $packageDirectory);
        $zip->mustRun();

        $vendorPackageRoot = $stagingDirectory.'/vendor/fklavyenet/webblocks-cms';
        File::ensureDirectoryExists($vendorPackageRoot);

        $unzip = new Process(['unzip', '-q', $archivePath, '-d', $vendorPackageRoot], base_path());
        $unzip->mustRun();

        return $vendorPackageRoot;
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
