<?php

namespace App\Http\Requests\Admin;

use App\Models\LayoutTypeSlot;
use App\Models\LayoutType;
use App\Models\Page;
use App\Models\PageSlot;
use App\Models\SlotType;
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
            'is_system' => ['nullable', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'published'])],
            'public_shell' => ['nullable', Rule::in(array_merge(Page::allowedPublicShellPresets(), ['dashboard']))],
            'slots' => ['nullable', 'array'],
            'slots.*.id' => ['nullable', 'integer', 'exists:layout_type_slots,id'],
            'slots.*.slot_type_id' => ['required', 'integer', 'exists:slot_types,id', 'distinct:strict'],
            'slots.*.enabled' => ['nullable', 'boolean'],
            'slots.*.ownership' => ['required', Rule::in(LayoutTypeSlot::ownershipOptions())],
            'slots.*.wrapper_preset' => ['required', Rule::in(PageSlot::acceptedWrapperPresets())],
            'slots.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function validatedData(): array
    {
        $data = $this->validated();
        $layoutType = $this->route('layout_type');
        $existingSettings = $layoutType instanceof LayoutType && is_array($layoutType->settings)
            ? $layoutType->settings
            : [];

        $data['settings'] = [
            'public_shell' => Page::normalizePublicShellPreset($data['public_shell'] ?? ($existingSettings['public_shell'] ?? 'default')),
        ];

        $data['slots'] = collect($data['slots'] ?? [])
            ->map(function (array $slot, int $index): array {
                $slot['enabled'] = (bool) ($slot['enabled'] ?? false);
                $slot['sort_order'] = $index;
                $slot['wrapper_preset'] = PageSlot::normalizeWrapperPreset($slot['wrapper_preset'] ?? 'default');
                $slot['wrapper_element'] = match ($slot['wrapper_preset']) {
                    'docs-navbar' => 'header',
                    'docs-sidebar' => 'aside',
                    'docs-main' => 'main',
                    default => PageSlot::defaultWrapperElementForSlug(
                        SlotType::query()->whereKey($slot['slot_type_id'] ?? null)->value('slug')
                    ),
                };

                return $slot;
            })
            ->filter(fn (array $slot) => $slot['enabled'])
            ->values()
            ->map(function (array $slot): array {
                unset($slot['enabled']);

                return $slot;
            })
            ->values()
            ->all();

        unset($data['public_shell']);

        return $data;
    }
}
