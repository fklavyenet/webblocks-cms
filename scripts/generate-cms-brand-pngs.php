<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$rootBrandPath = $root.'/public/cms/brand';
$packageBrandPath = $root.'/packages/webblocks-cms/public/cms/brand';

if (! extension_loaded('gd')) {
  fwrite(STDERR, "The PHP GD extension is required to generate CMS brand PNG assets.\n");
  exit(1);
}

generateTransparentMark($rootBrandPath.'/favicon-16x16.png', 16, 16, 0, '#118AB2');
generateTransparentMark($rootBrandPath.'/favicon-32x32.png', 32, 32, 0, '#118AB2');
generateTransparentMark($rootBrandPath.'/apple-touch-icon.png', 180, 140, 20, '#118AB2');
generateAppIcon($rootBrandPath.'/icon-192x192.png', 192, 128);
generateAppIcon($rootBrandPath.'/icon-512x512.png', 512, 344);

foreach ([
  'favicon-16x16.png',
  'favicon-32x32.png',
  'apple-touch-icon.png',
  'icon-192x192.png',
  'icon-512x512.png',
] as $file) {
  if (! copy($rootBrandPath.'/'.$file, $packageBrandPath.'/'.$file)) {
    fwrite(STDERR, "Unable to copy generated CMS brand PNG to package path: {$file}\n");
    exit(1);
  }
}

function generateTransparentMark(string $path, int $canvasSize, int $markSize, int $offset, string $strokeColor): void
{
  $image = imagecreatetruecolor($canvasSize, $canvasSize);
  imagealphablending($image, false);
  imagesavealpha($image, true);

  $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
  imagefilledrectangle($image, 0, 0, $canvasSize, $canvasSize, $transparent);

  imagealphablending($image, true);
  drawCmsMark($image, $markSize / 128, $offset, $strokeColor);
  savePng($image, $path);
}

function generateAppIcon(string $path, int $canvasSize, int $markSize): void
{
  $image = imagecreatetruecolor($canvasSize, $canvasSize);
  imagealphablending($image, true);
  imagesavealpha($image, true);

  [$backgroundRed, $backgroundGreen, $backgroundBlue] = rgb('#063B4C');
  $background = imagecolorallocate($image, $backgroundRed, $backgroundGreen, $backgroundBlue);
  imagefilledrectangle($image, 0, 0, $canvasSize, $canvasSize, $background);

  drawCmsMark($image, $markSize / 128, (int) round(($canvasSize - $markSize) / 2), '#FFFFFF');
  savePng($image, $path);
}

function drawCmsMark(GdImage $image, float $scale, int $offset, string $strokeColor): void
{
  imageantialias($image, true);

  [$red, $green, $blue] = rgb($strokeColor);
  $color = imagecolorallocate($image, $red, $green, $blue);
  imagesetthickness($image, max(1, (int) round(8 * $scale)));

  roundedRectangle($image, point(14, $scale, $offset), point(14, $scale, $offset), point(114, $scale, $offset), point(114, $scale, $offset), 18 * $scale, $color);
  imageline($image, point(14, $scale, $offset), point(40, $scale, $offset), point(114, $scale, $offset), point(40, $scale, $offset), $color);
  imageline($image, point(14, $scale, $offset), point(88, $scale, $offset), point(114, $scale, $offset), point(88, $scale, $offset), $color);
  imageline($image, point(42, $scale, $offset), point(40, $scale, $offset), point(42, $scale, $offset), point(88, $scale, $offset), $color);
  imageline($image, point(86, $scale, $offset), point(40, $scale, $offset), point(86, $scale, $offset), point(88, $scale, $offset), $color);
}

function roundedRectangle(GdImage $image, int $x1, int $y1, int $x2, int $y2, float $radius, int $color): void
{
  $radius = (int) round($radius);

  imageline($image, $x1 + $radius, $y1, $x2 - $radius, $y1, $color);
  imageline($image, $x1 + $radius, $y2, $x2 - $radius, $y2, $color);
  imageline($image, $x1, $y1 + $radius, $x1, $y2 - $radius, $color);
  imageline($image, $x2, $y1 + $radius, $x2, $y2 - $radius, $color);
  imagearc($image, $x1 + $radius, $y1 + $radius, $radius * 2, $radius * 2, 180, 270, $color);
  imagearc($image, $x2 - $radius, $y1 + $radius, $radius * 2, $radius * 2, 270, 360, $color);
  imagearc($image, $x2 - $radius, $y2 - $radius, $radius * 2, $radius * 2, 0, 90, $color);
  imagearc($image, $x1 + $radius, $y2 - $radius, $radius * 2, $radius * 2, 90, 180, $color);
}

function point(float $coordinate, float $scale, int $offset): int
{
  return (int) round($offset + ($coordinate * $scale));
}

/**
 * @return array{0:int, 1:int, 2:int}
 */
function rgb(string $hex): array
{
  $hex = ltrim($hex, '#');

  return [
    hexdec(substr($hex, 0, 2)),
    hexdec(substr($hex, 2, 2)),
    hexdec(substr($hex, 4, 2)),
  ];
}

function savePng(GdImage $image, string $path): void
{
  if (! imagepng($image, $path)) {
    fwrite(STDERR, "Unable to write {$path}\n");
    imagedestroy($image);
    exit(1);
  }

  imagedestroy($image);
}
