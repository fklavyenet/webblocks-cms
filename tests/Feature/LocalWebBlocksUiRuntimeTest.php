<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

class LocalWebBlocksUiRuntimeTest extends TestCase
{
  public function test_browser_runtime_uses_versioned_package_local_assets(): void
  {
    $base = '/cms/webblocks-ui/'.WebBlocks::UI_VERSION;

    $this->assertSame($base.'/webblocks-ui.css', WebBlocks::uiCssUrl());
    $this->assertSame($base.'/webblocks-icons.css', WebBlocks::iconsCssUrl());
    $this->assertSame($base.'/webblocks-ui.js', WebBlocks::uiJsUrl());
    $this->assertSame($base.'/webblocks-icons.json', WebBlocks::iconsManifestUrl());

    foreach ([WebBlocks::uiCssUrl(), WebBlocks::iconsCssUrl(), WebBlocks::uiJsUrl(), WebBlocks::iconsManifestUrl()] as $url) {
      $this->assertStringStartsWith('/cms/', $url);
      $this->assertStringNotContainsString('://', $url);
    }
  }

  public function test_local_runtime_matches_its_committed_checksum_manifest(): void
  {
    $root = dirname(__DIR__, 2);
    $directory = $root.'/public/cms/webblocks-ui/'.WebBlocks::UI_VERSION;
    $manifest = json_decode((string) file_get_contents($directory.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);

    $this->assertSame('webblocks-ui', $manifest['product']);
    $this->assertSame(WebBlocks::UI_VERSION, $manifest['version']);

    foreach ($manifest['artifacts'] as $file => $metadata) {
      $path = $directory.'/'.$file;

      $this->assertFileExists($path);
      $this->assertSame($metadata['sha256'], hash_file('sha256', $path));
      $this->assertSame($metadata['bytes'], filesize($path));
    }

    $this->assertSame(
      hash_file('sha256', $directory.'/webblocks-icons.json'),
      hash_file('sha256', $root.'/database/content/icons/webblocks-ui-'.WebBlocks::UI_VERSION.'.json')
    );
  }

  public function test_runtime_layouts_do_not_reference_the_webblocks_ui_cdn(): void
  {
    $root = dirname(__DIR__, 2);

    foreach ([
      'resources/views/layouts/admin.blade.php',
      'resources/views/layouts/guest.blade.php',
      'resources/views/layouts/public.blade.php',
      'resources/views/errors/404.blade.php',
    ] as $relativePath) {
      $contents = (string) file_get_contents($root.'/'.$relativePath);

      $this->assertStringNotContainsString('cdn.jsdelivr.net/gh/fklavyenet/webblocks-ui', $contents);
      $this->assertStringContainsString('WebBlocks::uiCssUrl()', $contents);
    }
  }
}
