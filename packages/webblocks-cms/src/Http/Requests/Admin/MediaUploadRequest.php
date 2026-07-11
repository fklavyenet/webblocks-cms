<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Media\MediaMimeTypes;

class MediaUploadRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'file' => [
        'required',
        'file',
        'max:51200',
        'mimetypes:'.MediaMimeTypes::rule(),
      ],
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ];
  }
}
