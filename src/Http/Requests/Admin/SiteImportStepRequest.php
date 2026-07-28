<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\SiteDomain;
use WebBlocks\Cms\Models\SiteImport;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteHandle;

/**
 * The naming form, validated on the step that starts an import and on no other.
 *
 * A chunked import is many requests carrying the same form, which breaks the
 * run request's rules in two ways once a run is under way. The unique domain
 * rule starts failing against the import's own SiteDomain row the moment the
 * domains phase writes it, turning the last poll into a validation error on a
 * finished import. And a resume — a later visit, another tab — has no form at
 * all, so a required site_name would reject it outright.
 *
 * Everything after the first step reads its options back from what that step
 * recorded, which is also the only way to guarantee the site is not renamed
 * halfway through by a request that arrived with different values.
 */
class SiteImportStepRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    if ($this->continuesExistingImport()) {
      return;
    }

    $this->merge([
      'site_handle' => SiteHandle::normalize($this->input('site_handle')),
      'site_domain' => app(SiteDomainNormalizer::class)->normalize($this->input('site_domain')),
    ]);
  }

  public function rules(): array
  {
    if ($this->continuesExistingImport()) {
      return [];
    }

    return [
      'site_name' => ['required', 'string', 'max:255'],
      'site_handle' => ['nullable', 'string', 'max:255'],
      'site_domain' => ['nullable', 'string', 'max:255', Rule::unique(SiteDomain::class, 'domain')],
    ];
  }

  public function continuesExistingImport(): bool
  {
    $siteImport = $this->route('siteImport');

    return $siteImport instanceof SiteImport && $siteImport->resume_phase !== null;
  }
}
