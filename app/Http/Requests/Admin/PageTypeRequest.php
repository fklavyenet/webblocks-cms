<?php

namespace App\Http\Requests\Admin;

use App\Models\PageType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PageTypeRequest extends FormRequest
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
            'slug' => Str::slug($slug !== '' ? $slug : $name),
        ]);
    }

    public function rules(): array
    {
        $pageType = $this->route('page_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(PageType::class, 'slug')->ignore($pageType)],
            'description' => ['nullable', 'string'],
            'is_system' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }
}
