<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class AssetFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $name = (string) $this->input('name');
        $slug = (string) $this->input('slug');

        $this->merge([
            'slug' => Str::slug($slug !== '' ? $slug : $name) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'parent_id' => ['nullable', 'integer', 'exists:asset_folders,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
        ];
    }
}
