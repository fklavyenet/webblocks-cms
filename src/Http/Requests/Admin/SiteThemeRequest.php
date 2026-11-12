<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Support\Theme\PublicThemes;

final class SiteThemeRequest extends FormRequest
{
  public function rules(): array
  {
    $action = last(explode('.', $this->route()->getName()));

    return match ($action) {
      'activate' => ['confirmation' => ['required', 'string', 'max:2048']],
      'restore' => ['expected' => ['required', 'integer', 'min:0'], 'revision' => ['nullable', 'integer', 'min:1']],
      default => [
        'version' => [$action === 'preview' ? 'required' : 'nullable', 'integer', 'min:1'],
        'page' => ['nullable', 'integer', 'min:1'],
        'page_list' => ['nullable', 'integer', 'min:1'],
        'button_corners' => ['nullable', Rule::in(PublicThemes::CORNERS)],
        'mode' => ['nullable', Rule::in(['light', 'dark', 'auto'])],
        'width' => ['nullable', Rule::in(['desktop', 'phone'])],
        'side' => ['nullable', Rule::in(['current', 'candidate'])],
        'locale' => ['nullable', 'string', 'max:12'],
        'omit_site_css' => ['nullable', 'boolean'],
      ],
    };
  }
}
