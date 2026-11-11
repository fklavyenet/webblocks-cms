<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class PanelLanguageDefaultRequest extends FormRequest
{
  public function rules(): array
  {
    return ['code' => ['required', 'string', 'max:12', 'regex:/\A[a-z]{2,3}(?:-[a-z0-9]{2,8}){0,2}\z/D']];
  }
}
