@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key) => $adminTranslator->get('admin.blocks.contact_form.'.$key, $adminLocale);
    $submitLabel = old('submit_label', $block->submit_label ?? 'Send message');
    $successMessage = old('success_message', $block->success_message ?? config('contact.success_message'));
    $recipientEmail = old('recipient_email', $block->setting('recipient_email'));
    $sendEmailNotification = (bool) old('send_email_notification', $block->setting('send_email_notification', true));
    $storeSubmissions = (bool) old('store_submissions', $block->setting('store_submissions', true));
    $consentRequired = (bool) old('consent_required', $block->setting('consent_required', false));
    $contactLocale = $activeLocale?->code ?? app(\WebBlocks\Cms\Support\Locales\LocaleResolver::class)->default()->code;
    $defaultNotice = $adminTranslator->get('blocks.contact_form.default_consent_notice', $contactLocale);
    $savedNotice = $block->getAttribute('translation_state') === 'fallback' ? null : $block->consent_label;
    $consentLabel = old('consent_label', filled($savedNotice) ? $savedNotice : ($consentRequired ? $defaultNotice : ''));
    $privacyNoticeUrl = old('privacy_notice_url', $block->setting('privacy_notice_url', ''));
    $canEditSharedFields = ! isset($activeLocale) || $isDefaultLocale;
    $visibilitySettings = \WebBlocks\Cms\Support\Contact\ContactFormFields::VISIBILITY;
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

    <div class="wb-stack wb-gap-1">
        <label for="privacy_notice_url">{{ $adminText('privacy_notice_url') }}</label>
        <input id="privacy_notice_url" name="privacy_notice_url" class="wb-input" type="text" maxlength="2048" value="{{ $privacyNoticeUrl }}" aria-describedby="privacy-notice-url-help" @disabled(isset($activeLocale) && ! $isDefaultLocale)>
        <span id="privacy-notice-url-help" class="wb-text-sm wb-text-muted">{{ $adminText('privacy_notice_help') }}</span>
        @error('privacy_notice_url')
            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
        @enderror
    </div>

    {{--
        The consent checkbox is the auditable half of storing submissions: the
        toggle is product-owned and shared across locales, while the wording the
        visitor agreed to is translated, because that wording is the notice.
    --}}
    <div class="wb-stack wb-gap-2" data-wb-contact-consent>
        <label>{{ $adminText('consent_section_label') }}</label>

        <label class="wb-check">
            <input type="hidden" name="consent_required" value="0">
            <input type="checkbox" name="consent_required" value="1" @checked($consentRequired) @disabled(! $canEditSharedFields) data-wb-contact-consent-toggle>
            <span>{{ $adminText('consent_required') }}</span>
        </label>

        <div class="wb-stack wb-gap-1">
            <div class="wb-cluster wb-cluster-between wb-cluster-2">
                <label for="consent_label">{{ $adminText('consent_label') }}</label>
                <button type="button" class="wb-btn wb-btn-outline wb-btn-sm" data-wb-contact-consent-reset aria-controls="consent_label">{{ $adminText('consent_reset') }}</button>
            </div>
            <textarea id="consent_label" name="consent_label" class="wb-textarea" rows="3" maxlength="1000" aria-describedby="contact-consent-help" data-wb-contact-consent-default="{{ $defaultNotice }}">{{ $consentLabel }}</textarea>
            <span id="contact-consent-help" class="wb-text-sm wb-text-muted">{{ $adminText('consent_help') }}</span>
            @error('consent_label')
                <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
            @enderror
        </div>
    </div>

    <div class="wb-card" role="group" aria-labelledby="contact-fields-heading">
        <div class="wb-card-header"><strong id="contact-fields-heading">{{ $adminText('visitor_fields') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2">
            <p class="wb-text-sm wb-text-muted">{{ $adminText($canEditSharedFields ? 'visitor_fields_help' : 'visitor_fields_locked_help') }}</p>
            <div class="wb-table-wrap">
                <table class="wb-table">
                    <thead><tr>
                        <th scope="col">{{ $adminText('visitor_field') }}</th>
                        <th scope="col">{{ $adminText('visitor_visibility') }}</th>
                        <th scope="col">{{ $adminText('visitor_label') }}</th>
                    </tr></thead>
                    <tbody>
                        @foreach (['name', 'email', 'subject', 'message'] as $field)
                            @php
                                $labelKey = $field.'_label';
                                $fieldName = $adminTranslator->get('blocks.contact_form.'.$field, $adminLocale);
                                $defaultLabel = $adminTranslator->get('blocks.contact_form.'.$field, $contactLocale);
                                $visibilityKey = isset($visibilitySettings[$field]) ? 'contact_'.$visibilitySettings[$field] : null;
                            @endphp
                            <tr>
                                <th scope="row">{{ $fieldName }}</th>
                                <td>
                                    @if ($visibilityKey)
                                        @if ($canEditSharedFields)
                                            <input type="hidden" name="{{ $visibilityKey }}" value="0">
                                        @endif
                                        <label class="wb-check">
                                            <input type="checkbox" name="{{ $visibilityKey }}" value="1" @checked(old($visibilityKey, $block->setting($visibilitySettings[$field], true))) @disabled(! $canEditSharedFields) aria-label="{{ $adminText('visitor_show') }}: {{ $fieldName }}">
                                            <span>{{ $adminText('visitor_show') }}</span>
                                        </label>
                                    @else
                                        {{ $adminText('visitor_always_required') }}
                                    @endif
                                </td>
                                <td>
                                    <input type="text" name="{{ $labelKey }}" class="wb-input" maxlength="255" value="{{ old($labelKey, $block->getAttribute($labelKey)) }}" placeholder="{{ $defaultLabel }}" aria-label="{{ $adminText('visitor_label') }}: {{ $fieldName }}">
                                    @error($labelKey)
                                        <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
