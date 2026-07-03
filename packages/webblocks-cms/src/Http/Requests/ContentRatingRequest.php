<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;

class ContentRatingRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  public function rules(): array
  {
    return [
      'block_id' => ['required', 'integer', 'exists:wbcms_blocks,id'],
      'page_id' => ['nullable', 'integer', 'exists:wbcms_pages,id'],
      'source_url' => ['nullable', 'string', 'max:2048'],
      'rating_value' => ['required', 'integer', 'min:1', 'max:5'],
    ];
  }

  public function payload(): array
  {
    $data = $this->validated();

    return [
      'block_id' => (int) $data['block_id'],
      'page_id' => ! empty($data['page_id']) ? (int) $data['page_id'] : null,
      'source_url' => $data['source_url'] ?? null,
      'rating_value' => (int) $data['rating_value'],
    ];
  }

  protected function getRedirectUrl(): string
  {
    return app(ContactFormRedirects::class)->target($this->input('source_url'), $this->input('block_id'), url('/'));
  }
}
