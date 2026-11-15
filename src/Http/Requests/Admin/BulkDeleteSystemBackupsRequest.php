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
    $selectionRequired = __('webblocks-cms::admin-feedback.bulk.select_required', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.backup')]);

    return [
      'backup_ids.required' => $selectionRequired,
      'backup_ids.array' => $selectionRequired,
      'backup_ids.min' => $selectionRequired,
      'backup_ids.*.exists' => __('webblocks-cms::admin-feedback.bulk.selection_missing', ['entity' => __('webblocks-cms::admin-feedback.bulk_entities.backups')]),
    ];
  }
}
