<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;

class CmsApiTokenRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') === true;
  }

  public function rules(): array
  {
    return [
      'name' => ['required', 'string', 'max:120'],
      'capabilities' => ['required', 'array', 'min:1'],
      'capabilities.*' => ['required', 'string', Rule::in(CmsApiTokenCapabilities::ALL)],
    ];
  }

  public function tokenName(): string
  {
    return trim((string) $this->validated('name'));
  }

  public function tokenCapabilities(): array
  {
    return array_values(array_unique($this->validated('capabilities')));
  }
}
