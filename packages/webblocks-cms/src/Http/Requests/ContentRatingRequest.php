<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

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

  public function messages(): array
  {
    return [
      'rating_value.required' => $this->validationText('rating.required'),
      'rating_value.integer' => $this->validationText('rating.integer'),
      'rating_value.min' => $this->validationText('rating.min'),
      'rating_value.max' => $this->validationText('rating.max'),
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
    return app(ContactFormRedirects::class)->baseUrl($this->input('source_url'), url('/')).'#rating-'.((int) $this->input('block_id'));
  }

  private function validationText(string $key): string
  {
    $block = Block::query()->with(['page.translations.locale'])->find($this->input('block_id'));
    $locale = $block ? app(PublicLocaleContext::class)->forBlockSource($block, $this->input('source_url')) : app()->getLocale();

    return app(CmsTranslator::class)->get('validation.'.$key, $locale);
  }
}
