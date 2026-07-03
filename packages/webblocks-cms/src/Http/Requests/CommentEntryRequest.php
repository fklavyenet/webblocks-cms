<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;

class CommentEntryRequest extends FormRequest
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
      'author_name' => ['nullable', 'string', 'max:80'],
      'body' => ['required', 'string', 'min:2', 'max:1200'],
      '_form_check_name' => ['nullable', 'string', 'max:255'],
      'submitted_at' => ['required', 'integer'],
    ];
  }

  public function payload(): array
  {
    $data = $this->validated();

    return [
      'block_id' => (int) $data['block_id'],
      'page_id' => ! empty($data['page_id']) ? (int) $data['page_id'] : null,
      'source_url' => $data['source_url'] ?? null,
      'author_name' => trim((string) ($data['author_name'] ?? '')) ?: null,
      'body' => trim((string) $data['body']),
      'form_check_filled' => app(ContactFormCheck::class)->isFilled($this->all(), (int) $data['block_id']),
      'submitted_at' => (int) $data['submitted_at'],
    ];
  }

  protected function getRedirectUrl(): string
  {
    return app(ContactFormRedirects::class)->target($this->input('source_url'), $this->input('block_id'), url('/'));
  }
}
