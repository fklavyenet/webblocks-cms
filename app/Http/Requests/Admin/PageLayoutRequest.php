<?php

namespace App\Http\Requests\Admin;

use App\Models\Page;
use App\Models\PageLayout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PageLayoutRequest extends FormRequest
{
    private bool $slotSchemaInvalid = false;

    private bool $wrapperSchemaInvalid = false;

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
        $slotSchema = $this->decodeSchemaField('slot_schema');
        $wrapperSchema = $this->decodeSchemaField('wrapper_schema');

        $this->merge([
            'handle' => $pageLayout?->is_system
                ? $pageLayout->handle
                : Str::slug($handle !== '' ? $handle : $name),
            'shell_type' => $pageLayout?->is_system
                ? $pageLayout->shell_type
                : Page::normalizePublicShellType($this->input('shell_type')),
            'is_active' => $this->boolean('is_active', true),
            'sort_order' => (int) $this->input('sort_order', 0),
            'slot_schema' => $slotSchema,
            'wrapper_schema' => $wrapperSchema,
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
            'shell_type' => ['required', Rule::in(Page::allowedPublicShellPresets())],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'slot_schema' => ['nullable', 'array'],
            'wrapper_schema' => ['nullable', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->slotSchemaInvalid) {
                $validator->errors()->add('slot_schema', 'Slot schema must be valid JSON and decode to an object or array.');
            }

            if ($this->wrapperSchemaInvalid) {
                $validator->errors()->add('wrapper_schema', 'Wrapper schema must be valid JSON and decode to an object or array.');
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
        }

        return $data;
    }

    private function decodeSchemaField(string $field): ?array
    {
        $value = $this->input($field);

        if ($value === null) {
            return null;
        }

        if (is_array($value)) {
            return $value;
        }

        $trimmed = trim((string) $value);

        if ($trimmed === '') {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        if (! is_array($decoded)) {
            if ($field === 'slot_schema') {
                $this->slotSchemaInvalid = true;
            }

            if ($field === 'wrapper_schema') {
                $this->wrapperSchemaInvalid = true;
            }

            return null;
        }

        return $decoded;
    }
}
