<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactFormFields;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

class ContactMessageRequest extends FormRequest
{
  /**
   * The form block is read during validation and again while building the
   * payload. `false` is the "not looked up yet" marker so a genuine miss caches
   * as null instead of re-querying on every call.
   */
  private Block|null|false $resolvedFormBlock = false;

  public function authorize(): bool
  {
    return true;
  }

  protected function prepareForValidation(): void
  {
    $block = $this->resolvedBlock();
    foreach (array_keys(ContactFormFields::VISIBILITY) as $field) {
      if (! ContactFormFields::visible($block, $field)) {
        // Do not flash values that this form did not ask for after a failed POST.
        $this->getInputSource()->remove($field);
        $this->query->remove($field);
      }
    }
  }

  public function rules(): array
  {
    $block = $this->resolvedBlock();

    return [
      'block_id' => ['required', 'integer', 'exists:wbcms_blocks,id'],
      'page_id' => ['nullable', 'integer', 'exists:wbcms_pages,id'],
      'source_url' => ['nullable', 'string', 'max:2048'],
      'name' => ContactFormFields::visible($block, 'name') ? ['required', 'string', 'max:255'] : ['exclude'],
      'email' => ['required', 'email:rfc', 'max:255'],
      'subject' => ContactFormFields::visible($block, 'subject') ? ['nullable', 'string', 'max:255'] : ['exclude'],
      'message' => ['required', 'string'],
      '_form_check_name' => ['nullable', 'string', 'max:255'],
      '_form_stamp' => ['required', 'string', 'max:255'],
      // A client can drop the checkbox from the DOM, so the requirement is
      // re-read from the block rather than trusted from the submission.
      'consent' => [$this->blockRequiresConsent() ? 'accepted' : 'nullable'],
    ];
  }

  public function messages(): array
  {
    return [
      'name.required' => $this->validationText('contact_form.name_required'),
      'email.required' => $this->validationText('contact_form.email_required'),
      'email.email' => $this->validationText('contact_form.email_valid'),
      'message.required' => $this->validationText('contact_form.message_required'),
      'consent.accepted' => $this->validationText($this->consentNoticeIsMissing() ? 'contact_form.consent_unavailable' : 'contact_form.consent_required'),
    ];
  }

  public function after(): array
  {
    return [function (Validator $validator): void {
      // A forged checkbox value cannot stand in for notice wording that the
      // visitor was never shown. Reject before storage or notification.
      if ($this->consentNoticeIsMissing() && ! $validator->errors()->has('consent')) {
        $validator->errors()->add('consent', $this->validationText('contact_form.consent_unavailable'));
      }
    }];
  }

  public function payload(): array
  {
    $data = $this->validated();
    $subject = trim((string) ($data['subject'] ?? ''));

    return [
      'block_id' => (int) $data['block_id'],
      'page_id' => ! empty($data['page_id']) ? (int) $data['page_id'] : null,
      'source_url' => $data['source_url'] ?? null,
      'name' => array_key_exists('name', $data) ? trim((string) $data['name']) : null,
      'email' => trim((string) $data['email']),
      'subject' => $subject !== '' ? $subject : null,
      'message' => trim((string) $data['message']),
      'form_check_filled' => app(ContactFormCheck::class)->isFilled($this->all(), (int) $data['block_id']),
      'elapsed_seconds' => app(ContactFormCheck::class)->elapsedSeconds($data['_form_stamp'], (int) $data['block_id']),
      // The wording is copied onto the submission, not just referenced: the
      // block's copy can be edited later, and a consent record that changes
      // meaning afterwards proves nothing.
      'consent_accepted_at' => $this->blockRequiresConsent() ? now() : null,
      'consent_label' => $this->blockRequiresConsent() ? trim((string) $this->resolvedBlock()?->consent_label) : null,
    ];
  }

  private function blockRequiresConsent(): bool
  {
    $block = $this->resolvedBlock();

    if (! $block) {
      return false;
    }

    return (bool) $block->setting('consent_required', false);
  }

  private function consentNoticeIsMissing(): bool
  {
    return $this->blockRequiresConsent()
      && trim((string) ($this->resolvedBlock()?->consent_label ?? '')) === '';
  }

  private function resolvedBlock(): ?Block
  {
    if ($this->resolvedFormBlock !== false) {
      return $this->resolvedFormBlock;
    }

    $blockId = $this->input('block_id');

    $block = is_numeric($blockId) ? Block::query()->find((int) $blockId) : null;

    if ($block) {
      // consent_label lives on the translation row, so the block has to be read
      // through the same locale resolution the public form rendered with.
      $block = app(BlockTranslationResolver::class)->resolve(
        $block,
        app(PublicLocaleContext::class)->forBlockSource($block, $this->input('source_url'))
      );
    }

    return $this->resolvedFormBlock = $block;
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
