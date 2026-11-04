<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Policies\SystemPagePolicy;

final class SystemPageDefaultsRequest extends FormRequest
{
  public function authorize(): bool
  {
    return app(SystemPagePolicy::class)->addDefaults($this, $this->route('page'));
  }

  public function rules(): array
  {
    return [];
  }
}
