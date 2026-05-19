<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Sites\SiteDomainNormalizer;
use WebBlocks\Cms\Support\Sites\SiteHandle;

class SiteCloneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'source_site_id' => ['required', 'integer', 'exists:sites,id'],
            'target_identifier' => ['required', 'string', 'max:255'],
            'target_name' => ['nullable', 'string', 'max:255'],
            'target_handle' => ['nullable', 'string', 'max:255'],
            'target_domain' => ['nullable', 'string', 'max:255'],
            'with_navigation' => ['nullable', 'boolean'],
            'with_media' => ['nullable', 'boolean'],
            'copy_media_files' => ['nullable', 'boolean'],
            'with_translations' => ['nullable', 'boolean'],
            'overwrite_target' => ['nullable', 'boolean'],
            'dry_run' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'target_handle' => SiteHandle::normalize($this->input('target_handle')),
            'target_domain' => app(SiteDomainNormalizer::class)->normalize($this->input('target_domain')),
            'with_navigation' => $this->boolean('with_navigation'),
            'with_media' => $this->boolean('with_media'),
            'copy_media_files' => $this->boolean('copy_media_files'),
            'with_translations' => $this->boolean('with_translations'),
            'overwrite_target' => $this->boolean('overwrite_target'),
            'dry_run' => $this->boolean('dry_run'),
        ]);
    }
}
