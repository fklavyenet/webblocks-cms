<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;
use WebBlocks\Cms\Support\System\SystemSettings;

final class McpSettingsRequest extends FormRequest
{
  public function rules(): array
  {
    return [
      'mcp_enabled' => ['required', 'boolean'],
      'manage_mcp_scopes' => ['sometimes', 'accepted'],
      'mcp_scopes' => ['sometimes', 'array', 'list', 'max:'.count(SystemSettings::MCP_SCOPE_KEYS)],
      'mcp_scopes.*' => ['required', 'string', 'distinct', Rule::in(array_keys(SystemSettings::MCP_SCOPE_KEYS))],
    ];
  }

  public function settingsPayload(): array
  {
    $values = [SystemSettings::MCP_ENABLED => $this->boolean('mcp_enabled') ? '1' : '0'];
    if ($this->validated('manage_mcp_scopes')) {
      $allowed = $this->validated('mcp_scopes', []);
      foreach (SystemSettings::MCP_SCOPE_KEYS as $scope => $key) {
        $values[$key] = in_array($scope, $allowed, true) ? '1' : '0';
      }
    }

    return $values;
  }

  public function after(McpConfiguration $config): array
  {
    return [function (Validator $validator) use ($config): void {
      if ($this->boolean('mcp_enabled') && ! $config->ready()) {
        $validator->errors()->add('mcp_enabled', __('webblocks-cms::mcp.setup_required'));
      }
    }];
  }

  protected function getRedirectUrl(): string
  {
    return route('admin.system.settings.edit', ['tab' => 'mcp']);
  }
}
