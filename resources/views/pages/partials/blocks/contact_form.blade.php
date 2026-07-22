@php
    $hasTargetedErrors = $errors->any() && (int) old('block_id') === $block->id;
    $resolvedLocaleCode = strtolower((string) ($block->getAttribute('resolved_locale_code') ?? app()->getLocale()));
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $contactFormCopy = [
        'submit' => $translator->get('blocks.contact_form.submit', $resolvedLocaleCode),
        'review' => $translator->get('blocks.contact_form.review', $resolvedLocaleCode),
        'name' => $translator->get('blocks.contact_form.name', $resolvedLocaleCode),
        'email' => $translator->get('blocks.contact_form.email', $resolvedLocaleCode),
        'subject' => $translator->get('blocks.contact_form.subject', $resolvedLocaleCode),
        'message' => $translator->get('blocks.contact_form.message', $resolvedLocaleCode),
        'storage' => $translator->get('blocks.contact_form.storage', $resolvedLocaleCode),
    ];
    $resolvedSubmitLabel = trim((string) ($block->submit_label ?? ''));
    $submitLabel = $resolvedSubmitLabel === '' || ($resolvedLocaleCode !== 'en' && $resolvedSubmitLabel === 'Send message')
        ? $contactFormCopy['submit']
        : $resolvedSubmitLabel;
    $formCheck = app(\WebBlocks\Cms\Support\Contact\ContactFormCheck::class);
    $formCheckName = $formCheck->fieldName($block);
@endphp

<section class="wb-card wb-public-contact-form-card" id="contact-form-{{ $block->id }}">
    <div class="wb-card-body wb-stack wb-gap-4">
        @if ($block->title)
            <div class="wb-stack wb-gap-2">
                <h2>{{ $block->title }}</h2>

                @if ($block->content)
                    <p>{{ $block->content }}</p>
                @endif
            </div>
        @elseif ($block->content)
            <p>{{ $block->content }}</p>
        @endif

        @if ($hasTargetedErrors)
            <div class="wb-alert wb-alert-danger">
                <div>
                    <div class="wb-alert-title">{{ $contactFormCopy['review'] }}</div>
                    <div>{{ $errors->first() }}</div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('contact-messages.store') }}" class="wb-stack wb-gap-3">
            @csrf
            <input type="hidden" name="block_id" value="{{ $block->id }}">
            <input type="hidden" name="page_id" value="{{ $page->id ?? $block->renderPageId() ?? $block->page_id }}">
            <input type="hidden" name="source_url" value="{{ request()->getRequestUri() }}">
            <input type="hidden" name="submitted_at" value="{{ now()->timestamp }}">
            <input type="hidden" name="_form_check_name" value="{{ $formCheck->signedFieldName($block) }}">

            <div class="wb-sr-only" inert aria-hidden="true">
                <label for="contact-form-check-{{ $block->id }}">Leave this field empty</label>
                <input id="contact-form-check-{{ $block->id }}" type="text" name="{{ $formCheckName }}" tabindex="-1" autocomplete="off">
            </div>

            <div class="wb-grid wb-grid-2">
                <div class="wb-stack wb-gap-1">
                    <label for="contact-name-{{ $block->id }}" class="wb-label">{{ $contactFormCopy['name'] }}</label>
                    <input id="contact-name-{{ $block->id }}" name="name" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('name') : '' }}" required>
                    @if ((int) old('block_id') === $block->id)
                        @foreach ($errors->get('name') as $message)
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @endforeach
                    @endif
                </div>

                <div class="wb-stack wb-gap-1">
                    <label for="contact-email-{{ $block->id }}" class="wb-label">{{ $contactFormCopy['email'] }}</label>
                    <input id="contact-email-{{ $block->id }}" name="email" type="email" class="wb-input" value="{{ old('block_id') == $block->id ? old('email') : '' }}" required>
                    @if ((int) old('block_id') === $block->id)
                        @foreach ($errors->get('email') as $message)
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @endforeach
                    @endif
                </div>
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="contact-subject-{{ $block->id }}" class="wb-label">{{ $contactFormCopy['subject'] }}</label>
                <input id="contact-subject-{{ $block->id }}" name="subject" type="text" class="wb-input" value="{{ old('block_id') == $block->id ? old('subject') : '' }}">
                @if ((int) old('block_id') === $block->id)
                    @foreach ($errors->get('subject') as $message)
                        <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                    @endforeach
                @endif
            </div>

            <div class="wb-stack wb-gap-1">
                <label for="contact-message-{{ $block->id }}" class="wb-label">{{ $contactFormCopy['message'] }}</label>
                <textarea id="contact-message-{{ $block->id }}" name="message" class="wb-textarea" rows="7" required>{{ old('block_id') == $block->id ? old('message') : '' }}</textarea>
                @if ((int) old('block_id') === $block->id)
                    @foreach ($errors->get('message') as $message)
                        <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                    @endforeach
                @endif
            </div>

            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <span class="wb-text-sm wb-text-muted">{{ $contactFormCopy['storage'] }}</span>
                <button type="submit" class="wb-btn wb-btn-primary">{{ $submitLabel }}</button>
            </div>
        </form>
    </div>
</section>
