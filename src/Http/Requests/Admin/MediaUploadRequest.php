<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Media\MediaUploadRules;

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
      'file' => MediaUploadRules::file(),
    ] + MediaUploadRules::metadata();
  }

  public function messages(): array
  {
    return [
      'file.required' => __('webblocks-cms::admin-feedback.validation.media_file_required'),
      'file.mimetypes' => __('webblocks-cms::admin-feedback.validation.media_file_type'),
    ];
  }
}
