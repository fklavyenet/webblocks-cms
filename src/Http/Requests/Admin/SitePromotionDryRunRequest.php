<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SitePromotionDryRunRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->isSuperAdmin();
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'target_site_id' => (int) $this->input('target_site_id'),
      'apply_assets' => $this->boolean('apply_assets'),
      'strategy' => trim((string) $this->input('strategy', 'additive_update')),
    ]);
  }

  public function rules(): array
  {
    return [
      'archive' => ['required_without:archive_path', 'nullable', 'file', 'mimes:zip'],
      'archive_path' => ['required_without:archive', 'nullable', 'string', 'max:500'],
      'target_site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
      'strategy' => ['required', Rule::in(['additive_update', 'mirror'])],
      'apply_assets' => ['nullable', 'boolean'],
    ];
  }
}
