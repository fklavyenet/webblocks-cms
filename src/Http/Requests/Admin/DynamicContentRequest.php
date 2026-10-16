<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DynamicContentRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-admin') ?? false;
  }

  public function rules(): array
  {
    return ['site' => ['nullable', 'integer', 'min:1'], 'source' => ['nullable', 'string', 'max:160'], 'search' => ['nullable', 'string', 'max:160'], 'tab' => ['nullable', Rule::in(['sources', 'loops', 'bindings', 'issues'])], 'page' => ['nullable', 'integer', 'min:1']];
  }
}
