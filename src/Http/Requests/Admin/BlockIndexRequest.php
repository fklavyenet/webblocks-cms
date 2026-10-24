<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BlockIndexRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->can('access-system');
  }

  public function rules(): array
  {
    return [
      'page_search' => ['nullable', 'string', 'max:200'],
      'page_options_page' => ['nullable', 'integer', 'min:1'],
    ];
  }
}
