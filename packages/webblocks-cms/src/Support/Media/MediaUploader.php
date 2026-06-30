<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use WebBlocks\Cms\Models\Media;

class MediaUploader
{
  public function upload(UploadedFile $file, array $data = [], ?int $uploadedBy = null): Media
  {
    return Media::query()->create([
      ...$this->storeFile($file),
      'folder_id' => $data['folder_id'] ?? null,
      'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
      'alt_text' => $data['alt_text'] ?? null,
      'caption' => $data['caption'] ?? null,
      'description' => $data['description'] ?? null,
      'uploaded_by' => $uploadedBy,
    ]);
  }

  public function storeFile(UploadedFile $file): array
  {
    $mimeType = $file->getMimeType();
    $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
    $kind = MediaKindResolver::resolve($mimeType, $extension);
    $disk = 'public';
    $directory = 'media/'.MediaKindResolver::directoryFor($kind);
    $filename = $this->buildFilename($file, $extension);
    $path = $file->storeAs($directory, $filename, $disk);
    $dimensions = $this->imageDimensions($file, $kind);

    return [
      'disk' => $disk,
      'path' => $path,
      'filename' => basename($path),
      'original_name' => $file->getClientOriginalName(),
      'extension' => $extension ?: null,
      'mime_type' => $mimeType,
      'size' => $file->getSize(),
      'kind' => $kind,
      'visibility' => 'public',
      'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
      'alt_text' => $data['alt_text'] ?? null,
      'caption' => $data['caption'] ?? null,
      'description' => $data['description'] ?? null,
      'width' => $dimensions['width'],
      'height' => $dimensions['height'],
      'duration' => null,
    ];
  }

  private function buildFilename(UploadedFile $file, string $extension): string
  {
    $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

    return trim($name !== '' ? $name : 'media').'-'.Str::lower(Str::random(10)).($extension !== '' ? '.'.$extension : '');
  }

  private function imageDimensions(UploadedFile $file, string $kind): array
  {
    if ($kind !== Media::KIND_IMAGE) {
      return ['width' => null, 'height' => null];
    }

    $size = @getimagesize($file->getRealPath());

    if (! is_array($size)) {
      return ['width' => null, 'height' => null];
    }

    return [
      'width' => $size[0] ?? null,
      'height' => $size[1] ?? null,
    ];
  }
}
