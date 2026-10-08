<?php

namespace WebBlocks\Cms\Http\Requests\Plugins;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\Plugins\PluginRecoveryPolicy;

class PluginRecoveryRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(PluginRecoveryPolicy::class)->manage($this->user());
  }

  public function rules(): array
  {
    return ['operation' => ['required', Rule::in(['disable', 'restore'])]];
  }
}
