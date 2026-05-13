<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Models\PageLayout;
use App\Support\Pages\LayoutMarkup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PageLayoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isSuperAdmin();
    }

    protected function prepareForValidation(): void
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;
        $name = trim((string) $this->input('name'));
        $handle = trim((string) $this->input('handle'));

        $this->merge([
            'handle' => $pageLayout?->is_system
                ? $pageLayout->handle
                : Str::slug($handle !== '' ? $handle : $name),
            'shell_type' => $pageLayout?->shell_type ?: 'default',
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => (int) $this->input('sort_order', 0),
            'body_class' => LayoutMarkup::normalizeTokenList($this->input('body_class')),
        ]);
    }

    public function rules(): array
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;

        return [
            'handle' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique(PageLayout::class, 'handle')->ignore($pageLayout?->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'body_class' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! LayoutMarkup::hasValidTokenList($this->input('body_class'))) {
                $validator->errors()->add('body_class', 'Body Class must be a safe whitespace-separated class token list.');
            }
        });
    }

    public function validatedData(): array
    {
        $pageLayout = $this->route('page_layout');
        $pageLayout = $pageLayout instanceof PageLayout ? $pageLayout : null;
        $data = $this->validated();

        if ($pageLayout?->is_system) {
            $data['handle'] = $pageLayout->handle;
            $data['shell_type'] = $pageLayout->shell_type;
            $data['is_system'] = true;
        } else {
            $data['is_system'] = false;
            $data['shell_type'] = Page::normalizePublicShellType($pageLayout?->shell_type ?: 'default');
        }

        return $data;
    }
}
