<?php

namespace App\Http\Requests\Updates;

use Illuminate\Validation\Rule;

class ShowReleaseRequest extends UpdateApiRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product' => ['required', 'string', 'regex:/^[A-Za-z0-9\-]+$/', Rule::in(config('webblocks-updates.products', []))],
            'version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+(?:\-[0-9A-Za-z\.-]+)?(?:\+[0-9A-Za-z\.-]+)?$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'product' => $this->route('product'),
            'version' => $this->route('version'),
        ]);
    }
}
