<?php

namespace App\Http\Requests\Admin;

use App\Support\Pages\PageIndexState;
use Illuminate\Foundation\Http\FormRequest;

class SyncPageLayoutSlotsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'return_url' => ['nullable', 'string'],
        ];
    }

    public function validatedReturnUrl(): ?string
    {
        return app(PageIndexState::class)->sanitizeReturnUrl($this->input('return_url'));
    }
}
