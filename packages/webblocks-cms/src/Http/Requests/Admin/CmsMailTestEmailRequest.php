<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CmsMailTestEmailRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('access-system') ?? false;
  }

  public function rules(): array
  {
    return [
      'recipient_email' => ['required', 'email', 'max:255'],
    ];
  }

  public function recipientEmail(): string
  {
    return (string) $this->validated('recipient_email');
  }
}
