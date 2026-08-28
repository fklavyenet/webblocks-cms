<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SupportProviderConnectRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') === true;
  }

  public function rules(): array
  {
    return ['provider_url' => ['required', 'url:https', 'max:2048']];
  }
}
