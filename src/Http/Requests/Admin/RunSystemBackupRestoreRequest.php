<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunSystemBackupRestoreRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user() !== null;
  }

  public function rules(): array
  {
    return [
      'acknowledge_restore_risk' => ['accepted'],
    ];
  }

  public function messages(): array
  {
    return [
      'acknowledge_restore_risk.accepted' => __('webblocks-cms::admin-feedback.backups.restore_acknowledgement'),
    ];
  }
}
