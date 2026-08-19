<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Applications\ApplicationAssetStore;
use WebBlocks\Cms\Tests\TestCase;

class ApplicationAssetStoreTest extends TestCase
{
  private string $temporaryPublicPath;

  private string $temporaryStoragePath;

  protected function setUp(): void
  {
    parent::setUp();

    $suffix = bin2hex(random_bytes(8));
    $this->temporaryPublicPath = sys_get_temp_dir().'/webblocks-application-assets-public-'.$suffix;
    $this->temporaryStoragePath = sys_get_temp_dir().'/webblocks-application-assets-storage-'.$suffix;
    File::ensureDirectoryExists($this->temporaryPublicPath);
    File::ensureDirectoryExists($this->temporaryStoragePath);
    $this->app->usePublicPath($this->temporaryPublicPath);
    $this->app->useStoragePath($this->temporaryStoragePath);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->temporaryPublicPath);
    File::deleteDirectory($this->temporaryStoragePath);

    parent::tearDown();
  }

  #[Test]
  public function it_writes_and_lists_scoped_css_and_javascript_assets(): void
  {
    $store = $this->app->make(ApplicationAssetStore::class);
    $css = $store->write($this->site(), $this->application(), 'css', 'embed.css', '.typing {}', null);
    $js = $store->write($this->site(), $this->application(), 'js', 'embed.js', 'console.log("typing");', null);

    $this->assertSame('/site/example/applications/typing/css/embed.css', $css['public_path']);
    $this->assertSame('/site/example/applications/typing/js/embed.js', $js['public_path']);
    $this->assertCount(2, $store->all($this->site(), $this->application()));
    $this->assertFileExists($this->temporaryPublicPath.'/site/example/applications/typing/css/embed.css');
  }

  #[Test]
  public function it_rejects_traversal_wrong_extensions_and_stale_checksums(): void
  {
    $store = $this->app->make(ApplicationAssetStore::class);

    foreach ([['css', '../embed.css'], ['css', 'embed.js'], ['svg', 'icon.svg']] as [$type, $filename]) {
      try {
        $store->write($this->site(), $this->application(), $type, $filename, 'x', null);
        $this->fail('Unsafe asset name was accepted.');
      } catch (RuntimeException) {
        $this->addToAssertionCount(1);
      }
    }

    $store->write($this->site(), $this->application(), 'css', 'embed.css', 'one', null);
    $this->expectException(RuntimeException::class);
    $store->write($this->site(), $this->application(), 'css', 'embed.css', 'two', str_repeat('0', 64));
  }

  #[Test]
  public function replacing_and_deleting_an_asset_preserves_revisions(): void
  {
    $store = $this->app->make(ApplicationAssetStore::class);
    $first = $store->write($this->site(), $this->application(), 'css', 'embed.css', 'one', null);
    $second = $store->write($this->site(), $this->application(), 'css', 'embed.css', 'two', $first['checksum']);
    $store->delete($this->site(), $this->application(), 'css', 'embed.css', $second['checksum']);

    $this->assertFileDoesNotExist($this->temporaryPublicPath.'/site/example/applications/typing/css/embed.css');
    $this->assertCount(2, File::allFiles($this->temporaryStoragePath.'/app/cms/application-assets/1/typing/css'));
  }

  private function site(): Site
  {
    return (new Site)->forceFill(['id' => 1, 'handle' => 'example']);
  }

  private function application(): EmbeddedApplication
  {
    return (new EmbeddedApplication)->forceFill(['handle' => 'typing']);
  }
}
