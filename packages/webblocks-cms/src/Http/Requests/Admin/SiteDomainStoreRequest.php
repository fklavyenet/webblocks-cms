<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use App\Models\SiteDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;

class SiteDomainStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'domain' => app(SiteDomainNormalizer::class)->normalize($this->input('domain')),
            'is_primary' => $this->boolean('is_primary'),
            'redirect_to_primary' => $this->boolean('redirect_to_primary'),
            'status' => strtolower(trim((string) $this->input('status', SiteDomain::STATUS_ACTIVE))),
        ]);
    }

    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255', Rule::unique(SiteDomain::class, 'domain')],
            'is_primary' => ['nullable', 'boolean'],
            'redirect_to_primary' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in([SiteDomain::STATUS_ACTIVE, SiteDomain::STATUS_INACTIVE])],
        ];
    }
}
