<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Pages\PageLocaleRestorePlanner;

class PageRevisionScopeRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user() !== null;
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['restore_scope' => $this->input('restore_scope', 'full_page')]);
  }

  public function rules(): array
  {
    return [
      'restore_scope' => ['required', 'string', Rule::in(PageLocaleRestorePlanner::SCOPES)],
      'locale_id' => ['nullable', 'integer', 'min:1', Rule::requiredIf($this->input('restore_scope', 'full_page') !== 'full_page')],
    ];
  }

  public function restoreScope(): string
  {
    return $this->validated('restore_scope', 'full_page');
  }

  public function restoreLocaleId(): ?int
  {
    return $this->restoreScope() === 'full_page' ? null : (int) $this->validated('locale_id');
  }
}
