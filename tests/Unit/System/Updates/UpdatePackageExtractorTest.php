<?php

namespace Tests\Unit\System\Updates;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Support\System\Updates\UpdateException;
use WebBlocks\Cms\Support\System\Updates\UpdatePackageExtractor;
use ZipArchive;

class UpdatePackageExtractorTest extends TestCase
{
  private array $temporaryDirectories = [];

  #[Test]
  public function extractor_rejects_archive_entries_with_path_traversal(): void
  {
    $archivePath = $this->makeTemporaryDirectory('archive').'/malicious.zip';
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString('../evil.php', 'malicious');
    $archive->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Path traversal detected');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  #[Test]
  public function extractor_accepts_package_artifact_at_archive_root_without_artisan(): void
  {
    $archivePath = $this->makePackageArchive('package-root.zip', null, [
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
          'WebBlocks\\Cms\\Database\\Seeders\\' => 'database/seeders/',
        ],
      ],
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $packageRoot = app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);

    $this->assertSame($destinationPath, $packageRoot);
    $this->assertFileExists($packageRoot.'/composer.json');
    $this->assertFileExists($packageRoot.'/src/Support/System/Updates/UpdateException.php');
    $this->assertFileDoesNotExist($packageRoot.'/artisan');
  }

  #[Test]
  public function extractor_accepts_package_artifact_inside_one_wrapper_directory(): void
  {
    $archivePath = $this->makePackageArchive('package-wrapper.zip', 'webblocks-cms-1.32.19', [
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
          'WebBlocks\\Cms\\Database\\Seeders\\' => 'database/seeders/',
        ],
      ],
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $packageRoot = app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);

    $this->assertSame($destinationPath.'/webblocks-cms-1.32.19', $packageRoot);
    $this->assertFileExists($packageRoot.'/composer.json');
    $this->assertFileExists($packageRoot.'/src/Support/System/Updates/UpdateException.php');
  }

  #[Test]
  public function extractor_rejects_invalid_package_name(): void
  {
    $archivePath = $this->makePackageArchive('invalid-name.zip', null, [
      'name' => 'wrong/package',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
        ],
      ],
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package validation failed');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  #[Test]
  public function extractor_rejects_invalid_webblocks_namespace_mapping(): void
  {
    $archivePath = $this->makePackageArchive('invalid-autoload.zip', null, [
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'packages/webblocks-cms/src/',
        ],
      ],
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package validation failed');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  #[Test]
  public function extractor_rejects_invalid_seeders_mapping_when_present(): void
  {
    $archivePath = $this->makePackageArchive('invalid-seeders.zip', null, [
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
          'WebBlocks\\Cms\\Database\\Seeders\\' => 'packages/webblocks-cms/database/seeders/',
        ],
      ],
    ]);
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package validation failed');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  #[Test]
  public function extractor_rejects_missing_src_directory(): void
  {
    $archivePath = $this->makeTemporaryDirectory('archive').'/missing-src.zip';
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString('composer.json', json_encode([
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'WebBlocks\\Cms\\' => 'src/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $archive->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package validation failed');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  #[Test]
  public function extractor_rejects_maintenance_repository_root_artifact_shape(): void
  {
    $archivePath = $this->makeTemporaryDirectory('archive').'/maintenance-root.zip';
    $destinationPath = $this->makeTemporaryDirectory('extract');

    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString('artisan', "<?php\n");
    $archive->addFromString('composer.json', json_encode([
      'name' => 'fklavyenet/webblocks-cms',
      'autoload' => [
        'psr-4' => [
          'App\\' => 'app/',
          'WebBlocks\\Cms\\' => 'packages/webblocks-cms/src/',
          'WebBlocks\\Cms\\Database\\Seeders\\' => 'packages/webblocks-cms/database/seeders/',
        ],
      ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $archive->addFromString('app/Support/WebBlocks.php', "<?php\n");
    $archive->addFromString('packages/webblocks-cms/src/Support/System/Updates/UpdateException.php', "<?php\n");
    $archive->close();

    $this->expectException(UpdateException::class);
    $this->expectExceptionMessage('Package validation failed');

    app(UpdatePackageExtractor::class)->extract($archivePath, $destinationPath);
  }

  private function makePackageArchive(string $archiveName, ?string $wrapperDirectory, array $composer): string
  {
    $archivePath = $this->makeTemporaryDirectory('archive').'/'.$archiveName;
    $prefix = $wrapperDirectory ? trim($wrapperDirectory, '/').'/' : '';

    $archive = new ZipArchive;
    $this->assertTrue($archive->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true);
    $archive->addFromString($prefix.'composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    $archive->addEmptyDir($prefix.'src');
    $archive->addFromString($prefix.'src/Support/System/Updates/UpdateException.php', "<?php\n");
    $archive->addEmptyDir($prefix.'config');
    $archive->addFromString($prefix.'config/webblocks-updates.php', "<?php\n");
    $archive->addEmptyDir($prefix.'resources/views');
    $archive->addFromString($prefix.'resources/views/placeholder.blade.php', '<div></div>');
    $archive->addEmptyDir($prefix.'database/seeders');
    $archive->addFromString($prefix.'database/seeders/PlaceholderSeeder.php', "<?php\n");
    $archive->addEmptyDir($prefix.'routes');
    $archive->addFromString($prefix.'routes/admin.php', "<?php\n");
    $archive->addEmptyDir($prefix.'public/cms');
    $archive->addFromString($prefix.'public/cms/package-boundary.json', '{}');
    $archive->close();

    return $archivePath;
  }

  private function makeTemporaryDirectory(string $prefix): string
  {
    $path = storage_path('app/testing-system-updates/'.$prefix.'-'.Str::uuid());
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
