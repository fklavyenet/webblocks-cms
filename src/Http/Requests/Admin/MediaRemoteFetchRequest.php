<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Media\MediaUploadRules;

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
    ] + MediaUploadRules::metadata();
  }
}
