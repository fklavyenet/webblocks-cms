<?php

namespace WebBlocks\Cms\Http\Requests\Mcp;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;

final class RegisterMcpClientRequest extends FormRequest
{
  public function validationData(): array
  {
    return $this->json()->all();
  }

  public function rules(): array
  {
    return [
      'client_name' => ['nullable', 'string', 'max:128'],
      'redirect_uris' => ['required', 'array', 'list', 'min:1', 'max:5'],
      'redirect_uris.*' => ['bail', 'required', 'string', 'max:2048', 'distinct:strict', function (string $key, string $uri, \Closure $fail): void {
        if (! McpConfiguration::validRedirectUri($uri)) {
          $fail('invalid_redirect_uri');
        }
      }],
      'token_endpoint_auth_method' => ['sometimes', 'required', Rule::in(['none'])],
      'grant_types' => ['sometimes', 'required', 'array', 'list', 'size:1'],
      'grant_types.*' => ['required', Rule::in(['authorization_code'])],
      'response_types' => ['sometimes', 'required', 'array', 'list', 'size:1'],
      'response_types.*' => ['required', Rule::in(['code'])],
    ];
  }

  protected function failedValidation(Validator $validator): void
  {
    $errors = $validator->errors();
    $invalidRedirect = $errors->has('redirect_uris') || collect($errors->keys())->contains(fn (string $key): bool => str_starts_with($key, 'redirect_uris.'));

    throw new HttpResponseException(response()->json([
      'error' => $invalidRedirect ? 'invalid_redirect_uri' : 'invalid_client_metadata',
    ], 400, ['Cache-Control' => 'no-store']));
  }
}
