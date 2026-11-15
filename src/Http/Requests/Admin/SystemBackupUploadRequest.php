<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SystemBackupUploadRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'archive' => ['required', 'file', 'mimes:zip', 'max:51200'],
    ];
  }

  public function messages(): array
  {
    return [
      'archive.required' => __('webblocks-cms::admin-feedback.validation.backup_archive_required'),
      'archive.mimes' => __('webblocks-cms::admin-feedback.validation.backup_archive_type'),
    ];
  }
}
