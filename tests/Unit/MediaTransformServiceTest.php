<?php

namespace WebBlocks\Cms\Tests\Unit;

use ReflectionMethod;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Media\MediaTransformService;
use WebBlocks\Cms\Tests\TestCase;

class MediaTransformServiceTest extends TestCase
{
  public function test_contain_dimensions_are_proportional_and_never_enlarged(): void
  {
    $service = app(MediaTransformService::class);
    $dimensions = new ReflectionMethod($service, 'outputDimensions');
    $media = new Media(['width' => 500, 'height' => 250]);

    $this->assertSame([500, 250], $dimensions->invoke($service, $media, [
      'width' => 1280,
      'height' => null,
      'fit' => 'contain',
    ]));
  }

  public function test_small_crop_preserves_configured_ratio_without_enlarging(): void
  {
    $service = app(MediaTransformService::class);
    $dimensions = new ReflectionMethod($service, 'outputDimensions');
    $media = new Media(['width' => 500, 'height' => 500]);

    $this->assertSame([500, 375], $dimensions->invoke($service, $media, [
      'width' => 800,
      'height' => 600,
      'fit' => 'crop',
    ]));
  }

  public function test_fingerprint_ignores_editorial_metadata_but_tracks_focal_point(): void
  {
    $service = app(MediaTransformService::class);
    $definition = ['width' => 800, 'height' => 600, 'fit' => 'crop'];
    $media = new Media([
      'disk' => 'public', 'path' => 'media/images/a.jpg', 'size' => 10,
      'mime_type' => 'image/jpeg', 'width' => 500, 'height' => 500,
      'title' => 'First', 'focal_point_x' => 0.5, 'focal_point_y' => 0.5,
    ]);
    $first = $service->fingerprint($media, $definition);
    $media->title = 'Editorial-only change';

    $this->assertSame($first, $service->fingerprint($media, $definition));

    $media->focal_point_x = 0.2;
    $this->assertNotSame($first, $service->fingerprint($media, $definition));
  }
}
