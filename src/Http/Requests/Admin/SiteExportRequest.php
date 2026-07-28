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
      'site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
      'includes_media' => ['nullable', 'boolean'],
      // Absent means the whole site. An empty array is a package with no
      // pages, which is a legitimate thing to ask for and a confusing thing
      // to get by accident, so the form always submits something.
      'page_ids' => ['nullable', 'array'],
      'page_ids.*' => ['integer', 'exists:wbcms_pages,id'],
    ];
  }
}
