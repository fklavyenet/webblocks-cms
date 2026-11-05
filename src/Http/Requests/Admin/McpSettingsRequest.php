<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Support\Mcp\McpConfiguration;

final class McpSettingsRequest extends FormRequest
{
  public function rules(): array
  {
    return ['mcp_enabled' => ['required', 'boolean']];
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
