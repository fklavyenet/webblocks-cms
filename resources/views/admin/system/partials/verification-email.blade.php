@php
    $verificationText = static fn (string $key) => trans('webblocks-cms::two-factor.template_'.$key, [], $adminLocale);
    $verificationOldInput = old('locale') === $verificationLocale;
@endphp
<section class="wb-card wb-card-muted wb-mt-4" aria-labelledby="verification-email-title">
    <div class="wb-card-header"><h2 class="wb-card-title" id="verification-email-title">{{ $verificationText('title') }}</h2></div>
    <div class="wb-card-body wb-stack-4">
        <p class="wb-text-sm wb-text-muted">{{ $verificationText('description') }}</p>
        <form method="GET" action="{{ route('admin.system.settings.edit') }}" class="wb-cluster wb-gap-3">
            <input type="hidden" name="tab" value="mail">
            <div class="wb-field">
                <label for="verification-email-locale">{{ $verificationText('language') }}</label>
                <select id="verification-email-locale" class="wb-select" name="verification_locale">
                    @foreach ($adminLocaleOptions as $localeCode => $localeLabel)
                        <option value="{{ $localeCode }}" @selected($verificationLocale === $localeCode)>{{ $localeLabel }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="wb-btn wb-btn-secondary">{{ $verificationText('load') }}</button>
        </form>
    </div>
    <form method="POST" action="{{ route('admin.system.settings.mail.verification') }}">
        @csrf
        <input type="hidden" name="locale" value="{{ $verificationLocale }}">
        <div class="wb-card-body wb-stack-4">
            <p class="wb-text-sm wb-text-muted">{{ $verificationText('tokens') }}</p>
            <p class="wb-text-sm wb-text-muted">{{ $verificationText('subject_notice') }}</p>
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
                    @error($verificationField)<p class="wb-text-sm wb-text-danger">{{ $message }}</p>@enderror
                </div>
            @endforeach
            <p class="wb-text-sm wb-text-muted">{{ $verificationText('preview_help') }}</p>
        </div>
        <div class="wb-card-footer wb-action-group">
            <button type="submit" class="wb-btn wb-btn-primary" name="intent" value="save">{{ $verificationText('save') }}</button>
            <button type="submit" class="wb-btn wb-btn-secondary" name="intent" value="preview" formtarget="_blank">{{ $verificationText('preview') }}</button>
            <button type="button" class="wb-btn wb-btn-danger" data-wb-toggle="modal" data-wb-target="#verification-email-reset" aria-haspopup="dialog">{{ $verificationText('reset') }}</button>
        </div>
    </form>
</section>

@push('overlays')
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
                <div class="wb-modal-body"><p>{{ $verificationText('reset_help') }}</p></div>
                <div class="wb-modal-footer wb-action-group">
                    <button type="submit" class="wb-btn wb-btn-danger">{{ $verificationText('reset') }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endpush
