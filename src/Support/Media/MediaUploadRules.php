<?php

namespace WebBlocks\Cms\Support\Media;

final class MediaUploadRules
{
  public static function file(): array
  {
    return ['required', 'file', 'max:51200', 'mimetypes:'.MediaMimeTypes::rule()];
  }

  public static function metadata(): array
  {
    return [
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ];
  }
}
