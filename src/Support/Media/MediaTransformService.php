<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Support\Facades\Storage;
use Throwable;
use WebBlocks\Cms\Models\Media;

class MediaTransformService
{
  public function url(Media $media, string $variant): ?string
  {
    return $this->result($media, $variant)->url;
  }

  public function result(Media $media, string $variant): MediaTransformResult
  {
    $original = $this->originalResult($media);

    if (! $media->isImage() || $media->visibility !== 'public') {
      return $original;
    }

    $definition = config('media_transforms.variants.'.$variant);

    if (! is_array($definition) || ! $this->supports($media)) {
      return $original;
    }

    if (($definition['fit'] ?? 'contain') === 'contain'
      && (int) $media->width <= (int) ($definition['width'] ?? 0)) {
      return $original;
    }

    $path = $this->path($media, $variant, $definition);
    $disk = Storage::disk($media->disk);

    if (! $disk->exists($path)) {
      $generated = $this->generate($media, $path, $definition);

      if ($generated === null) {
        return $original;
      }

      return $generated;
    }

    $dimensions = $this->outputDimensions($media, $definition);

    return new MediaTransformResult($disk->url($path), $dimensions[0], $dimensions[1], true, 'reused');
  }

  public function responsiveCandidates(Media $media, array $variants): array
  {
    return collect($variants)
      ->map(fn (string $variant) => $this->result($media, $variant))
      ->filter(fn (MediaTransformResult $result) => $result->url && $result->width && $result->height)
      ->unique(fn (MediaTransformResult $result) => $result->url)
      ->sortBy('width')
      ->values()
      ->all();
  }

  public function variants(Media $media, bool $generate = false): array
  {
    return collect(config('media_transforms.variants', []))
      ->map(function (array $definition, string $name) use ($media, $generate) {
        $result = $generate ? $this->result($media, $name) : $this->existingResult($media, $name, $definition);

        return [
          'name' => $name,
          'width' => $definition['width'] ?? null,
          'height' => $definition['height'] ?? null,
          'fit' => $definition['fit'] ?? 'contain',
          'url' => $result?->url,
          'available' => $result !== null,
        ];
      })->values()->all();
  }

  public function regenerate(Media $media): array
  {
    $this->clear($media);
    $counts = ['generated' => 0, 'reused' => 0, 'skipped' => 0, 'fallback' => 0, 'failed' => 0];

    foreach (array_keys(config('media_transforms.variants', [])) as $variant) {
      $result = $this->result($media, $variant);
      $key = array_key_exists($result->status, $counts) ? $result->status : 'failed';
      $counts[$key]++;
    }

    return $counts;
  }

  public function clear(Media $media): bool
  {
    return Storage::disk($media->disk)->deleteDirectory('media/transforms/'.$media->getKey());
  }

  public function fingerprint(Media $media, array $definition): string
  {
    return hash('sha256', implode('|', [
      $media->disk,
      $media->path,
      $media->size,
      $media->mime_type,
      $media->width,
      $media->height,
      $media->focal_point_x ?? 0.5,
      $media->focal_point_y ?? 0.5,
      json_encode($definition, JSON_THROW_ON_ERROR),
    ]));
  }

  public function prune(Media $media): int
  {
    $disk = Storage::disk($media->disk);
    $root = 'media/transforms/'.$media->getKey();
    $active = collect(config('media_transforms.variants', []))
      ->map(fn (array $definition) => substr($this->fingerprint($media, $definition), 0, 16))
      ->unique()->all();
    $pruned = 0;

    foreach ($disk->directories($root) as $directory) {
      if (! in_array(basename($directory), $active, true) && $disk->deleteDirectory($directory)) {
        $pruned++;
      }
    }

    return $pruned;
  }

  private function supports(Media $media): bool
  {
    $extension = strtolower((string) $media->extension);
    $writer = match ($extension) {
      'png' => 'imagepng',
      'webp' => 'imagewebp',
      'jpg', 'jpeg' => 'imagejpeg',
      default => null,
    };

    return function_exists('imagecreatefromstring') && $writer !== null && function_exists($writer);
  }

  private function path(Media $media, string $variant, array $definition): string
  {
    return 'media/transforms/'.$media->getKey().'/'.substr($this->fingerprint($media, $definition), 0, 16).'/'.$variant.'.'.strtolower((string) $media->extension);
  }

  private function existingResult(Media $media, string $variant, array $definition): ?MediaTransformResult
  {
    if (! $this->supports($media)) {
      return null;
    }

    $path = $this->path($media, $variant, $definition);
    $disk = Storage::disk($media->disk);

    if (! $disk->exists($path)) {
      return null;
    }

    $dimensions = $this->outputDimensions($media, $definition);

    return new MediaTransformResult($disk->url($path), $dimensions[0], $dimensions[1], true, 'reused');
  }

  private function originalResult(Media $media): MediaTransformResult
  {
    return new MediaTransformResult($media->url(), $media->width, $media->height, false, 'fallback');
  }

  private function outputDimensions(Media $media, array $definition): array
  {
    $sourceWidth = max(1, (int) $media->width);
    $sourceHeight = max(1, (int) $media->height);
    $configuredWidth = max(1, (int) ($definition['width'] ?? $sourceWidth));
    $configuredHeight = isset($definition['height']) ? max(1, (int) $definition['height']) : null;

    if (($definition['fit'] ?? 'contain') === 'crop' && $configuredHeight) {
      $scale = min(1, $sourceWidth / $configuredWidth, $sourceHeight / $configuredHeight);

      return [max(1, (int) floor($configuredWidth * $scale)), max(1, (int) floor($configuredHeight * $scale))];
    }

    $width = min($configuredWidth, $sourceWidth);

    return [$width, max(1, (int) round($sourceHeight * ($width / $sourceWidth)))];
  }

  private function generate(Media $media, string $path, array $definition): ?MediaTransformResult
  {
    $disk = Storage::disk($media->disk);
    $source = null;
    $target = null;
    $buffering = false;

    try {
      $bytes = $disk->get($media->path);
      $source = @imagecreatefromstring($bytes);

      if (! $source) {
        return null;
      }

      $sourceWidth = imagesx($source);
      $sourceHeight = imagesy($source);
      [$targetWidth, $targetHeight] = $this->outputDimensions($media, $definition);
      $copyWidth = $sourceWidth;
      $copyHeight = $sourceHeight;
      $sourceX = 0;
      $sourceY = 0;
      $crop = ($definition['fit'] ?? 'contain') === 'crop' && isset($definition['height']);

      if ($crop) {
        $targetRatio = ((int) $definition['width']) / ((int) $definition['height']);
        $sourceRatio = $sourceWidth / $sourceHeight;

        if ($sourceRatio > $targetRatio) {
          $copyWidth = max(1, (int) round($sourceHeight * $targetRatio));
          $sourceX = (int) round(($sourceWidth * (float) ($media->focal_point_x ?? 0.5)) - ($copyWidth / 2));
          $sourceX = max(0, min($sourceX, $sourceWidth - $copyWidth));
        } else {
          $copyHeight = max(1, (int) round($sourceWidth / $targetRatio));
          $sourceY = (int) round(($sourceHeight * (float) ($media->focal_point_y ?? 0.5)) - ($copyHeight / 2));
          $sourceY = max(0, min($sourceY, $sourceHeight - $copyHeight));
        }
      }

      $target = imagecreatetruecolor($targetWidth, $targetHeight);

      if (! $target) {
        return null;
      }

      if (strtolower((string) $media->extension) === 'png') {
        imagealphablending($target, false);
        imagesavealpha($target, true);
      }

      if (! imagecopyresampled($target, $source, 0, 0, $sourceX, $sourceY, $targetWidth, $targetHeight, $copyWidth, $copyHeight)) {
        return null;
      }

      ob_start();
      $buffering = true;
      $quality = (int) ($definition['quality'] ?? 85);
      $written = match (strtolower((string) $media->extension)) {
        'png' => imagepng($target, null, 6),
        'webp' => imagewebp($target, null, $quality),
        default => imagejpeg($target, null, $quality),
      };
      $output = ob_get_clean();
      $buffering = false;

      if (! $written || ! is_string($output) || $output === '' || ! $disk->put($path, $output)) {
        $disk->delete($path);

        return null;
      }

      return new MediaTransformResult($disk->url($path), $targetWidth, $targetHeight, true, 'generated');
    } catch (Throwable) {
      $disk->delete($path);

      return null;
    } finally {
      if ($buffering) {
        ob_end_clean();
      }
      if ($source) {
        imagedestroy($source);
      }
      if ($target) {
        imagedestroy($target);
      }
    }
  }
}
