<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

class CreateSystemBackupRequest extends SystemBackupRetentionRequest
{
  public function rules(): array
  {
    return [
      'retention_mode' => ['required_with:is_protected,expires_at', 'in:policy,never,expires'],
      'is_protected' => ['required_with:retention_mode', 'boolean'],
      'expires_at' => ['nullable', 'required_if:retention_mode,expires', 'date', 'after:now'],
    ];
  }
}
