<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Sites\SiteAssetStore;
use WebBlocks\Cms\Support\Sites\SitePublicDirectoryManager;
use WebBlocks\Cms\Tests\TestCase;

class SitePublicDirectoryManagerTest extends TestCase
{
  private string $temporaryPublicPath;

  protected function setUp(): void
  {
    parent::setUp();

    $this->temporaryPublicPath = sys_get_temp_dir().'/webblocks-site-assets-'.bin2hex(random_bytes(8));
    File::ensureDirectoryExists($this->temporaryPublicPath);
    $this->app->usePublicPath($this->temporaryPublicPath);
  }

  protected function tearDown(): void
  {
    File::deleteDirectory($this->temporaryPublicPath);

    parent::tearDown();
  }

  #[Test]
  public function missing_public_site_tree_is_ready_when_public_directory_is_writable(): void
  {
    $readiness = $this->app->make(SitePublicDirectoryManager::class)
      ->readiness($this->site(), SiteAssetStore::TYPE_CSS);

    $this->assertTrue($readiness['ready']);
    $this->assertTrue($readiness['writable']);
    $this->assertFalse($readiness['site_directory_exists']);
    $this->assertNull($readiness['problem']);
  }

  #[Test]
  public function first_write_creates_the_missing_site_css_tree(): void
  {
    $asset = $this->app->make(SiteAssetStore::class)
      ->write($this->site(), SiteAssetStore::TYPE_CSS, ':root { --brand: #123456; }', null);

    $this->assertTrue($asset['exists']);
    $this->assertSame(':root { --brand: #123456; }', $asset['contents']);
    $this->assertFileExists($this->temporaryPublicPath.'/site/example/css/site.css');
    $this->assertTrue($asset['readiness']['ready']);
  }

  private function site(): Site
  {
    return (new Site)->forceFill([
      'id' => 1,
      'handle' => 'example',
    ]);
  }
}
