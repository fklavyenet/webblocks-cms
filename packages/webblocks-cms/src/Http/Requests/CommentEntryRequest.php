<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

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

  public function messages(): array
  {
    return [
      'body.required' => $this->validationText('comments.body_required'),
      'body.min' => $this->validationText('comments.body_min'),
      'body.max' => $this->validationText('comments.body_max'),
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
    return app(ContactFormRedirects::class)->baseUrl($this->input('source_url'), url('/')).'#comments-'.((int) $this->input('block_id'));
  }

  private function validationText(string $key): string
  {
    $block = Block::query()->with(['page.translations.locale'])->find($this->input('block_id'));
    $locale = $block ? app(PublicLocaleContext::class)->forBlockSource($block, $this->input('source_url')) : app()->getLocale();

    return app(CmsTranslator::class)->get('validation.'.$key, $locale);
  }
}
