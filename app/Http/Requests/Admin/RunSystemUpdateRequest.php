<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunSystemUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'acknowledge_backup_risk' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acknowledge_backup_risk.accepted' => 'Confirm that automatic backups are not created before update in this version.',
        ];
    }
}
