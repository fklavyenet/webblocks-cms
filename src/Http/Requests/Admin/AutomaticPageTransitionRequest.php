<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AutomaticPageTransitionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('activate', $this->route('automaticPage')) ?? false;
  }

  public function rules(): array
  {
    return ['digest' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']];
  }
}
