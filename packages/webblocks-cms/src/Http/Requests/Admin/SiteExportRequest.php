<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteExportRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->isSuperAdmin();
  }

  protected function prepareForValidation(): void
  {
    $routeSite = $this->route('site');

    $this->merge([
      'site_id' => $routeSite?->id ?? $this->input('site_id'),
      'includes_media' => $this->boolean('includes_media'),
    ]);
  }

  public function rules(): array
  {
    return [
      'site_id' => ['required', 'integer', 'exists:sites,id'],
      'includes_media' => ['nullable', 'boolean'],
    ];
  }
}
