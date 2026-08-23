<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\System\SystemSettings;

class MaintenanceCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return [
      'backup_cleanup_enabled' => ['required', 'boolean'],
      'backup_cleanup_pre_update_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'backup_cleanup_keep_latest_pre_update' => ['required', 'integer', 'min:1', 'max:100'],
      'backup_cleanup_restore_safety_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'backup_cleanup_content_apply_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'asset_revision_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_asset_revisions' => ['required', 'integer', 'min:1', 'max:1000'],
      'temporary_workspace_hours' => ['required', 'integer', 'min:1', 'max:8760'],
    ];
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['backup_cleanup_enabled' => $this->boolean('backup_cleanup_enabled')]);
  }

  public function settingsPayload(): array
  {
    return [
      SystemSettings::BACKUP_CLEANUP_ENABLED => $this->validated('backup_cleanup_enabled'),
      SystemSettings::BACKUP_CLEANUP_PRE_UPDATE_DAYS => $this->validated('backup_cleanup_pre_update_days'),
      SystemSettings::BACKUP_CLEANUP_KEEP_LATEST_PRE_UPDATE => $this->validated('backup_cleanup_keep_latest_pre_update'),
      SystemSettings::BACKUP_CLEANUP_RESTORE_SAFETY_DAYS => $this->validated('backup_cleanup_restore_safety_days'),
      SystemSettings::BACKUP_CLEANUP_CONTENT_APPLY_DAYS => $this->validated('backup_cleanup_content_apply_days'),
      SystemSettings::CLEANUP_ASSET_REVISION_DAYS => $this->validated('asset_revision_days'),
      SystemSettings::CLEANUP_KEEP_LATEST_ASSET_REVISIONS => $this->validated('keep_latest_asset_revisions'),
      SystemSettings::CLEANUP_TEMPORARY_WORKSPACE_HOURS => $this->validated('temporary_workspace_hours'),
    ];
  }
}
