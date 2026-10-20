<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\EditorRecoveryPolicy;

class EditorPreviewRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(EditorRecoveryPolicy::class)->edit($this->user(), $this->route('page'));
  }

  public function rules(): array
  {
    $locales = $this->route('page')->availableSiteLocales()->pluck('code')->all();

    return [
      'locale' => ['required', 'string', Rule::in($locales)],
      'form_locale' => ['required', 'string', Rule::in($locales)],
      'block_id' => ['nullable', 'integer', 'min:1'],
      'entries' => ['present', 'array', 'max:512'],
      'entries.*.name' => ['required', 'string', 'max:190'],
      'entries.*.present' => ['required', 'boolean'],
      'entries.*.value' => ['nullable', 'string', 'max:349528'],
      'pagination' => ['sometimes', 'array', 'max:32'],
      'pagination.*' => ['integer', 'min:1', 'max:10000'],
    ];
  }
}
