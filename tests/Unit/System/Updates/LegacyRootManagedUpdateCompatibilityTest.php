<?php

namespace Tests\Unit\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use ZipArchive;

class LegacyRootManagedUpdateCompatibilityTest extends TestCase
{
  private array $temporaryDirectories = [];

  #[Test]
  public function legacy_1_31_53_validator_rejects_package_rooted_archives_with_the_live_failure_condition(): void
  {
    $archivePath = $this->makeArchive('package-rooted.zip', [
      'composer.json' => json_encode([
        'name' => 'fklavyenet/webblocks-cms',
        'autoload' => ['psr-4' => ['WebBlocks\\Cms\\' => 'src/']],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'src/Support/System/Updates/UpdatePackageExtractor.php' => "<?php\n",
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Package validation failed because composer.json and artisan were not found at the archive root.');

    $this->legacyExtractAndValidate($archivePath, $destinationPath);
  }

  #[Test]
  public function legacy_1_31_53_validator_accepts_an_explicit_root_managed_bridge_archive(): void
  {
    $archivePath = $this->makeArchive('root-managed-bridge.zip', [
      'artisan' => "<?php\n",
      'composer.json' => json_encode([
        'name' => 'fklavyenet/webblocks-cms',
        'autoload' => [
          'psr-4' => [
            'App\\' => 'app/',
            'WebBlocks\\Cms\\' => 'packages/webblocks-cms/src/',
          ],
        ],
      ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
      'app/Support/System/Updates/UpdatePackageExtractor.php' => "<?php\n",
      'packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php' => "<?php\n",
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $packageRoot = $this->legacyExtractAndValidate($archivePath, $destinationPath);

    $this->assertSame($destinationPath, $packageRoot);
    $this->assertFileExists($packageRoot.'/artisan');
    $this->assertFileExists($packageRoot.'/composer.json');
    $this->assertFileExists($packageRoot.'/app/Support/System/Updates/UpdatePackageExtractor.php');
    $this->assertFileExists($packageRoot.'/packages/webblocks-cms/src/Support/System/Updates/UpdatePackageExtractor.php');
  }

  #[Test]
  public function generated_bridge_archive_root_wrappers_load_package_update_exception_with_stale_app_only_autoloading(): void
  {
    $outputDirectory = $this->makeTemporaryDirectory('generated-bridge');
    $process = new Process([
      'bash',
      base_path('scripts/build-root-managed-bridge-archive.sh'),
      '9.9.9',
      $outputDirectory,
      'v1.32.30',
    ], base_path());
    $process->mustRun();

    $archivePath = $outputDirectory.'/webblocks-cms-9.9.9-root-managed-bridge.zip';
    $destinationPath = $this->makeTemporaryDirectory('extract');
    $packageRoot = $this->legacyExtractAndValidate($archivePath, $destinationPath);

    $this->assertFileExists($packageRoot.'/app/Support/System/Updates/UpdateException.php');
    $this->assertFileExists($packageRoot.'/packages/webblocks-cms/src/Support/System/Updates/UpdateException.php');
    $this->assertFileExists($packageRoot.'/app/Support/System/Updates/PackageUpdaterBridgeBootstrap.php');

    $probePath = $this->makeTemporaryDirectory('probe').'/probe.php';
    File::put($probePath, <<<'PHP'
<?php

$root = $argv[1];

spl_autoload_register(static function (string $className) use ($root): void {
  $prefix = 'App\\';

  if (! str_starts_with($className, $prefix)) {
    return;
  }

  $relativeClass = substr($className, strlen($prefix));
  $path = $root.'/app/'.str_replace('\\', '/', $relativeClass).'.php';

  if (is_file($path)) {
    require_once $path;
  }
});

$rootWrapperResolved = class_exists('App\\Support\\System\\Updates\\UpdateException');
$packageClassResolved = class_exists('WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException');

echo json_encode([
  'root_wrapper_resolved' => $rootWrapperResolved,
  'package_class_resolved' => $packageClassResolved,
  'is_subclass' => is_subclass_of(
    'App\\Support\\System\\Updates\\UpdateException',
    'WebBlocks\\Cms\\Support\\System\\Updates\\UpdateException'
  ),
], JSON_THROW_ON_ERROR);
PHP);

    $probe = new Process([PHP_BINARY, $probePath, $packageRoot], base_path());
    $probe->mustRun();

    $result = json_decode($probe->getOutput(), true, 512, JSON_THROW_ON_ERROR);

    $this->assertSame([
      'root_wrapper_resolved' => true,
      'package_class_resolved' => true,
      'is_subclass' => true,
    ], $result);
  }

  #[Test]
  public function legacy_bridge_contract_rejects_arbitrary_zip_layouts(): void
  {
    $archivePath = $this->makeArchive('invalid.zip', [
      'README.md' => "not a release\n",
      'src/Support/System/Updates/UpdatePackageExtractor.php' => "<?php\n",
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Package validation failed because composer.json and artisan were not found at the archive root.');

    $this->legacyExtractAndValidate($archivePath, $destinationPath);
  }

  private function legacyExtractAndValidate(string $archivePath, string $destinationDirectory): string
  {
    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath) === true);

    File::ensureDirectoryExists($destinationDirectory);

    for ($index = 0; $index < $archive->numFiles; $index++) {
      $entryName = (string) $archive->getNameIndex($index);
      $normalizedPath = ltrim(str_replace('\\', '/', $entryName), '/');

      if ($normalizedPath === '' || str_ends_with($normalizedPath, '/')) {
        continue;
      }

      if (preg_match('/(^|\/)\.\.(\/|$)/', $normalizedPath) === 1) {
        throw new \RuntimeException('Path traversal detected in archive entry '.$normalizedPath.'.');
      }

      $targetPath = $destinationDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $normalizedPath);

      File::ensureDirectoryExists(dirname($targetPath));
      File::put($targetPath, (string) $archive->getFromIndex($index));
    }

    $archive->close();

    if (File::isFile($destinationDirectory.'/artisan') && File::isFile($destinationDirectory.'/composer.json')) {
      return $destinationDirectory;
    }

    $directories = array_values(array_filter(File::directories($destinationDirectory), function (string $directory): bool {
      return basename($directory) !== '__MACOSX';
    }));

    if (count($directories) === 1 && File::isFile($directories[0].'/artisan') && File::isFile($directories[0].'/composer.json')) {
      return $directories[0];
    }

    throw new \RuntimeException('Package validation failed because composer.json and artisan were not found at the archive root.');
  }

  private function makeArchive(string $archiveName, array $files): string
  {
    $archivePath = $this->makeTemporaryDirectory('archive').'/'.$archiveName;
    $archive = new ZipArchive;

    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);

    foreach ($files as $path => $contents) {
      $archive->addFromString($path, $contents);
    }

    $archive->close();

    return $archivePath;
  }

  private function makeTemporaryDirectory(string $prefix): string
  {
    $path = storage_path('app/testing-legacy-update-compatibility/'.$prefix.'-'.Str::uuid());
    File::ensureDirectoryExists($path);
    $this->temporaryDirectories[] = $path;

    return $path;
  }

  protected function tearDown(): void
  {
    foreach ($this->temporaryDirectories as $directory) {
      File::deleteDirectory($directory);
    }

    parent::tearDown();
  }
}
