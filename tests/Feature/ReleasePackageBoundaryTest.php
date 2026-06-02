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
    $this->assertStringNotContainsString('/.local-container export-ignore', $attributes);
    $this->assertStringNotContainsString('/public/cms export-ignore', $attributes);
    $this->assertStringNotContainsString('/public/cms/** export-ignore', $attributes);
    $this->assertStringNotContainsString('/packages/webblocks-cms/public export-ignore', $attributes);
    $this->assertStringNotContainsString('/packages/webblocks-cms/public/** export-ignore', $attributes);
  }

  #[Test]
  public function github_release_workflows_are_intentionally_absent(): void
  {
    $this->assertDirectoryDoesNotExist(base_path('.github'));
    $this->assertSame([], glob(base_path('.github/workflows/*')) ?: []);
  }

  #[Test]
  public function native_release_prepare_builds_archives_from_package_root_with_worktree_attributes(): void
  {
    $script = (string) file_get_contents(base_path('scripts/release/prepare.sh'));

    $this->assertStringContainsString('git archive --format=tar --worktree-attributes HEAD "${PACKAGE_ROOT}"', $script);
    $this->assertStringContainsString('cd "${PACKAGE_DIR}"', $script);
    $this->assertStringContainsString('zip -qr "${ARCHIVE_PATH}" .', $script);
    $this->assertStringContainsString('"minimum_client_version" => getenv("WEBBLOCKS_UPDATE_MINIMUM_CLIENT_VERSION") ?: "1.32.18"', $script);
    $this->assertStringNotContainsString('gh release', $script);
    $this->assertStringNotContainsString('github.com', $script);
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
  public function release_artifact_includes_package_owned_admin_title_runtime_files(): void
  {
    $installedPackageRoot = $this->buildInstalledPackageSnapshot();

    $adminLayout = (string) file_get_contents($installedPackageRoot.'/resources/views/layouts/admin.blade.php');
    $systemSettings = (string) file_get_contents($installedPackageRoot.'/src/Support/System/SystemSettings.php');
    $dashboard = (string) file_get_contents($installedPackageRoot.'/resources/views/admin/dashboard.blade.php');

    $this->assertStringContainsString('app(SystemSettings::class)->adminBrowserTitle($adminBrowserTitle ?? $title ?? null)', $adminLayout);
    $this->assertStringContainsString("'title' => \$resolvedAdminBrowserTitle", $adminLayout);
    $this->assertStringContainsString('function adminBrowserTitle(?string $screenTitle = null): string', $systemSettings);
    $this->assertStringContainsString("if (\$screenTitle === 'Admin Dashboard')", $systemSettings);
    $this->assertStringContainsString("return 'Dashboard';", $systemSettings);
    $this->assertStringContainsString("return \$screenTitle.' - '.\$productName;", $systemSettings);
    $this->assertStringContainsString("['title' => 'Admin Dashboard', 'heading' => 'Dashboard']", $dashboard);
  }

  #[Test]
  public function release_artifact_includes_bulk_listing_admin_javascript_in_package_public_assets(): void
  {
    $installedPackageRoot = $this->buildInstalledPackageSnapshot();

    $this->assertFileExists($installedPackageRoot.'/public/cms/js/admin/listing-bulk-actions.js');
    $this->assertFileDoesNotExist($installedPackageRoot.'/public/cms/index.php');
  }

  #[Test]
  public function repository_and_release_artifacts_do_not_ship_vite_or_node_build_chain_files(): void
  {
    $repositorySourceRoot = $this->buildRepositorySourceSnapshot();
    $installedPackageRoot = $this->buildInstalledPackageSnapshot();
    $vendorPackageRoot = $this->buildNativeReleaseZipVendorPackageSnapshot();

    foreach ([
      'repository source checkout' => $repositorySourceRoot,
      'installed package artifact' => $installedPackageRoot,
      'native release ZIP package root' => $vendorPackageRoot,
    ] as $label => $root) {
      $this->assertBuildChainFilesAbsent($root, $label);
    }
  }

  #[Test]
  public function runtime_and_release_boundaries_do_not_reference_vite_or_node_build_steps(): void
  {
    $this->assertForbiddenBuildChainReferencesAbsent(base_path(), [
      'app',
      'bootstrap',
      'config',
      'database',
      'packages/webblocks-cms',
      'plugins',
      'public',
      'resources',
      'routes',
      'scripts',
      'composer.json',
      '.gitattributes',
      '.gitignore',
    ]);

    $this->assertForbiddenBuildChainReferencesAbsent($this->buildInstalledPackageSnapshot(), [
      'config',
      'database',
      'public',
      'resources',
      'routes',
      'scripts',
      'src',
      'composer.json',
    ]);
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
  public function native_release_zip_installs_bulk_listing_admin_javascript_at_composer_vendor_package_root(): void
  {
    $vendorPackageRoot = $this->buildNativeReleaseZipVendorPackageSnapshot();

    $this->assertFileExists($vendorPackageRoot.'/public/cms/js/admin/listing-bulk-actions.js');
    $this->assertFileExists($vendorPackageRoot.'/resources/views/layouts/admin.blade.php');
    $this->assertFileExists($vendorPackageRoot.'/src/Support/System/SystemSettings.php');
    $this->assertStringContainsString('adminBrowserTitle($adminBrowserTitle ?? $title ?? null)', (string) file_get_contents($vendorPackageRoot.'/resources/views/layouts/admin.blade.php'));
    $this->assertStringContainsString("'Admin Dashboard'", (string) file_get_contents($vendorPackageRoot.'/src/Support/System/SystemSettings.php'));
    $this->assertFileDoesNotExist($vendorPackageRoot.'/public/cms/index.php');
    $this->assertFileExists($vendorPackageRoot.'/public/cms/brand/logo-64.png');
    $this->assertFileExists($vendorPackageRoot.'/public/cms/brand/favicon-32x32.png');
    $this->assertFileDoesNotExist($vendorPackageRoot.'/packages/webblocks-cms/public/cms/js/admin/listing-bulk-actions.js');

    foreach ([
      '.github',
      '.git',
      '.DS_Store',
      '__MACOSX',
      'project',
      'storage',
      'vendor',
      'node_modules',
      'public/build',
    ] as $forbiddenPath) {
      $this->assertFileDoesNotExist($vendorPackageRoot.'/'.$forbiddenPath);
    }
  }

  #[Test]
  public function composer_source_dist_checkout_includes_bulk_listing_admin_javascript_in_root_and_package_paths(): void
  {
    $sourceCheckoutRoot = $this->buildRepositorySourceSnapshot();

    $this->assertFileExists($sourceCheckoutRoot.'/public/cms/js/admin/listing-bulk-actions.js');
    $this->assertFileExists($sourceCheckoutRoot.'/packages/webblocks-cms/public/cms/js/admin/listing-bulk-actions.js');
    $this->assertFileDoesNotExist($sourceCheckoutRoot.'/public/cms/index.php');
    $this->assertFileDoesNotExist($sourceCheckoutRoot.'/packages/webblocks-cms/public/cms/index.php');
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

  private function buildNativeReleaseZipVendorPackageSnapshot(): string
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

  private function assertBuildChainFilesAbsent(string $root, string $label): void
  {
    $forbiddenNames = [
      'package.json',
      'package-lock.json',
      'pnpm-lock.yaml',
      'yarn.lock',
      'bun.lock',
      'bun.lockb',
      'webpack.mix.js',
    ];

    foreach ($forbiddenNames as $forbiddenName) {
      $this->assertFileDoesNotExist($root.'/'.$forbiddenName, $label.' must not ship '.$forbiddenName);
    }

    $forbiddenPatterns = [
      'vite.config.*',
      'tailwind.config.*',
      'postcss.config.*',
    ];

    foreach ($forbiddenPatterns as $forbiddenPattern) {
      $matches = glob($root.'/'.$forbiddenPattern) ?: [];

      $this->assertSame([], $matches, $label.' must not ship '.$forbiddenPattern);
    }

    foreach ([
      'node_modules',
      'public/build',
      'public/hot',
    ] as $forbiddenPath) {
      $this->assertFileDoesNotExist($root.'/'.$forbiddenPath, $label.' must not ship '.$forbiddenPath);
    }
  }

  /**
   * @param  array<int, string>  $relativePaths
   */
  private function assertForbiddenBuildChainReferencesAbsent(string $root, array $relativePaths): void
  {
    $forbiddenPatterns = [
      '/@vite\b/',
      '/\bVite::/',
      '/laravel-vite-plugin/',
      '/\bvite\.config\./',
      '/\btailwind\.config\./',
      '/\bpostcss\.config\./',
      '/\bpackage-lock\.json\b/',
      '/\bnode_modules\b/',
      '/\bpublic\/build\b/',
      '/\bpublic\/hot\b/',
      '/\bnpm\s+(run|install|ci|build)\b/',
      '/"build"\s*:/',
      '/"dev"\s*:\s*"vite/',
    ];
    $violations = [];

    foreach ($relativePaths as $relativePath) {
      $path = $root.'/'.$relativePath;

      if (is_file($path)) {
        $this->collectForbiddenBuildChainReferences($path, $root, $forbiddenPatterns, $violations);

        continue;
      }

      if (! is_dir($path)) {
        continue;
      }

      foreach (File::allFiles($path) as $file) {
        $this->collectForbiddenBuildChainReferences($file->getPathname(), $root, $forbiddenPatterns, $violations);
      }
    }

    $this->assertSame([], $violations);
  }

  /**
   * @param  array<int, string>  $forbiddenPatterns
   * @param  array<int, string>  $violations
   */
  private function collectForbiddenBuildChainReferences(
    string $path,
    string $root,
    array $forbiddenPatterns,
    array &$violations
  ): void {
    if (! $this->isTextFile($path)) {
      return;
    }

    $contents = (string) file_get_contents($path);
    $relativePath = ltrim(str_replace($root, '', $path), DIRECTORY_SEPARATOR);

    foreach ($forbiddenPatterns as $pattern) {
      if (preg_match($pattern, $contents) === 1) {
        $violations[] = $relativePath.' matches '.$pattern;
      }
    }
  }

  private function isTextFile(string $path): bool
  {
    $extension = pathinfo($path, PATHINFO_EXTENSION);

    return in_array($extension, [
      '',
      'blade.php',
      'css',
      'gitignore',
      'gitattributes',
      'json',
      'md',
      'php',
      'sh',
      'stub',
      'txt',
      'yml',
      'yaml',
    ], true);
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
