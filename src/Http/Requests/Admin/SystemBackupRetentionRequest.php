<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\SystemBackup;

class SystemBackupRetentionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return $this->user()?->can('manageRetention', $this->route('backup') ?? SystemBackup::class) ?? false;
  }

  public function rules(): array
  {
    return [
      'is_protected' => ['required', 'boolean'],
      'retention_mode' => ['required', 'in:policy,never,expires'],
      'expires_at' => ['nullable', 'required_if:retention_mode,expires', 'date'],
    ];
  }
}
