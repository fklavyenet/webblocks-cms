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
            'wrapper_preset' => ['required', Rule::in(PageSlot::allowedWrapperPresets())],
        ];
    }

    public function validatedSettings(): array
    {
        $data = $this->validated();
        $preset = (string) $data['wrapper_preset'];
        $element = (string) $data['wrapper_element'];

        return match ($preset) {
            'dashboard-navbar' => [
                'wrapper_preset' => 'dashboard-navbar',
                'wrapper_element' => 'header',
            ],
            'dashboard-sidebar' => [
                'wrapper_preset' => 'dashboard-sidebar',
                'wrapper_element' => 'aside',
            ],
            'dashboard-main' => [
                'wrapper_preset' => 'dashboard-main',
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
