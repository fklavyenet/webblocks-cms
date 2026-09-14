<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApplicationAssetController;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;
use ZipArchive;

class InternalApplicationPackageApiTest extends TestCase
{
  private string $temporaryPublicPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->temporaryPublicPath = sys_get_temp_dir().'/webblocks-application-package-api-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->temporaryPublicPath);
    $this->app->usePublicPath($this->temporaryPublicPath);

    Schema::create('wbcms_embedded_applications', function (Blueprint $table): void {
      $table->id();
      $table->string('handle')->unique();
      $table->string('name');
      $table->text('description')->nullable();
      $table->string('version');
      $table->string('render_mode');
      $table->string('entry_url')->nullable();
      $table->string('mount_element')->nullable();
      $table->string('mount_classes')->nullable();
      $table->json('css_assets')->nullable();
      $table->json('js_assets')->nullable();
      $table->json('supports')->nullable();
      $table->json('settings_schema')->nullable();
      $table->boolean('is_enabled')->default(true);
      $table->timestamps();
    });
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->temporaryPublicPath);
    parent::tearDown();
  }

  #[Test]
  public function it_installs_a_multipart_package_and_returns_the_activated_application(): void
  {
    $application = $this->application();
    $request = Request::create('/webadmin/api/sites/1/applications/play-balloon-pop/package', 'POST', [], [], [
      'package' => $this->package(['index.html' => '<script src="./game.js"></script>', 'game.js' => 'void 0;', 'metadata.json' => '{}']),
    ]);

    $response = $this->app->make(InternalApplicationAssetController::class)->installPackage($request, $this->site(), $application->handle);
    $payload = $response->getData(true);

    $this->assertSame(200, $response->getStatusCode());
    $this->assertTrue($payload['ok']);
    $this->assertSame('1.0.1', $payload['package']['version']);
    $this->assertSame(3, $payload['package']['files']);
    $this->assertSame('/webblocks-applications/play-balloon-pop/1.0.1/index.html', $payload['package']['entry_url']);
    $this->assertSame($payload['package']['entry_url'], $payload['application']['entry']);
    $this->assertSame('application_package', $payload['writes'][0]['type']);
  }

  #[Test]
  public function it_returns_structured_validation_and_archive_errors(): void
  {
    $application = $this->application();
    $controller = $this->app->make(InternalApplicationAssetController::class);
    $missing = $controller->installPackage(Request::create('/package', 'POST'), $this->site(), $application->handle);
    $unsafe = $controller->installPackage(Request::create('/package', 'POST', [], [], [
      'package' => $this->package(['index.html' => 'ok', '../escape.js' => 'no']),
    ]), $this->site(), $application->handle);

    $this->assertSame(422, $missing->getStatusCode());
    $this->assertSame('application_package.package', $missing->getData(true)['errors'][0]['path']);
    $this->assertSame(422, $unsafe->getStatusCode());
    $this->assertSame('application_package', $unsafe->getData(true)['errors'][0]['path']);
  }

  private function application(): EmbeddedApplication
  {
    return EmbeddedApplication::query()->firstOrCreate(['handle' => 'play-balloon-pop'], [
      'name' => 'Balloon Pop', 'version' => '1.0.1', 'render_mode' => 'iframe', 'entry_url' => '/old.html', 'is_enabled' => true,
    ]);
  }

  private function site(): Site
  {
    return (new Site)->forceFill(['id' => 1, 'handle' => 'webblocks-play']);
  }

  private function package(array $files): UploadedFile
  {
    $path = tempnam(sys_get_temp_dir(), 'wbcms-api-package-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
      $archive->addFromString($name, $contents);
    }
    $archive->close();

    return new UploadedFile($path, 'application.zip', 'application/zip', null, true);
  }
}
