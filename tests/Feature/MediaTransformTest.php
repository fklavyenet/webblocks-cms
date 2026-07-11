<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Media\MediaTransformService;

class MediaTransformTest extends TestCase
{
  use RefreshDatabase;

  public function test_named_transform_is_generated_and_cached_with_focal_crop(): void
  {
    $migration = require base_path('packages/webblocks-cms/database/migrations/updates/2026_07_11_120000_ensure_media_focal_point.php');
    $migration->up();
    Storage::fake('public');
    $source = imagecreatetruecolor(1200, 800);
    ob_start();
    imagejpeg($source, null, 90);
    $bytes = ob_get_clean();
    imagedestroy($source);
    Storage::disk('public')->put('media/images/source.jpg', $bytes);

    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/source.jpg',
      'filename' => 'source.jpg',
      'original_name' => 'source.jpg',
      'extension' => 'jpg',
      'mime_type' => 'image/jpeg',
      'size' => strlen($bytes),
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'width' => 1200,
      'height' => 800,
      'focal_point_x' => 0.8,
      'focal_point_y' => 0.3,
    ]);

    $url = app(MediaTransformService::class)->url($media, 'card');
    $this->assertNotNull($url);
    $path = collect(Storage::disk('public')->allFiles('media/transforms/'.$media->id))->first();
    $this->assertNotNull($path);
    $this->assertSame([800, 600], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    $this->assertSame($url, app(MediaTransformService::class)->url($media, 'card'));
  }

  public function test_unsupported_images_fall_back_to_original_url(): void
  {
    Storage::fake('public');
    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/images/vector.svg',
      'filename' => 'vector.svg',
      'original_name' => 'vector.svg',
      'extension' => 'svg',
      'mime_type' => 'image/svg+xml',
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
    ]);

    $this->assertSame($media->url(), app(MediaTransformService::class)->url($media, 'thumbnail'));
  }
}
