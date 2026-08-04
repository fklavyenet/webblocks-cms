<?php

namespace WebBlocks\Cms\Support\Install;

use Illuminate\Support\Facades\Storage;
use Throwable;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Media\MediaKindResolver;

/**
 * Copies a blueprint's bundled image into the site's own Media Library.
 *
 * Native blocks bind images by media id, so shipped starter artwork has to
 * become a real Media record before a block can use it — and serving it from
 * the site's own origin is the point. Hot-linking the product's brand assets
 * from a CDN would make every visitor of a customer's public page issue a
 * third-party request, which is exactly what `docs/ai-page-building-guide.md`
 * rules out when it says remote URLs do not belong in content.
 *
 * The record is an ordinary library entry: the operator can replace the file,
 * retitle it, or delete it and the block along with it.
 */
class StarterMediaImporter
{
  private const DISK = 'public';

  private const DIRECTORY = 'assets/starter';

  public function import(string $sourcePath, string $title): ?Media
  {
    if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
      return null;
    }

    $filename = basename($sourcePath);
    $path = self::DIRECTORY.'/'.$filename;

    $existing = Media::query()->where('disk', self::DISK)->where('path', $path)->first();

    if ($existing) {
      return $existing;
    }

    $contents = @file_get_contents($sourcePath);

    if ($contents === false || $contents === '') {
      return null;
    }

    try {
      Storage::disk(self::DISK)->put($path, $contents);
    } catch (Throwable) {
      // A read-only or unconfigured public disk is a deployment condition, not
      // a reason to fail an install over decorative starter artwork.
      return null;
    }

    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mimeType = $this->mimeTypeFor($extension);
    $dimensions = $this->dimensions($sourcePath);

    try {
      return Media::query()->create([
        'disk' => self::DISK,
        'path' => $path,
        'filename' => $filename,
        'original_name' => $filename,
        'extension' => $extension,
        'mime_type' => $mimeType,
        'size' => strlen($contents),
        'kind' => MediaKindResolver::resolve($mimeType, $extension),
        'visibility' => 'public',
        'title' => $title,
        'alt_text' => $title,
        'description' => 'Shipped with the WebBlocks CMS starter content. Safe to replace or delete.',
        'width' => $dimensions['width'],
        'height' => $dimensions['height'],
      ]);
    } catch (Throwable) {
      Storage::disk(self::DISK)->delete($path);

      return null;
    }
  }

  private function mimeTypeFor(string $extension): string
  {
    return match ($extension) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      'jpg', 'jpeg' => 'image/jpeg',
      'gif' => 'image/gif',
      default => 'application/octet-stream',
    };
  }

  /**
   * @return array{width: ?int, height: ?int}
   */
  private function dimensions(string $sourcePath): array
  {
    $size = @getimagesize($sourcePath);

    return [
      'width' => is_array($size) ? ($size[0] ?? null) : null,
      'height' => is_array($size) ? ($size[1] ?? null) : null,
    ];
  }
}
