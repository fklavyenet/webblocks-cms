<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class PublishUpdateReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+(?:\-[0-9A-Za-z\.-]+)?(?:\+[0-9A-Za-z\.-]+)?$/'],
            'channel' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'source_url' => ['nullable', 'url'],
            'tag' => ['nullable', 'string'],
        ];
    }
}
