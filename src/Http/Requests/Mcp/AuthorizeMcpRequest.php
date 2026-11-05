<?php

namespace WebBlocks\Cms\Http\Requests\Mcp;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\Mcp\McpOAuthClient;
use WebBlocks\Cms\Support\Mcp\McpOAuthClients;

final class AuthorizeMcpRequest extends FormRequest
{
  private McpOAuthClient $client;

  protected function prepareForValidation(): void
  {
    $id = $this->input('client_id');
    $client = is_string($id) ? app(McpOAuthClients::class)->find($id) : null;
    abort_unless($client, 400, __('webblocks-cms::mcp.invalid_request'));
    $this->client = $client;
  }

  public function client(): McpOAuthClient
  {
    return $this->client;
  }

  public function rules(McpConfiguration $config): array
  {
    return [
      'client_id' => ['required', 'string', Rule::in([$this->client->id])],
      'redirect_uri' => ['required', 'string', Rule::in($this->client->redirectUris)],
      'response_type' => ['required', Rule::in(['code'])],
      'code_challenge_method' => ['required', Rule::in(['S256'])],
      'code_challenge' => ['required', 'string', 'regex:/\A[A-Za-z0-9_-]{43}\z/'],
      'state' => ['nullable', 'string', 'max:512'],
      'scope' => ['bail', 'nullable', 'string', 'max:100', function (string $key, string $value, \Closure $fail): void {
        if (array_diff(explode(' ', $value), [McpConfiguration::READ, McpConfiguration::WRITE])) {
          $fail(__('webblocks-cms::mcp.invalid_request'));
        }
      }],
      'resource' => ['nullable', 'string', Rule::in([$config->resource()])],
      'decision' => [$this->isMethod('POST') ? 'required' : 'prohibited', Rule::in(['allow', 'deny'])],
      'site_id' => ['required_if:decision,allow', 'nullable', 'integer', 'min:1'],
    ];
  }

  protected function failedValidation(Validator $validator): void
  {
    abort(400, __('webblocks-cms::mcp.invalid_request'));
  }
}
