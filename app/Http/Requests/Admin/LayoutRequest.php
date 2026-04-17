<?php

namespace App\Http\Requests\Admin;

use App\Models\Layout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LayoutRequest extends FormRequest
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
        $layout = $this->route('layout');

        return [
            'name' => ['required', 'string', 'max:255'],
            'layout_type_id' => ['required', 'integer', 'exists:layout_types,id'],
            'slug' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Layout::class, 'slug')->ignore($layout),
            ],
        ];
    }
}
