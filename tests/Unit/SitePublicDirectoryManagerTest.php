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

  #[Test]
  public function a_handle_change_moves_the_existing_asset_directory_to_the_new_handle(): void
  {
    $manager = $this->app->make(SitePublicDirectoryManager::class);

    $this->app->make(SiteAssetStore::class)
      ->write($this->site('old-handle'), SiteAssetStore::TYPE_CSS, ':root { --brand: #123456; }', null);

    $warnings = $manager->relocateAssetDirectory('old-handle', $this->site('new-handle'));

    $this->assertSame([], $warnings);
    $this->assertFileDoesNotExist($this->temporaryPublicPath.'/site/old-handle/css/site.css');
    $this->assertFileExists($this->temporaryPublicPath.'/site/new-handle/css/site.css');
    $this->assertSame(
      ':root { --brand: #123456; }',
      file_get_contents($this->temporaryPublicPath.'/site/new-handle/css/site.css')
    );
  }

  #[Test]
  public function relocation_is_a_no_op_when_the_old_directory_never_existed(): void
  {
    $warnings = $this->app->make(SitePublicDirectoryManager::class)
      ->relocateAssetDirectory('never-had-assets', $this->site('new-handle'));

    $this->assertSame([], $warnings);
    $this->assertDirectoryDoesNotExist($this->temporaryPublicPath.'/site/new-handle');
  }

  #[Test]
  public function relocation_is_a_no_op_when_the_handle_did_not_change(): void
  {
    $this->app->make(SiteAssetStore::class)
      ->write($this->site('unchanged'), SiteAssetStore::TYPE_CSS, ':root { --brand: #123456; }', null);

    $warnings = $this->app->make(SitePublicDirectoryManager::class)
      ->relocateAssetDirectory('unchanged', $this->site('unchanged'));

    $this->assertSame([], $warnings);
    $this->assertFileExists($this->temporaryPublicPath.'/site/unchanged/css/site.css');
  }

  #[Test]
  public function relocation_merges_into_an_already_existing_target_directory_without_losing_new_files(): void
  {
    $manager = $this->app->make(SitePublicDirectoryManager::class);
    $assetStore = $this->app->make(SiteAssetStore::class);

    $assetStore->write($this->site('old-handle'), SiteAssetStore::TYPE_CSS, ':root { --brand: #111111; }', null);
    $assetStore->write($this->site('new-handle'), SiteAssetStore::TYPE_JS, 'console.log("kept");', null);

    $warnings = $manager->relocateAssetDirectory('old-handle', $this->site('new-handle'));

    $this->assertSame([], $warnings);
    $this->assertFileExists($this->temporaryPublicPath.'/site/new-handle/css/site.css');
    $this->assertFileExists($this->temporaryPublicPath.'/site/new-handle/js/site.js');
    $this->assertDirectoryDoesNotExist($this->temporaryPublicPath.'/site/old-handle');
  }

  private function site(string $handle = 'example'): Site
  {
    return (new Site)->forceFill([
      'id' => 1,
      'handle' => $handle,
    ]);
  }
}
