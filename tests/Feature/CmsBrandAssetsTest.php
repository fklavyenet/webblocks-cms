<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CmsBrandAssetsTest extends TestCase
{
  private const BRAND_FILES = [
    'apple-touch-icon.png',
    'favicon-16x16.png',
    'favicon-32x32.png',
    'favicon.svg',
    'icon-192x192.png',
    'icon-512x512.png',
    'logo-mark-dark.svg',
    'logo-mark-inverse.svg',
    'logo-mark-mask.svg',
    'logo-mark-on-accent.svg',
    'logo-mark.svg',
    'logo.svg',
  ];

  private const PNG_DIMENSIONS = [
    'apple-touch-icon.png' => [180, 180],
    'favicon-16x16.png' => [16, 16],
    'favicon-32x32.png' => [32, 32],
    'icon-192x192.png' => [192, 192],
    'icon-512x512.png' => [512, 512],
  ];

  #[Test]
  public function cms_brand_assets_stay_canonical_and_package_aligned(): void
  {
    $rootBrandPath = public_path('cms/brand');
    $packageBrandPath = base_path('packages/webblocks-cms/public/cms/brand');

    $this->assertSame(self::BRAND_FILES, $this->brandFiles($rootBrandPath));
    $this->assertSame(self::BRAND_FILES, $this->brandFiles($packageBrandPath));

    foreach (self::BRAND_FILES as $file) {
      $this->assertSame(
        hash_file('sha256', $rootBrandPath.'/'.$file),
        hash_file('sha256', $packageBrandPath.'/'.$file),
        $file.' differs between root and package CMS brand assets.',
      );
    }
  }

  #[Test]
  public function cms_brand_png_assets_have_expected_dimensions_and_are_not_flat_color(): void
  {
    foreach (self::PNG_DIMENSIONS as $file => [$expectedWidth, $expectedHeight]) {
      $path = public_path('cms/brand/'.$file);
      [$width, $height] = getimagesize($path);

      $this->assertSame($expectedWidth, $width, $file.' width mismatch.');
      $this->assertSame($expectedHeight, $height, $file.' height mismatch.');
      $this->assertGreaterThan(1, $this->uniquePngColorCount($path), $file.' must contain visible mark pixels, not a flat fill.');
    }
  }

  /**
   * @return list<string>
   */
  private function brandFiles(string $path): array
  {
    $files = array_map('basename', glob($path.'/*') ?: []);
    sort($files);

    return $files;
  }

  private function uniquePngColorCount(string $path): int
  {
    $image = imagecreatefrompng($path);
    $this->assertNotFalse($image, 'Unable to read PNG '.$path);

    $colors = [];
    $width = imagesx($image);
    $height = imagesy($image);

    for ($y = 0; $y < $height; $y++) {
      for ($x = 0; $x < $width; $x++) {
        $colors[imagecolorat($image, $x, $y)] = true;

        if (count($colors) > 1) {
          imagedestroy($image);

          return count($colors);
        }
      }
    }

    imagedestroy($image);

    return count($colors);
  }
}
