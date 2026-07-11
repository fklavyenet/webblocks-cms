<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Media\MediaIndexState;

class MediaUpdateRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'title' => ['nullable', 'string', 'max:255'],
      'alt_text' => ['nullable', 'string', 'max:255'],
      'caption' => ['nullable', 'string'],
      'description' => ['nullable', 'string'],
      'focal_point_x' => ['nullable', 'numeric', 'between:0,1'],
      'focal_point_y' => ['nullable', 'numeric', 'between:0,1'],
      'return_url' => ['nullable', 'string', 'max:2048'],
    ];
  }

  public function safeReturnUrl(): ?string
  {
    return app(MediaIndexState::class)->sanitizeReturnUrl($this->input('return_url'));
  }
}
