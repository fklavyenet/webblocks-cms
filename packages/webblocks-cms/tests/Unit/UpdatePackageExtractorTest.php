<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageExtractor;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class UpdatePackageExtractorTest extends TestCase
{
  private array $temporaryDirectories = [];

  protected function tearDown(): void
  {
    foreach (array_reverse($this->temporaryDirectories) as $directory) {
      File::deleteDirectory($directory);
    }

    parent::tearDown();
  }

  #[Test]
  public function flat_package_root_is_accepted(): void
  {
    $destination = $this->temporaryDirectory('extract');
    $root = app(UpdatePackageExtractor::class)->extract($this->packageArchive(null), $destination);

    $this->assertSame($destination, $root);
  }

  #[Test]
  public function one_wrapper_directory_is_accepted(): void
  {
    $destination = $this->temporaryDirectory('extract');
    $root = app(UpdatePackageExtractor::class)->extract($this->packageArchive('webblocks-cms-1.36.0'), $destination);

    $this->assertSame($destination.'/webblocks-cms-1.36.0', $root);
  }

  #[Test]
  public function maintenance_application_root_is_rejected(): void
  {
    $this->expectPackageRejection($this->packageArchive(null, artisan: true));
  }

  #[Test]
  public function nested_package_repository_shape_is_rejected(): void
  {
    $this->expectPackageRejection($this->packageArchive(null, nestedPackage: true));
  }

  #[Test]
  public function wrong_composer_package_identity_is_rejected(): void
  {
    $this->expectPackageRejection($this->packageArchive(null, name: 'wrong/package'));
  }

  #[Test]
  public function missing_src_directory_is_rejected(): void
  {
    $this->expectPackageRejection($this->packageArchive(null, includeSrc: false));
  }

  #[Test]
  public function multiple_wrapper_directories_are_rejected(): void
  {
    $this->expectPackageRejection($this->packageArchive('outer/inner'));
  }

  #[Test]
  public function path_traversal_entries_are_rejected(): void
  {
    $archive = $this->packageArchive(null);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive) === true);
    $zip->addFromString('../outside.php', 'unsafe');
    $zip->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Path traversal detected');

    app(UpdatePackageExtractor::class)->extract($archive, $this->temporaryDirectory('extract'));
  }

  #[Test]
  public function duplicate_normalized_paths_are_rejected(): void
  {
    $archive = $this->packageArchive(null);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive) === true);
    $zip->addFromString('./composer.json', '{}');
    $zip->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Duplicate archive path');

    app(UpdatePackageExtractor::class)->extract($archive, $this->temporaryDirectory('extract'));
  }

  #[Test]
  public function symbolic_link_entries_are_rejected(): void
  {
    $archive = $this->packageArchive(null);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive) === true);
    $zip->addFromString('src/unsafe-link', 'target.php');
    $this->assertTrue($zip->setExternalAttributesName('src/unsafe-link', ZipArchive::OPSYS_UNIX, 0120777 << 16));
    $zip->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Symlink archive entry');

    app(UpdatePackageExtractor::class)->extract($archive, $this->temporaryDirectory('extract'));
  }

  #[Test]
  public function unexpectedly_large_entries_are_rejected(): void
  {
    $archive = $this->packageArchive(null);
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archive) === true);
    $zip->addFromString('src/oversized.bin', str_repeat('x', (10 * 1024 * 1024) + 1));
    $zip->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Oversized archive entry');

    app(UpdatePackageExtractor::class)->extract($archive, $this->temporaryDirectory('extract'));
  }

  #[Test]
  public function archive_containing_only_development_files_is_rejected(): void
  {
    $archivePath = $this->temporaryDirectory('archive').'/development-only.zip';
    $zip = new ZipArchive;
    $this->assertTrue($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $zip->addFromString('phpunit.xml.dist', '<phpunit/>');
    $zip->addFromString('tests/ExampleTest.php', "<?php\n");
    $zip->close();

    $this->expectPackageRejection($archivePath);
  }

  private function expectPackageRejection(string $archive): void
  {
    try {
      app(UpdatePackageExtractor::class)->extract($archive, $this->temporaryDirectory('extract'));
      $this->fail('Expected the archive to be rejected.');
    } catch (UpdateException $exception) {
      $this->assertStringContainsString('Package validation failed', $exception->getMessage());
    }
  }

  private function packageArchive(
    ?string $wrapper,
    bool $artisan = false,
    bool $nestedPackage = false,
    string $name = 'fklavyenet/webblocks-cms',
    bool $includeSrc = true,
  ): string {
    $archivePath = $this->temporaryDirectory('archive').'/package.zip';
    $prefix = $wrapper === null ? '' : trim($wrapper, '/').'/';
    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString($prefix.'composer.json', json_encode([
      'name' => $name,
      'autoload' => ['psr-4' => ['WebBlocks\\Cms\\' => 'src/']],
    ], JSON_THROW_ON_ERROR));

    if ($includeSrc) {
      $archive->addFromString($prefix.'src/Support/Placeholder.php', "<?php\n");
    }

    if ($artisan) {
      $archive->addFromString($prefix.'artisan', "<?php\n");
    }

    if ($nestedPackage) {
      $archive->addFromString($prefix.'packages/webblocks-cms/src/Placeholder.php', "<?php\n");
    }

    $archive->close();

    return $archivePath;
  }

  private function temporaryDirectory(string $purpose): string
  {
    $path = sys_get_temp_dir().'/webblocks-cms-package-tests/'.$purpose.'-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($path);
    $this->temporaryDirectories[] = $path;

    return $path;
  }
}
