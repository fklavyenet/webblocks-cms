<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class EmbeddedApplicationAssetRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('access-system');
  }

  public function rules(): array
  {
    $rules = [
      'site_id' => ['required', 'integer', 'exists:wbcms_sites,id'],
      'expected_checksum' => ['nullable', 'string', 'size:64'],
    ];

    if ($this->isMethod('post')) {
      $rules['asset'] = ['required', 'file', 'max:1024', 'extensions:css,js,html'];
    } elseif ($this->isMethod('put')) {
      $rules['contents'] = ['required', 'string', 'max:1000000'];
      $rules['expected_checksum'] = ['required', 'string', 'size:64'];
    } else {
      $rules['expected_checksum'] = ['required', 'string', 'size:64'];
    }

    return $rules;
  }
}
