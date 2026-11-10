<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\System\BackupRetentionSettings;
use WebBlocks\Cms\Support\System\CleanupSettingsFields;

class BackupCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Installation-wide capability middleware owns authorization.
  }

  public function rules(): array
  {
    return BackupRetentionSettings::rules() + CleanupSettingsFields::backupRules();
  }
}
