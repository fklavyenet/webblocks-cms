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
