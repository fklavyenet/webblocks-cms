<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\System\BackupRetentionSettings;

class BackupCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Installation-wide capability middleware owns authorization.
  }

  public function rules(): array
  {
    return [
      ...BackupRetentionSettings::rules(),
      'enabled' => ['required', 'boolean'],
      'pre_update_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_pre_update' => ['required', 'integer', 'min:1', 'max:100'],
      'restore_safety_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'content_apply_days' => ['required', 'integer', 'min:1', 'max:3650'],
    ];
  }
}
