<?php

namespace App\Http\Requests\Updates;

use Illuminate\Validation\Rule;

class ReleaseIndexRequest extends UpdateApiRequest
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
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product' => $this->route('product'),
            'channel' => $this->query('channel', config('webblocks-updates.server.default_channel', 'stable')),
            'limit' => $this->query('limit', 10),
        ]);
    }
}
