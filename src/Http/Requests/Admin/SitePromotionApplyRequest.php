<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SitePromotionApplyRequest extends FormRequest
{
  public function authorize(): bool
  {
    return (bool) $this->user()?->isSuperAdmin();
  }

  public function rules(): array
  {
    return [
      'plan_token' => ['required', 'string', 'max:255'],
    ];
  }
}
