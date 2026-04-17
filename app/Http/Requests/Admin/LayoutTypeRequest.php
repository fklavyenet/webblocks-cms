<?php

namespace App\Http\Requests\Admin;

use App\Models\LayoutType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LayoutTypeRequest extends FormRequest
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
        $layoutType = $this->route('layout_type');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', Rule::unique(LayoutType::class, 'slug')->ignore($layoutType)],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_system' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published'])],
        ];
    }
}
