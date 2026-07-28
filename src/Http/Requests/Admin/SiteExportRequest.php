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

    // The picker always submits one empty value, so that ticking nothing
    // arrives as an explicit empty selection rather than as no selection at
    // all — which would mean the whole site. That marker is not an id, and
    // leaving it in the array failed validation on every export.
    if ($this->has('page_ids')) {
      $this->merge([
        'page_ids' => array_values(array_filter(
          (array) $this->input('page_ids', []),
          static fn ($id) => $id !== '' && $id !== null,
        )),
      ]);
    }
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
