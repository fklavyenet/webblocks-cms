<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

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
    ];
  }

  public function tokenName(): string
  {
    return trim((string) $this->validated('name'));
  }
}
