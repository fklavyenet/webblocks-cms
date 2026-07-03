<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class MediaRemoteFetchRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'source_url' => ['required', 'url:http,https', 'max:2048'],
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
    ];
  }
}
