<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Facades\Storage;
use Throwable;
use WebBlocks\Cms\Models\Media;

class MediaTransformService
{
  public function url(Media $media, string $variant): ?string
  {
    if (! $media->isImage() || $media->visibility !== 'public') {
      return $media->url();
    }

    $definition = config('media_transforms.variants.'.$variant);

    if (! is_array($definition) || ! $this->supports($media)) {
      return $media->url();
    }

    $path = $this->path($media, $variant, $definition);
    $disk = Storage::disk($media->disk);

    if (! $disk->exists($path) && ! $this->generate($media, $path, $definition)) {
      return $media->url();
    }

    return $disk->url($path);
  }

  public function variants(Media $media): array
  {
    return collect(config('media_transforms.variants', []))
      ->map(fn (array $definition, string $name) => [
        'name' => $name,
        'width' => $definition['width'] ?? null,
        'height' => $definition['height'] ?? null,
        'fit' => $definition['fit'] ?? 'contain',
        'url' => $this->url($media, $name),
      ])
      ->values()
      ->all();
  }

  public function regenerate(Media $media): void
  {
    $this->clear($media);

    foreach (array_keys(config('media_transforms.variants', [])) as $variant) {
      $this->url($media, $variant);
    }
  }

  public function clear(Media $media): void
  {
    Storage::disk($media->disk)->deleteDirectory('media/transforms/'.$media->getKey());
  }

  private function supports(Media $media): bool
  {
    return in_array(strtolower((string) $media->extension), ['jpg', 'jpeg', 'png', 'webp'], true)
      && function_exists('imagecreatefromstring');
  }

  private function path(Media $media, string $variant, array $definition): string
  {
    $fingerprint = hash('sha256', implode('|', [
      $media->path,
      $media->size,
      $media->updated_at?->getTimestamp(),
      $media->focal_point_x,
      $media->focal_point_y,
      json_encode($definition),
    ]));

    return 'media/transforms/'.$media->getKey().'/'.substr($fingerprint, 0, 16).'/'.$variant.'.'.strtolower((string) $media->extension);
  }

  private function generate(Media $media, string $path, array $definition): bool
  {
    $disk = Storage::disk($media->disk);
    try {
      $bytes = $disk->get($media->path);
    } catch (Throwable) {
      return false;
    }
    $source = @imagecreatefromstring($bytes);

    if (! $source) {
      return false;
    }

    $sourceWidth = imagesx($source);
    $sourceHeight = imagesy($source);
    $targetWidth = min((int) ($definition['width'] ?? $sourceWidth), $sourceWidth);
    $configuredHeight = $definition['height'] ?? null;
    $targetHeight = $configuredHeight ? min((int) $configuredHeight, $sourceHeight) : (int) round($sourceHeight * ($targetWidth / $sourceWidth));
    $crop = ($definition['fit'] ?? 'contain') === 'crop' && $configuredHeight;
    $sourceX = 0;
    $sourceY = 0;
    $copyWidth = $sourceWidth;
    $copyHeight = $sourceHeight;

    if ($crop) {
      $targetRatio = $targetWidth / $targetHeight;
      $sourceRatio = $sourceWidth / $sourceHeight;

      if ($sourceRatio > $targetRatio) {
        $copyWidth = (int) round($sourceHeight * $targetRatio);
        $sourceX = (int) round(($sourceWidth * (float) ($media->focal_point_x ?? 0.5)) - ($copyWidth / 2));
        $sourceX = max(0, min($sourceX, $sourceWidth - $copyWidth));
      } else {
        $copyHeight = (int) round($sourceWidth / $targetRatio);
        $sourceY = (int) round(($sourceHeight * (float) ($media->focal_point_y ?? 0.5)) - ($copyHeight / 2));
        $sourceY = max(0, min($sourceY, $sourceHeight - $copyHeight));
      }
    }

    $target = imagecreatetruecolor($targetWidth, $targetHeight);

    if (strtolower((string) $media->extension) === 'png') {
      imagealphablending($target, false);
      imagesavealpha($target, true);
    }

    imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $copyWidth, $copyHeight);
    ob_start();
    $quality = (int) ($definition['quality'] ?? 85);
    $written = match (strtolower((string) $media->extension)) {
      'png' => imagepng($target, null, 6),
      'webp' => function_exists('imagewebp') && imagewebp($target, null, $quality),
      default => imagejpeg($target, null, $quality),
    };
    $output = ob_get_clean();
    imagedestroy($source);
    imagedestroy($target);

    return $written && is_string($output) && $disk->put($path, $output);
  }
}
