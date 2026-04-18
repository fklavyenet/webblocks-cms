<?php

namespace App\Http\Requests\Updates;

use Illuminate\Validation\Rule;

class LatestReleaseRequest extends UpdateApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]+$/', Rule::in(config('webblocks-updates.products', []))],
            'channel' => ['nullable', 'string', Rule::in(config('webblocks-updates.channels', []))],
            'installed_version' => ['nullable', 'string', 'regex:/^\d+\.\d+\.\d+(?:\-[0-9A-Za-z\.-]+)?(?:\+[0-9A-Za-z\.-]+)?$/'],
            'php_version' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+){1,2}(?:\-[0-9A-Za-z\.-]+)?$/'],
            'laravel_version' => ['nullable', 'string', 'regex:/^\d+(?:\.\d+){1,2}(?:\-[0-9A-Za-z\.-]+)?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product' => $this->route('product'),
            'channel' => $this->query('channel', config('webblocks-updates.server.default_channel', 'stable')),
        ]);
    }
}
