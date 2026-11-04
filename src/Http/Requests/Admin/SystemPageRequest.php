<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use WebBlocks\Cms\Policies\SystemPagePolicy;
use WebBlocks\Cms\Support\SystemPages\SystemPageRegistry;

final class SystemPageRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(SystemPagePolicy::class)->allows($this, 'create', (int) $this->input('site_id'));
  }

  public function rules(): array
  {
    return [
      'site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
      'key' => ['required', Rule::in(array_keys(app(SystemPageRegistry::class)->all()))],
    ];
  }
}
