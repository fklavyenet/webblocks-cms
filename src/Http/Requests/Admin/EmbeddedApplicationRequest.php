<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Support\Applications\ApplicationAuthoringFields;

class EmbeddedApplicationRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('access-system');
  }

  protected function prepareForValidation(): void
  {
    $this->merge(['is_enabled' => $this->boolean('is_enabled')]);
  }

  public function rules(): array
  {
    $application = $this->route('embedded_application');

    return [
      ...ApplicationAuthoringFields::identityRules($application),
      'entry_url' => ['nullable', 'required_if:render_mode,iframe', 'string', 'max:2048', 'regex:'.ApplicationAuthoringFields::ASSET_PATH_PATTERN],
      'mount_element' => ['nullable', 'required_if:render_mode,inline', Rule::in(['div', 'section', 'canvas'])],
      'mount_classes' => ['nullable', 'string', 'max:512', 'regex:/^[A-Za-z0-9_-]+(?:\s+[A-Za-z0-9_-]+)*$/'],
      'css_urls' => ['nullable', 'string', 'max:20000'],
      'js_assets' => ['nullable', 'array', 'max:20'],
      'js_assets.*' => ['array'],
      'js_assets.*.path' => ['nullable', 'string', 'max:2048', 'regex:'.ApplicationAuthoringFields::ASSET_PATH_PATTERN],
      'js_assets.*.type' => ['nullable', Rule::in(['classic', 'module'])],
      'js_assets.*.load_position' => ['nullable', Rule::in(['head', 'body_end'])],
      'supports_locale' => ['nullable', 'boolean'],
      'supports_theme' => ['nullable', 'boolean'],
      'supports_fullscreen' => ['nullable', 'boolean'],
      'settings' => ['nullable', 'array', 'max:20'],
      'settings.*' => ['array'],
      'settings.*.key' => ['nullable', 'string', 'max:64', 'regex:'.ApplicationAuthoringFields::SETTINGS_KEY_PATTERN],
      'settings.*.type' => ['nullable', Rule::in(['boolean', 'enum', 'integer', 'string'])],
      'settings.*.default' => ['nullable', 'string', 'max:2000'],
      'settings.*.values' => ['nullable', 'string', 'max:5000'],
      'settings.*.min' => ['nullable', 'integer'],
      'settings.*.max' => ['nullable', 'integer'],
      'settings.*.max_length' => ['nullable', 'integer', 'min:1', 'max:10000'],
      'is_enabled' => ['required', 'boolean'],
    ];
  }

  public function applicationData(): array
  {
    $data = $this->validated();
    $css = collect(preg_split('/\R/', (string) ($data['css_urls'] ?? '')))
      ->map(fn (string $url): string => trim($url))->filter()->values()->all();
    $js = collect($data['js_assets'] ?? [])->filter(fn (array $asset): bool => trim((string) ($asset['path'] ?? '')) !== '')
      ->map(fn (array $asset): array => [
        'path' => trim((string) $asset['path']),
        'type' => $asset['type'] ?? 'classic',
        'load_position' => $asset['load_position'] ?? 'body_end',
      ])->values()->all();
    $schema = collect($data['settings'] ?? [])->filter(fn (array $setting): bool => trim((string) ($setting['key'] ?? '')) !== '')
      ->mapWithKeys(function (array $setting): array {
        $definition = ['type' => $setting['type'] ?? 'string'];
        if ($definition['type'] === 'enum') {
          $definition['values'] = $this->enumValues($setting['values'] ?? null);
        }
        foreach (['min', 'max', 'max_length'] as $key) {
          if (($setting[$key] ?? '') !== '') {
            $definition[$key] = (int) $setting[$key];
          }
        }
        if (($setting['default'] ?? '') !== '') {
          $definition['default'] = match ($definition['type']) {
            'boolean' => filter_var($setting['default'], FILTER_VALIDATE_BOOL),
            'integer' => (int) $setting['default'],
            default => (string) $setting['default'],
          };
        }

        return [(string) $setting['key'] => $definition];
      })->all();

    return [
      'handle' => $data['handle'], 'name' => $data['name'], 'description' => $data['description'] ?? null,
      'version' => $data['version'], 'render_mode' => $data['render_mode'],
      'entry_url' => $data['render_mode'] === 'iframe' ? ($data['entry_url'] ?? null) : null,
      'mount_element' => $data['render_mode'] === 'inline' ? ($data['mount_element'] ?? 'div') : null,
      'mount_classes' => $data['render_mode'] === 'inline' ? ($data['mount_classes'] ?? null) : null,
      'css_assets' => $css, 'js_assets' => $js,
      'supports' => ['locale' => $this->boolean('supports_locale'), 'theme' => $this->boolean('supports_theme'), 'fullscreen' => $this->boolean('supports_fullscreen')],
      'settings_schema' => $schema, 'is_enabled' => $data['is_enabled'],
    ];
  }

  public function withValidator(Validator $validator): void
  {
    $validator->after(function (Validator $validator): void {
      $cssInput = $this->input('css_urls');
      $cssUrls = is_string($cssInput) ? preg_split('/\R/', $cssInput) : [];
      foreach ($cssUrls as $index => $url) {
        $url = trim($url);
        if ($url !== '' && preg_match(ApplicationAuthoringFields::ASSET_PATH_PATTERN, $url) !== 1) {
          $validator->errors()->add('css_urls', __('webblocks-cms::admin-feedback.validation.application_css_url', ['line' => $index + 1]));
        }
      }

      $settings = collect($this->input('settings', []))->filter(
        fn (mixed $setting): bool => is_array($setting) && is_string($setting['key'] ?? null) && trim($setting['key']) !== ''
      );
      if ($settings->pluck('key')->duplicates()->isNotEmpty()) {
        $validator->errors()->add('settings', __('webblocks-cms::admin-feedback.validation.application_setting_unique'));
      }
      foreach ($settings as $index => $setting) {
        if (($setting['type'] ?? 'string') === 'enum' && $this->enumValues($setting['values'] ?? null) === []) {
          $validator->errors()->add("settings.{$index}.values", __('webblocks-cms::admin-feedback.validation.application_enum_values'));
        }
      }
    });
  }

  public function messages(): array
  {
    return [
      'handle.required' => __('webblocks-cms::admin-feedback.validation.application_handle_required'),
      'name.required' => __('webblocks-cms::admin-feedback.validation.application_name_required'),
      'version.required' => __('webblocks-cms::admin-feedback.validation.application_version_required'),
      'render_mode.required' => __('webblocks-cms::admin-feedback.validation.application_mode_required'),
    ];
  }

  private function enumValues(mixed $input): array
  {
    if (! is_string($input)) {
      return [];
    }

    return array_values(array_filter(array_map(trim(...), explode(',', $input)), fn (string $value): bool => $value !== ''));
  }
}
