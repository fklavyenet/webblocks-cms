<?php

namespace App\Http\Requests\Updates;

use App\Support\System\Updates\UpdateApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

abstract class UpdateApiRequest extends FormRequest
{
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(UpdateApiResponse::error(
            'validation_failed',
            'The update request parameters are invalid.',
            422,
            [
                'validation' => $validator->errors()->toArray(),
            ],
        ));
    }
}
