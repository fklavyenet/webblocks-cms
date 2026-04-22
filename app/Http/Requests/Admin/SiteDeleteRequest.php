<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SiteDeleteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'confirm_delete' => $this->boolean('confirm_delete'),
        ]);
    }

    public function rules(): array
    {
        return [
            'confirm_delete' => ['accepted'],
        ];
    }
}
