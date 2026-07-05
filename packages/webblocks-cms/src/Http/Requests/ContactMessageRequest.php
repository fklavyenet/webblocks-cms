<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

class ContactMessageRequest extends FormRequest
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
      'name' => ['required', 'string', 'max:255'],
      'email' => ['required', 'email:rfc', 'max:255'],
      'subject' => ['nullable', 'string', 'max:255'],
      'message' => ['required', 'string'],
      '_form_check_name' => ['nullable', 'string', 'max:255'],
      'submitted_at' => ['required', 'integer'],
    ];
  }

  public function messages(): array
  {
    return [
      'name.required' => $this->validationText('contact_form.name_required'),
      'email.required' => $this->validationText('contact_form.email_required'),
      'email.email' => $this->validationText('contact_form.email_valid'),
      'message.required' => $this->validationText('contact_form.message_required'),
    ];
  }

  public function payload(): array
  {
    $data = $this->validated();

    return [
      'block_id' => (int) $data['block_id'],
      'page_id' => ! empty($data['page_id']) ? (int) $data['page_id'] : null,
      'source_url' => $data['source_url'] ?? null,
      'name' => trim((string) $data['name']),
      'email' => trim((string) $data['email']),
      'subject' => trim((string) ($data['subject'] ?? '')) ?: null,
      'message' => trim((string) $data['message']),
      'form_check_filled' => app(ContactFormCheck::class)->isFilled($this->all(), (int) $data['block_id']),
      'submitted_at' => (int) $data['submitted_at'],
    ];
  }

  protected function getRedirectUrl(): string
  {
    return app(ContactFormRedirects::class)->target($this->input('source_url'), $this->input('block_id'), url('/'));
  }

  private function validationText(string $key): string
  {
    $block = Block::query()->with(['page.translations.locale'])->find($this->input('block_id'));
    $locale = $block ? app(PublicLocaleContext::class)->forBlockSource($block, $this->input('source_url')) : app()->getLocale();

    return app(CmsTranslator::class)->get('validation.'.$key, $locale);
  }
}
