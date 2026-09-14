<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Applications\ApplicationPackageStore;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class ApplicationPackageStoreTest extends TestCase
{
  private string $temporaryStoragePath;

  private string $temporaryPublicPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->temporaryStoragePath = sys_get_temp_dir().'/webblocks-application-packages-'.bin2hex(random_bytes(8));
    $this->temporaryPublicPath = sys_get_temp_dir().'/webblocks-application-packages-public-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->temporaryStoragePath);
    File::ensureDirectoryExists($this->temporaryPublicPath);
    $this->app->useStoragePath($this->temporaryStoragePath);
    $this->app->usePublicPath($this->temporaryPublicPath);

    Schema::create('wbcms_embedded_applications', function (Blueprint $table): void {
      $table->id();
      $table->string('handle')->unique();
      $table->string('name');
      $table->string('version');
      $table->string('render_mode');
      $table->string('entry_url')->nullable();
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
    });
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->temporaryStoragePath);
    File::deleteDirectory($this->temporaryPublicPath);
    parent::tearDown();
  }

  #[Test]
  public function it_installs_an_immutable_versioned_package_and_activates_its_entry(): void
  {
    $application = EmbeddedApplication::query()->create([
      'handle' => 'balloon-pop', 'name' => 'Balloon Pop', 'version' => '2.3.0', 'render_mode' => 'iframe', 'is_enabled' => true,
    ]);
    $store = $this->app->make(ApplicationPackageStore::class);
    $result = $store->install($this->site(), $application, $this->package([
      'index.html' => '<script src="./assets/game.js"></script>',
      'assets/game.js' => 'fetch("./metadata.json")',
      'assets/background.webp' => 'webp',
      'metadata.json' => '{"name":"Balloon Pop"}',
      'locales/tr/manifest.json' => '{"play":"Oyna"}',
    ]));

    $this->assertSame('/webblocks-applications/balloon-pop/2.3.0/index.html', $result['entry_url']);
    $this->assertSame(5, $result['files']);
    $this->assertSame($result['entry_url'], $application->fresh()->entry_url);
    $this->assertSame('webp', file_get_contents($store->read($this->site(), $application, '2.3.0', 'assets/background.webp')['absolute_path']));

    $this->expectException(RuntimeException::class);
    $store->install($this->site(), $application, $this->package(['index.html' => 'replacement']));
  }

  #[Test]
  public function it_rejects_packages_without_an_entry_or_with_unsafe_files(): void
  {
    $application = EmbeddedApplication::query()->create([
      'handle' => 'typing', 'name' => 'Typing', 'version' => '1.0.0', 'render_mode' => 'iframe', 'is_enabled' => true,
    ]);
    $store = $this->app->make(ApplicationPackageStore::class);

    foreach ([['assets/game.js' => 'x'], ['index.html' => 'x', '../escape.js' => 'x'], ['index.html' => 'x', 'server.php' => 'x']] as $files) {
      try {
        $store->install($this->site(), $application, $this->package($files));
        $this->fail('Unsafe application package was accepted.');
      } catch (RuntimeException) {
        $this->addToAssertionCount(1);
      }
    }
  }

  private function package(array $files): UploadedFile
  {
    $path = tempnam(sys_get_temp_dir(), 'wbcms-app-package-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
      $archive->addFromString($name, $contents);
    }
    $archive->close();

    return new UploadedFile($path, 'application.zip', 'application/zip', null, true);
  }

  private function site(): Site
  {
    return (new Site)->forceFill(['id' => 1, 'handle' => 'webblocks-play']);
  }
}
