<?php

namespace App\Http\Requests\Updates;

use Illuminate\Validation\Rule;

class PublishReleaseRequest extends UpdateApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', Rule::in(config('webblocks-updates.products', []))],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+(?:\-[0-9A-Za-z\.-]+)?(?:\+[0-9A-Za-z\.-]+)?$/'],
            'channel' => ['required', 'string', Rule::in(config('webblocks-updates.channels', []))],
            'name' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'changelog' => ['nullable', 'string'],
            'checksum_sha256' => ['required', 'string', 'size:64'],
            'package' => ['required', 'file', 'mimes:zip'],
            'published_at' => ['nullable', 'date'],
            'supported_from_version' => ['nullable', 'string'],
            'supported_until_version' => ['nullable', 'string'],
            'min_php_version' => ['nullable', 'string'],
            'min_laravel_version' => ['nullable', 'string'],
            'is_critical' => ['nullable', 'boolean'],
            'is_security' => ['nullable', 'boolean'],
        ];
    }
}
