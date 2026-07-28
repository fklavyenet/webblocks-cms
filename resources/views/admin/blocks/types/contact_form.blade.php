@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.contact_form.'.$key, $adminLocale);
    $submitLabel = old('submit_label', $block->submit_label ?? 'Send message');
    $successMessage = old('success_message', $block->success_message ?? config('contact.success_message'));
    $recipientEmail = old('recipient_email', $block->setting('recipient_email'));
    $sendEmailNotification = (bool) old('send_email_notification', $block->setting('send_email_notification', true));
    $storeSubmissions = (bool) old('store_submissions', $block->setting('store_submissions', true));
@endphp

<div class="wb-stack wb-gap-4">
    <div class="wb-alert wb-alert-info">
        <div>
            <div class="wb-alert-title">{{ $adminText('system_title') }}</div>
            <div>{{ $adminText('system_help') }}</div>
            @if (isset($activeLocale) && $block->supportsTranslations())
                <div>{{ $adminText('locale_help') }}</div>
            @endif
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="heading">{{ $adminText('heading_label') }}</label>
            <input id="heading" name="heading" class="wb-input" type="text" value="{{ old('heading', $block->title) }}">
        </div>

        <div class="wb-stack wb-gap-1">
            <label for="submit_label">{{ $adminText('submit_label') }}</label>
            <input id="submit_label" name="submit_label" class="wb-input" type="text" value="{{ $submitLabel }}" required>
        </div>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="intro_text">{{ $adminText('intro_label') }}</label>
        <textarea id="intro_text" name="intro_text" class="wb-textarea" rows="4">{{ old('intro_text', $block->content) }}</textarea>
    </div>

    <div class="wb-stack wb-gap-1">
        <label for="success_message">{{ $adminText('success_label') }}</label>
        <textarea id="success_message" name="success_message" class="wb-textarea" rows="3" required>{{ $successMessage }}</textarea>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-stack wb-gap-1">
            <label for="recipient_email">{{ $adminText('recipient_label') }}</label>
            <input id="recipient_email" name="recipient_email" class="wb-input" type="email" value="{{ $recipientEmail }}" @disabled(isset($activeLocale) && ! $isDefaultLocale)>
            <span class="wb-text-sm wb-text-muted">
                @if (isset($activeLocale) && ! $isDefaultLocale)
                    {{ $adminText('recipient_locked_help') }}
                @else
                    {!! $adminText('recipient_help') !!}
                @endif
            </span>
        </div>

        <div class="wb-stack wb-gap-2">
            <label>{{ $adminText('delivery_label') }}</label>

            <label class="wb-check">
                <input type="hidden" name="send_email_notification" value="0">
                <input type="checkbox" name="send_email_notification" value="1" @checked($sendEmailNotification) @disabled(isset($activeLocale) && ! $isDefaultLocale)>
                <span>{{ $adminText('send_notification') }}</span>
            </label>

            <span class="wb-text-sm wb-text-muted">{{ $adminText('delivery_help') }}</span>
        </div>
    </div>
</div>
