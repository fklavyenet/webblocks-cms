<?php

namespace WebBlocks\Cms\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Contact\ContactFormCheck;
use WebBlocks\Cms\Support\Contact\ContactFormRedirects;
use WebBlocks\Cms\Support\Translations\CmsTranslator;
use WebBlocks\Cms\Support\Translations\PublicLocaleContext;

class ContactMessageRequest extends FormRequest
{
  /**
   * The consent block is read during rules() and again while building the
   * payload. `false` is the "not looked up yet" marker so a genuine miss caches
   * as null instead of re-querying on every call.
   */
  private Block|null|false $resolvedConsentBlock = false;

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
      'consent.accepted' => $this->validationText('contact_form.consent_required'),
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
      // The wording is copied onto the submission, not just referenced: the
      // block's copy can be edited later, and a consent record that changes
      // meaning afterwards proves nothing.
      'consent_accepted_at' => $this->blockRequiresConsent() ? now() : null,
      'consent_label' => $this->blockRequiresConsent() ? $this->consentBlock()?->consent_label : null,
    ];
  }

  private function blockRequiresConsent(): bool
  {
    $block = $this->consentBlock();

    if (! $block) {
      return false;
    }

    return (bool) $block->setting('consent_required', false)
      && trim((string) ($block->consent_label ?? '')) !== '';
  }

  private function consentBlock(): ?Block
  {
    if ($this->resolvedConsentBlock !== false) {
      return $this->resolvedConsentBlock;
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

    return $this->resolvedConsentBlock = $block;
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
