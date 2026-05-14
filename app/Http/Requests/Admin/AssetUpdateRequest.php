<?php

namespace App\Http\Requests\Admin;

use App\Support\Media\MediaIndexState;
use Illuminate\Foundation\Http\FormRequest;

class AssetUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'integer', 'exists:asset_folders,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'alt_text' => ['nullable', 'string', 'max:255'],
            'caption' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'return_url' => ['nullable', 'string', 'max:2048'],
        ];
    }

    public function safeReturnUrl(): ?string
    {
        return app(MediaIndexState::class)->sanitizeReturnUrl($this->input('return_url'));
    }
}
