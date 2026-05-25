<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteHandle;

class SiteImportRunRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $this->merge([
      'site_handle' => SiteHandle::normalize($this->input('site_handle')),
      'site_domain' => app(SiteDomainNormalizer::class)->normalize($this->input('site_domain')),
    ]);
  }

  public function rules(): array
  {
    return [
      'site_name' => ['required', 'string', 'max:255'],
      'site_handle' => ['nullable', 'string', 'max:255'],
      'site_domain' => ['nullable', 'string', 'max:255', Rule::unique(SiteDomain::class, 'domain')],
    ];
  }
}
