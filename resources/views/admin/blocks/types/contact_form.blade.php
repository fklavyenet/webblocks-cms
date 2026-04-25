@php
    $submitLabel = old('submit_label', $block->submit_label ?? 'Send message');
    $successMessage = old('success_message', $block->success_message ?? config('contact.success_message'));
    $recipientEmail = old('recipient_email', $block->setting('recipient_email'));
    $sendEmailNotification = (bool) old('send_email_notification', $block->setting('send_email_notification', true));
    $storeSubmissions = (bool) old('store_submissions', $block->setting('store_submissions', true));
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">Reusable Contact Block</div>
            <div>Messages are always saved first. Email notification runs after persistence so inbox records survive delivery failures.</div>
            @if (isset($activeLocale) && $block->supportsTranslations())
                <div>Heading, intro text, submit label, and success message are translated per locale. Delivery settings stay shared across locales.</div>
            @endif
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="heading">Heading</label>
            <input id="heading" name="heading" class="wb-input" type="text" value="{{ old('heading', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="submit_label">Submit Label</label>
            <input id="submit_label" name="submit_label" class="wb-input" type="text" value="{{ $submitLabel }}" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="intro_text">Intro Text</label>
        <textarea id="intro_text" name="intro_text" class="wb-textarea" rows="4">{{ old('intro_text', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="success_message">Success Message</label>
        <textarea id="success_message" name="success_message" class="wb-textarea" rows="3" required>{{ $successMessage }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="recipient_email">Recipient Email Override</label>
            <input id="recipient_email" name="recipient_email" class="wb-input" type="email" value="{{ $recipientEmail }}" @disabled(isset($activeLocale) && ! $isDefaultLocale)>
            <span class="wb-text-sm wb-text-muted">
                @if (isset($activeLocale) && ! $isDefaultLocale)
                    Delivery settings stay shared. Switch to the default locale to update the recipient.
                @else
                    If empty, the form falls back to <code>CONTACT_RECIPIENT_EMAIL</code>.
                @endif
            </span>
        </div>

        <div class="wb-stack wb-gap-2">
            <label>Delivery Settings</label>

            <label class="wb-checkbox">
                <input type="hidden" name="send_email_notification" value="0">
                <input type="checkbox" name="send_email_notification" value="1" @checked($sendEmailNotification) @disabled(isset($activeLocale) && ! $isDefaultLocale)>
                <span>Send email notification</span>
            </label>

            <span class="wb-text-sm wb-text-muted">Contact messages are always stored first. Notification settings stay shared across locales.</span>
        </div>
    </div>
</div>
