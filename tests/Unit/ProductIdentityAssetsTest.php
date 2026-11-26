<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\Support\WebBlocks;

class ProductIdentityAssetsTest extends TestCase
{
  #[Test]
  public function favicon_files_contain_the_declared_image_sizes(): void
  {
    $root = dirname(__DIR__, 2).'/public/cms/brand/';
    foreach (['favicon-16x16.png' => 16, 'favicon-32x32.png' => 32, 'apple-touch-icon.png' => 180] as $file => $size) {
      $image = getimagesize($root.$file);
      $this->assertSame([$size, $size, IMAGETYPE_PNG], array_slice($image, 0, 3), $file);
    }
    $svg = file_get_contents($root.'favicon.svg');
    $this->assertStringContainsString('viewBox="0 0 32 32"', $svg);
    $this->assertStringContainsString('#118AB2', $svg);
    $this->assertStringNotContainsString('var(', $svg);
    $ico = file_get_contents($root.'favicon.ico');
    $header = unpack('vreserved/vtype/vcount', substr($ico, 0, 6));
    $this->assertSame(['reserved' => 0, 'type' => 1, 'count' => 3], $header);
    $sizes = [];
    for ($index = 0; $index < $header['count']; $index++) {
      $entry = unpack('Cwidth/Cheight/Ccolors/Creserved/vplanes/vbits/Vbytes/Voffset', substr($ico, 6 + $index * 16, 16));
      $this->assertSame($entry['width'], $entry['height']);
      $this->assertGreaterThan(0, $entry['bytes']);
      $this->assertLessThanOrEqual(strlen($ico), $entry['offset'] + $entry['bytes']);
      // A correctly sized but fully transparent ICO is still unusable.
      $bitmap = substr($ico, $entry['offset'], $entry['bytes']);
      $dib = unpack('Vheader/Vwidth/Vheight/vplanes/vbits', substr($bitmap, 0, 16));
      $this->assertSame(40, $dib['header']);
      $this->assertSame(32, $dib['bits']);
      $alpha = [];
      for ($pixel = 0; $pixel < $entry['width'] * $entry['height']; $pixel++) {
        $alpha[] = ord($bitmap[40 + $pixel * 4 + 3]);
      }
      $this->assertGreaterThan(0, max($alpha), 'ICO artwork must contain visible pixels.');
      $this->assertSame(0, min($alpha), 'The approved mark keeps its transparent background.');
      $sizes[] = $entry['width'];
    }
    sort($sizes);
    $this->assertSame([16, 32, 48], $sizes);
  }

  #[Test]
  public function static_and_selected_product_icons_exist_in_the_pinned_runtime(): void
  {
    $root = dirname(__DIR__, 2);
    $runtime = $root.'/public/cms/webblocks-ui/'.WebBlocks::UI_VERSION;
    $registry = array_column(json_decode(file_get_contents($runtime.'/webblocks-icons.json'), true, flags: JSON_THROW_ON_ERROR), 'css_class');
    $css = file_get_contents($runtime.'/webblocks-icons.css');
    $files = [$root.'/src/Support/Admin/AdminNavigation.php', $root.'/src/Support/Plugins/PluginSidebarGroups.php'];
    foreach (['admin', 'auth', 'layouts'] as $directory) {
      $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root.'/resources/views/'.$directory));
      foreach ($iterator as $file) {
        if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
          $files[] = $file->getPathname();
        }
      }
    }
    $used = [];
    foreach ($files as $file) {
      // Match literals, including ternary selections, but exclude component
      // classes (wb-icon-field, wb-icon-lg) and dynamic authored catalog icons.
      preg_match_all('/[\s\x22\x27](wb-icon-[a-z0-9-]+)(?=[\s\x22\x27])/', file_get_contents($file), $matches);
      foreach ($matches[1] as $icon) {
        if (! preg_match('/^wb-icon-(?:field(?:-|$)|picker(?:-|$)|btn$|(?:xs|sm|md|lg|xl|2xl)$|tone-)/', $icon)) {
          $used[$icon] = true;
        }
      }
    }
    foreach (array_keys($used) as $icon) {
      if ($icon !== 'wb-icon-refresh-cw') {
        $this->assertContains($icon, $registry, $icon);
      }
      $this->assertMatchesRegularExpression('/\.'.preg_quote($icon, '/').'[\s,{]/', $css, $icon);
    }
    // This pin registers rotate-cw; refresh-cw is its explicitly documented
    // shipped CSS alias, not an invented catalog record or a runtime patch.
    $this->assertContains('wb-icon-rotate-cw', $registry);
    $this->assertStringContainsString(".wb-icon-rotate-cw,\n.wb-icon-refresh-cw,", $css);
    foreach (['layout-dashboard', 'user-round', 'users', 'settings', 'shield-check', 'languages', 'file-text', 'plug', 'history', 'eye', 'pencil', 'trash', 'plus', 'save', 'search', 'copy', 'download', 'external-link', 'palette'] as $icon) {
      $this->assertContains('wb-icon-'.$icon, $registry);
    }
  }
}
