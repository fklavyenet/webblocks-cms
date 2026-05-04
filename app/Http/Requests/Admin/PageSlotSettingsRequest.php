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

        return [
            'wrapper_preset' => $preset,
            'wrapper_element' => (string) $data['wrapper_element'],
        ];
    }
}
