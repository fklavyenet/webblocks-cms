<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Translations\LanguagePackCatalog;
use WebBlocks\Cms\Support\Translations\PanelLanguageSource;

final class InstallCatalogLanguageRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('manage-panel-languages');
  }

  public function rules(): array
  {
    return [
      'code' => ['required', 'string', 'max:12', 'regex:/\A'.PanelLanguageSource::CODE_PATTERN.'\z/'],
      'version' => ['required', 'string', 'max:32', 'regex:/\A'.LanguagePackCatalog::VERSION_PATTERN.'\z/'],
      'format' => ['required', 'string', Rule::in(LanguagePackCatalog::FORMATS)],
      'checksum' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
    ];
  }

  public function messages(): array
  {
    return array_fill_keys(['code.*', 'version.*', 'format.*', 'checksum.*'], __('webblocks-cms::panel-languages.extensions_invalid_selection'));
  }

  protected function getRedirectUrl(): string
  {
    return route('admin.system.panel-languages.extensions.index');
  }
}
