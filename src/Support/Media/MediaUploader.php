<?php

namespace WebBlocks\Cms\Support\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use WebBlocks\Cms\Models\Media;

class MediaUploader
{
  public function upload(UploadedFile $file, array $data = [], ?int $uploadedBy = null): Media
  {
    $stored = $this->storeFile($file);

    try {
      return DB::transaction(fn () => Media::query()->create([
        ...$stored,
        'folder_id' => $data['folder_id'] ?? null,
        'title' => ($data['title'] ?? null) ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
        'alt_text' => $data['alt_text'] ?? null,
        'caption' => $data['caption'] ?? null,
        'description' => $data['description'] ?? null,
        'uploaded_by' => $uploadedBy,
      ]));
    } catch (Throwable $exception) {
      Storage::disk($stored['disk'])->delete($stored['path']);

      throw $exception;
    }
  }

  public function storeFile(UploadedFile $file): array
  {
    $mimeType = $file->getMimeType();
    $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: '');
    $kind = MediaKindResolver::resolve($mimeType, $extension);
    $disk = 'public';
    $directory = 'media/'.MediaKindResolver::directoryFor($kind);
    $filename = $this->buildFilename($file, $extension);
    $dimensions = $this->imageDimensions($file, $kind);
    $size = $file->getSize();
    try {
      $path = $file->storeAs($directory, $filename, $disk);
      if ($path === false) {
        throw new RuntimeException('Media storage write failed.');
      }
    } catch (Throwable $exception) {
      Storage::disk($disk)->delete($directory.'/'.$filename);

      throw $exception;
    }

    return [
      'disk' => $disk,
      'path' => $path,
      'filename' => basename($path),
      'original_name' => $file->getClientOriginalName(),
      'extension' => $extension ?: null,
      'mime_type' => $mimeType,
      'size' => $size,
      'kind' => $kind,
      'visibility' => 'public',
      'width' => $dimensions['width'],
      'height' => $dimensions['height'],
      'duration' => null,
    ];
  }

  private function buildFilename(UploadedFile $file, string $extension): string
  {
    $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

    return Str::limit($name !== '' ? $name : 'media', 200, '').'-'.Str::lower(Str::random(10)).($extension !== '' ? '.'.$extension : '');
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
