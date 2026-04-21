<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class RunSystemBackupRestoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'acknowledge_restore_risk' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'acknowledge_restore_risk.accepted' => 'Confirm that this restore will replace the current database and uploads, and that a pre-restore safety backup should be created first.',
        ];
    }
}
