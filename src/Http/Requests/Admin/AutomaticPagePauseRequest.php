<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class AutomaticPagePauseRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('activate', $this->route('automaticPage')) ?? false;
  }

  public function rules(): array
  {
    return ['revision' => ['required', 'integer', 'min:1']];
  }
}
