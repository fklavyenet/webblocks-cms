<?php

namespace WebBlocks\Cms\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\Media\MediaIndexState;
use WebBlocks\Cms\Support\Media\MediaUploadRules;

class MediaUpdateRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    $media = $this->route('media');

    return [
      'folder_id' => ['nullable', 'integer', 'exists:wbcms_media_folders,id'],
      'focal_point_x' => ['nullable', 'numeric', 'between:0,1'],
      'focal_point_y' => ['nullable', 'numeric', 'between:0,1'],
      'return_url' => ['nullable', 'string', 'max:2048'],
    ] + MediaUploadRules::metadata($media instanceof Media ? $media : null, $this->all());
  }

  public function safeReturnUrl(): ?string
  {
    return app(MediaIndexState::class)->sanitizeReturnUrl($this->input('return_url'));
  }
}
