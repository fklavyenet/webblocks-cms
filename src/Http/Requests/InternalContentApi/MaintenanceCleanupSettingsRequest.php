<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Installation-wide API capability middleware owns authorization.
  }

  public function rules(): array
  {
    return [
      'automatic' => ['sometimes', 'array:asset-revisions,media-variants,temporary-workspaces,page-revisions,shared-slot-revisions'],
      'automatic.*' => ['required', 'boolean'],
      'asset_revision_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_asset_revisions' => ['required', 'integer', 'min:1', 'max:1000'],
      'temporary_workspace_hours' => ['required', 'integer', 'min:1', 'max:8760'],
      'page_revision_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_page_revisions' => ['required', 'integer', 'min:1', 'max:1000'],
      'shared_slot_revision_days' => ['required', 'integer', 'min:1', 'max:3650'],
      'keep_latest_shared_slot_revisions' => ['required', 'integer', 'min:1', 'max:1000'],
    ];
  }
}
