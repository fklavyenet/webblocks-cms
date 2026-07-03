<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkDeleteSystemBackupsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return [
      'backup_ids' => ['required', 'array', 'min:1'],
      'backup_ids.*' => ['integer', 'distinct', 'exists:wbcms_system_backups,id'],
    ];
  }

  public function messages(): array
  {
    return [
      'backup_ids.required' => 'Select at least one backup to delete.',
      'backup_ids.array' => 'Select at least one backup to delete.',
      'backup_ids.min' => 'Select at least one backup to delete.',
      'backup_ids.*.exists' => 'One or more selected backups no longer exists.',
    ];
  }
}
