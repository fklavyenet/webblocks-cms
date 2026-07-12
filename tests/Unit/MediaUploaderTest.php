<?php

namespace WebBlocks\Cms\Tests\Unit;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use WebBlocks\Cms\Support\Media\MediaUploader;
use WebBlocks\Cms\Tests\TestCase;

class MediaUploaderTest extends TestCase
{
  public function test_store_file_returns_file_metadata_without_reading_upload_metadata(): void
  {
    Storage::fake('public');
    $file = UploadedFile::fake()->image('Original Name.jpg', 320, 180)->size(12);
    $stored = app(MediaUploader::class)->storeFile($file);

    $this->assertSame('Original Name.jpg', $stored['original_name']);
    $this->assertSame('jpg', $stored['extension']);
    $this->assertSame('image/jpeg', $stored['mime_type']);
    $this->assertSame(320, $stored['width']);
    $this->assertSame(180, $stored['height']);
    $this->assertSame('image', $stored['kind']);
    $this->assertStringStartsWith('media/images/original-name-', $stored['path']);
    Storage::disk('public')->assertExists($stored['path']);
  }
}
