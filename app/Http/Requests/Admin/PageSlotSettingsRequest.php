<?php

namespace App\Http\Requests\Admin;

use App\Models\PageSlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageSlotSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'wrapper_element' => ['required', Rule::in(PageSlot::allowedWrapperElements())],
            'wrapper_preset' => ['required', Rule::in(PageSlot::acceptedWrapperPresets())],
        ];
    }

    public function validatedSettings(): array
    {
        $data = $this->validated();
        $preset = PageSlot::normalizeWrapperPreset($data['wrapper_preset'] ?? 'default');
        $element = (string) $data['wrapper_element'];

        return match ($preset) {
            'docs-navbar' => [
                'wrapper_preset' => 'docs-navbar',
                'wrapper_element' => 'header',
            ],
            'docs-sidebar' => [
                'wrapper_preset' => 'docs-sidebar',
                'wrapper_element' => 'aside',
            ],
            'docs-main' => [
                'wrapper_preset' => 'docs-main',
                'wrapper_element' => 'main',
            ],
            'plain' => [
                'wrapper_preset' => 'plain',
                'wrapper_element' => $element,
            ],
            default => [
                'wrapper_preset' => 'default',
                'wrapper_element' => $element,
            ],
        };
    }
}
