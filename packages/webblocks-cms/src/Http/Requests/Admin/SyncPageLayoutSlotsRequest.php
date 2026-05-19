<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Pages\PageIndexState;

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
