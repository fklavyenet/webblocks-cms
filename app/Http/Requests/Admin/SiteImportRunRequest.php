<?php

namespace App\Http\Requests\Admin;

use App\Models\SiteDomain;
use App\Support\Sites\SiteDomainNormalizer;
use App\Support\Sites\SiteHandle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
