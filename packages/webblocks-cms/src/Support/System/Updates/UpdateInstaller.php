<?php

namespace WebBlocks\Cms\Support\System\Updates;

use Illuminate\Support\Facades\File;
use WebBlocks\Cms\Support\Install\InstallationGitRemoteGuard;

class UpdateInstaller
{
  private const PACKAGE_RUNTIME_PATH = 'packages/webblocks-cms';

  private const COMPOSER_VENDOR_PACKAGE_PATH = 'vendor/fklavyenet/webblocks-cms';

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
    $packageRuntimePaths = $this->packageRuntimePaths($targetPath);

    if (! File::isDirectory($targetPath)) {
      throw new UpdateException('The application root configured for updates does not exist.', 'Missing update target path: '.$targetPath);
    }

    if (! File::isDirectory($packageRoot)) {
      throw new UpdateException('The downloaded update package could not be applied.', 'Validated package root is missing: '.$packageRoot);
    }

    $this->assertSafePackageContents($packageRoot);

    foreach ($packageRuntimePaths as $packageRuntimePath) {
      $this->replacePackageRuntime($targetPath, $packageRuntimePath, $packageRoot, $output);
    }

    $this->syncRootRuntimeAssets($targetPath, $packageRoot, $output);
  }

  private function replacePackageRuntime(string $targetPath, string $packageRuntimePath, string $packageRoot, array &$output): void
  {
    $stagingPath = $packageRuntimePath.'.wb-update-new';
    $backupPath = $packageRuntimePath.'.wb-update-old';

    $this->assertSafePackageRuntimePath($targetPath, $packageRuntimePath);

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

    $output[] = 'Replaced '.$this->relativePath($targetPath, $packageRuntimePath).' with package artifact contents.';
  }

  private function syncRootRuntimeAssets(string $targetPath, string $packageRoot, array &$output): void
  {
    $source = $packageRoot.DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'cms';

    if (! File::isDirectory($source)) {
      return;
    }

    $target = rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.'public'.DIRECTORY_SEPARATOR.'cms';

    File::ensureDirectoryExists($target);

    foreach (File::allFiles($source) as $file) {
      $relativePath = ltrim(str_replace($source, '', $file->getPathname()), DIRECTORY_SEPARATOR);
      $destination = $target.DIRECTORY_SEPARATOR.$relativePath;

      File::ensureDirectoryExists(dirname($destination));
      File::copy($file->getPathname(), $destination);
    }

    $output[] = 'Synced package public/cms assets into public/cms runtime compatibility path.';

    $retiredCmsIndex = $target.DIRECTORY_SEPARATOR.'index.php';

    if (File::exists($retiredCmsIndex) && ! File::exists($source.DIRECTORY_SEPARATOR.'index.php')) {
      File::delete($retiredCmsIndex);

      $output[] = 'Removed retired public/cms/index.php front-controller handoff.';
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
    $this->migrationRunner->run($this->targetPath(), $output);

    foreach ([
      ['config:clear'],
      ['view:clear'],
      ['cache:clear'],
      ['route:clear'],
      ['optimize:clear'],
    ] as $artisanCommand) {
      $this->commandRunner->run(
        $this->commandRunner->artisanCommand($artisanCommand),
        $this->targetPath(),
        $output,
      );
    }

    $this->installationGitRemoteGuard->protectCurrentInstall($this->targetPath(), $output);
  }

  public function verifyAppliedVersion(string $expectedVersion, array &$output): void
  {
    $targetPath = $this->targetPath();
    $versions = [];

    foreach ($this->packageRuntimePaths($targetPath) as $packageRuntimePath) {
      $versionPath = $packageRuntimePath.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'Support'.DIRECTORY_SEPARATOR.'WebBlocks.php';
      $relativePath = $this->relativePath($targetPath, $versionPath);
      $version = $this->versionFromWebBlocksFile($versionPath);

      if ($version === null) {
        throw new UpdateException(
          'The update package was applied, but the installed CMS version could not be verified. The run was not marked successful.',
          'Could not read WebBlocks CMS version from '.$relativePath.'.',
        );
      }

      $versions[$relativePath] = $version;
    }

    if ($versions === []) {
      throw new UpdateException(
        'The update package was applied, but the installed CMS version could not be verified. The run was not marked successful.',
        'No active WebBlocks CMS runtime path was found under the configured update target.',
      );
    }

    foreach ($versions as $relativePath => $version) {
      if ($version !== $expectedVersion) {
        throw new UpdateException(
          'The update package was applied, but the installed CMS version still does not match the target release. The run was not marked successful; restore the pre-update backup or review filesystem and cache state before retrying.',
          'Expected WebBlocks CMS '.$expectedVersion.' after update, but '.$relativePath.' reports '.$version.'.',
        );
      }
    }

    $output[] = 'Post-update version verified as '.$expectedVersion.' from canonical WebBlocks version source.';
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

  private function packageRuntimePaths(string $targetPath): array
  {
    if ($this->isSourceMaintainedTarget($targetPath)) {
      return [$this->rootPackageRuntimePath($targetPath)];
    }

    return [$this->canonicalComposerPackageRuntimePath($targetPath)];
  }

  private function rootPackageRuntimePath(string $targetPath): string
  {
    return rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::PACKAGE_RUNTIME_PATH);
  }

  private function canonicalComposerPackageRuntimePath(string $targetPath): string
  {
    return rtrim($targetPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, self::COMPOSER_VENDOR_PACKAGE_PATH);
  }

  private function isSourceMaintainedTarget(string $targetPath): bool
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

    $autoload = $composer['autoload']['psr-4'] ?? [];

    if (($composer['name'] ?? null) !== 'fklavyenet/webblocks-cms') {
      return false;
    }

    if (($autoload['WebBlocks\\Cms\\'] ?? null) !== 'packages/webblocks-cms/src/') {
      return false;
    }

    if (($autoload['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null) !== 'packages/webblocks-cms/database/seeders/') {
      return false;
    }

    return File::isDirectory($this->rootPackageRuntimePath($targetPath));
  }

  private function assertSafePackageRuntimePath(string $targetPath, string $packageRuntimePath): void
  {
    $normalizedTargetPath = rtrim(str_replace('\\', '/', $targetPath), '/');
    $normalizedPackageRuntimePath = rtrim(str_replace('\\', '/', $packageRuntimePath), '/');
    $rootRuntimePath = $normalizedTargetPath.'/'.self::PACKAGE_RUNTIME_PATH;
    $vendorRuntimePath = $normalizedTargetPath.'/'.self::COMPOSER_VENDOR_PACKAGE_PATH;

    if ($normalizedPackageRuntimePath === $rootRuntimePath) {
      return;
    }

    if ($normalizedPackageRuntimePath === $vendorRuntimePath) {
      return;
    }

    if ($this->isSafeComposerRuntimePath($targetPath, $packageRuntimePath)) {
      return;
    }

    throw new UpdateException('The update could not apply the downloaded package.', 'Refusing to apply package outside the WebBlocks CMS runtime boundaries.');
  }

  private function isSafeComposerRuntimePath(string $targetPath, string $packageRuntimePath): bool
  {
    $normalizedTargetPath = rtrim(str_replace('\\', '/', realpath($targetPath) ?: $targetPath), '/');
    $normalizedPackageRuntimePath = rtrim(str_replace('\\', '/', realpath($packageRuntimePath) ?: $packageRuntimePath), '/');
    $normalizedVendorPackagePath = $normalizedTargetPath.'/'.self::COMPOSER_VENDOR_PACKAGE_PATH;

    if ($normalizedPackageRuntimePath !== $normalizedVendorPackagePath
      && ! str_starts_with($normalizedPackageRuntimePath, $normalizedVendorPackagePath.'/')) {
      return false;
    }

    $composerPath = $packageRuntimePath.DIRECTORY_SEPARATOR.'composer.json';

    if (! File::isFile($composerPath)) {
      return false;
    }

    $composer = json_decode((string) File::get($composerPath), true);

    return is_array($composer)
      && ($composer['name'] ?? null) === 'fklavyenet/webblocks-cms'
      && File::isDirectory($packageRuntimePath.DIRECTORY_SEPARATOR.'src');
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

  private function versionFromWebBlocksFile(string $path): ?string
  {
    if (! File::isFile($path)) {
      return null;
    }

    $tokens = token_get_all((string) File::get($path));
    $tokenCount = count($tokens);

    for ($index = 0; $index < $tokenCount; $index++) {
      $token = $tokens[$index];

      if (! is_array($token) || $token[0] !== T_CONST) {
        continue;
      }

      for ($nameIndex = $index + 1; $nameIndex < $tokenCount; $nameIndex++) {
        $nameToken = $tokens[$nameIndex];

        if (is_array($nameToken) && $nameToken[0] === T_WHITESPACE) {
          continue;
        }

        if (! is_array($nameToken) || $nameToken[0] !== T_STRING || $nameToken[1] !== 'VERSION') {
          break;
        }

        for ($valueIndex = $nameIndex + 1; $valueIndex < $tokenCount; $valueIndex++) {
          $valueToken = $tokens[$valueIndex];

          if (is_array($valueToken) && $valueToken[0] === T_WHITESPACE) {
            continue;
          }

          if ($valueToken === '=') {
            continue;
          }

          if (is_array($valueToken) && $valueToken[0] === T_CONSTANT_ENCAPSED_STRING) {
            return trim($valueToken[1], '\'"');
          }

          break;
        }
      }
    }

    return null;
  }

  private function relativePath(string $targetPath, string $path): string
  {
    $normalizedTargetPath = rtrim(str_replace('\\', '/', $targetPath), '/').'/';
    $normalizedPath = str_replace('\\', '/', $path);

    if (str_starts_with($normalizedPath, $normalizedTargetPath)) {
      return substr($normalizedPath, strlen($normalizedTargetPath));
    }

    return $path;
  }
}
