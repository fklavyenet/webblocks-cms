<?php

namespace App\Http\Requests\Admin;

use App\Models\Site;
use Illuminate\Foundation\Http\FormRequest;

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
            'with_navigation' => $this->boolean('with_navigation'),
            'with_media' => $this->boolean('with_media'),
            'copy_media_files' => $this->boolean('copy_media_files'),
            'with_translations' => $this->boolean('with_translations'),
            'overwrite_target' => $this->boolean('overwrite_target'),
            'dry_run' => $this->boolean('dry_run'),
        ]);
    }
}
