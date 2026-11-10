<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\System\BackupRetentionSettings;
use WebBlocks\Cms\Support\System\CleanupSettingsFields;

class MaintenanceCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return BackupRetentionSettings::rules() + CleanupSettingsFields::backupRules('backup_cleanup_') + CleanupSettingsFields::maintenanceRules();
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['backup_cleanup_enabled' => $this->boolean('backup_cleanup_enabled')]);
  }

  public function settingsPayload(): array
  {
    $data = $this->validated();

    return CleanupSettingsFields::backupPayload($data, 'backup_cleanup_') + CleanupSettingsFields::maintenancePayload($data);
  }
}
