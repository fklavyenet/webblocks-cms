@php
    $verificationText = static fn (string $key) => trans('webblocks-cms::two-factor.template_'.$key, [], $adminLocale);
    $verificationOldInput = old('locale') === $verificationLocale;
@endphp
<section class="wb-card wb-card-muted wb-mt-4" aria-labelledby="verification-email-title">
    <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
        <h2 class="wb-card-title" id="verification-email-title">{{ $verificationText('title') }}</h2>
        <div class="wb-cluster wb-cluster-2 wb-items-end">
            <form method="GET" action="{{ route('admin.system.settings.edit') }}" data-wb-verification-language>
                <input type="hidden" name="tab" value="mail">
                <div class="wb-field">
                    <label for="verification-email-locale">{{ $verificationText('language') }}</label>
                    <select id="verification-email-locale" class="wb-select" name="verification_locale">
                        @foreach ($verificationLocaleOptions as $localeCode => $localeLabel)
                            <option value="{{ $localeCode }}" @selected($verificationLocale === $localeCode)>{{ $localeLabel }}</option>
                        @endforeach
                    </select>
                </div>
                <noscript><button type="submit" class="wb-btn wb-btn-secondary">{{ $verificationText('load') }}</button></noscript>
            </form>
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-verification-preview aria-haspopup="dialog" aria-controls="verification-email-preview" hidden>{{ $verificationText('preview') }}</button>
        </div>
    </div>
    <form method="POST" action="{{ route('admin.system.settings.mail.verification') }}" data-wb-verification-editor @if ($verificationOldInput) data-wb-mail-unsaved @endif>
        @csrf
        <input type="hidden" name="locale" value="{{ $verificationLocale }}">
        <div class="wb-card-body wb-stack-4">
            <p class="wb-text-sm wb-text-muted">{{ $verificationText('description') }}</p>
            <p class="wb-text-sm wb-text-muted">{{ $verificationText('tokens') }}</p>
            @foreach (\WebBlocks\Cms\Support\Mail\VerificationEmailTemplates::FIELDS as $verificationField)
                @php
                    $verificationValue = $verificationOldInput ? old($verificationField, $verificationTemplates[$verificationField]) : $verificationTemplates[$verificationField];
                    $verificationMultiline = in_array($verificationField, ['intro', 'help'], true);
                @endphp
                <div class="wb-field">
                    <label for="verification-email-{{ $verificationField }}">{{ $verificationText($verificationField) }}</label>
                    @if ($verificationMultiline)
                        <textarea id="verification-email-{{ $verificationField }}" name="{{ $verificationField }}" class="wb-textarea" rows="3" maxlength="2000" required>{{ $verificationValue }}</textarea>
                    @else
                        <input id="verification-email-{{ $verificationField }}" name="{{ $verificationField }}" class="wb-input" type="text" maxlength="255" value="{{ $verificationValue }}" required>
                    @endif
                    @if ($verificationField === 'subject')<p class="wb-text-sm wb-text-muted">{{ $verificationText('subject_notice') }}</p>@endif
                    @error($verificationField)<p class="wb-text-sm wb-text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
        </div>
        <div class="wb-card-footer">
            <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#verification-email-reset" aria-haspopup="dialog">{{ $verificationText('reset') }}</button>
            <button type="submit" class="wb-btn wb-btn-primary" name="intent" value="save">{{ $verificationText('save') }}</button>
        </div>
    </form>
</section>

@push('overlays')
    <dialog class="wb-modal wb-modal-lg" id="verification-email-preview" aria-labelledby="verification-email-preview-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="verification-email-preview-title">{{ $verificationText('preview') }}</h2>
            </div>
            <div class="wb-modal-body wb-stack-3">
                <p class="wb-text-sm wb-text-muted">{{ $verificationText('preview_help') }}</p>
                <p role="status" data-wb-verification-preview-status>{{ $verificationText('preview_loading') }}</p>
                <p class="wb-alert wb-alert-danger" role="alert" data-wb-verification-preview-error data-error-message="{{ $verificationText('preview_error') }}" hidden></p>
                <p data-wb-verification-preview-subject hidden><strong>{{ $verificationText('subject') }}</strong> <span></span></p>
                <iframe class="wb-verification-email-preview-frame" title="{{ $verificationText('preview') }}" sandbox hidden></iframe>
            </div>
            <div class="wb-modal-footer wb-flex wb-justify-end">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.close') }}</button>
            </div>
        </div>
    </dialog>
    <dialog class="wb-modal" id="verification-email-language-confirm" aria-labelledby="verification-email-language-confirm-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="verification-email-language-confirm-title">{{ $verificationText('switch_language') }}</h2>
            </div>
            <div class="wb-modal-body"><p>{{ $verificationText('switch_language_help') }}</p></div>
            <div class="wb-modal-footer wb-action-group">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.cancel') }}</button>
                <button type="button" class="wb-btn wb-btn-danger" data-wb-verification-language-confirm>{{ $verificationText('switch_language') }}</button>
            </div>
        </div>
    </dialog>
    <div class="wb-modal" id="verification-email-reset" role="dialog" aria-modal="true" aria-labelledby="verification-email-reset-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="verification-email-reset-title">{{ $verificationText('reset') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('common.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <form method="POST" action="{{ route('admin.system.settings.mail.verification') }}">
                @csrf
                <input type="hidden" name="locale" value="{{ $verificationLocale }}">
                <input type="hidden" name="intent" value="reset">
                <div class="wb-modal-body"><p>{{ trans('webblocks-cms::two-factor.template_reset_help', ['language' => $verificationLocaleOptions[$verificationLocale]], $adminLocale) }}</p></div>
                <div class="wb-modal-footer wb-action-group">
                    <button type="submit" class="wb-btn wb-btn-danger">{{ $verificationText('reset') }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endpush
