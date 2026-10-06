<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Models\IconCatalogItem;
use WebBlocks\Cms\Policies\PluginAppearancePolicy;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;

class PluginAppearanceRequest extends FormRequest
{
  protected $errorBag = 'pluginAppearance';

  public function authorize(): bool
  {
    return app(PluginAppearancePolicy::class)->update($this->user(), app(PluginRegistry::class)->get((string) $this->route('plugin')));
  }

  public function rules(): array
  {
    return [
      'sidebar_icon' => ['nullable', 'string', 'max:100', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::exists(IconCatalogItem::class, 'slug')->where('is_active', true)],
      'origin' => ['required', Rule::in(['settings', 'details'])],
      'site_id' => ['nullable', 'integer', 'min:1'],
    ];
  }

  public function messages(): array
  {
    return ['sidebar_icon.exists' => __('webblocks-cms::admin.plugin_appearance.invalid_icon')];
  }
}
