<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\SystemUpdateRecoveryPolicy;
use WebBlocks\Cms\Queries\System\UnfinishedSystemUpdates;

class ReconcileSystemUpdateRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(SystemUpdateRecoveryPolicy::class)->reconcile($this->user());
  }

  public function rules(): array
  {
    return [
      'record_type' => ['required', Rule::in(array_keys(UnfinishedSystemUpdates::MODELS))],
      'record_id' => ['required', 'string', 'max:100', 'regex:/\A[0-9a-f-]+\z/i'],
      'reason' => ['required', 'string', 'min:10', 'max:500'],
    ];
  }
}
