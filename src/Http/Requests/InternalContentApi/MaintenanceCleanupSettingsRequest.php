<?php

namespace WebBlocks\Cms\Http\Requests\InternalContentApi;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\System\CleanupSettingsFields;

class MaintenanceCleanupSettingsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true; // Installation-wide API capability middleware owns authorization.
  }

  public function rules(): array
  {
    return CleanupSettingsFields::maintenanceRules();
  }
}
