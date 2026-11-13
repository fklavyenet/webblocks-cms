@php
    $mailUrl = route('admin.system.settings.edit', ['tab' => 'mail', 'verification_locale' => $verificationLocale]);
    $mailUpdateUrl = route('admin.system.settings.update', ['verification_locale' => $verificationLocale]);
    $activeMailMode = $mailDiagnostics['mode'];
    $selectedMailMode = old('cms_mail_pending_mode', old('cms_mail_mode', $activeMailMode));
    $selectedMailMode = array_key_exists(is_string($selectedMailMode) ? $selectedMailMode : '', $mailSummaries) ? $selectedMailMode : $activeMailMode;
    $editingCustomMail = old('section') === 'mail_custom' && $errors->any();
    $mailPlaceholder = fn (string $example) => $systemText('mail_field_example', ['example' => $example]);
@endphp

<form method="POST" action="{{ $mailUpdateUrl }}" data-wb-mail-settings data-active-mode="{{ $activeMailMode }}" @if ($selectedMailMode !== $activeMailMode) data-wb-mail-unsaved @endif>
    @csrf
    @method('PUT')
    <input type="hidden" name="section" value="mail_mode">

    <div class="wb-card-body wb-stack wb-gap-3">
        <p class="wb-text-sm wb-text-muted">{{ $systemText('mail_help') }}</p>
        <h3 class="wb-card-title" id="settings-mail-mode-title">{{ $systemText('mail_mode') }}</h3>
        <div class="wb-grid wb-grid-2 wb-gap-3" role="radiogroup" aria-labelledby="settings-mail-mode-title" aria-describedby="settings-mail-mode-pending">
            @foreach ($mailSummaries as $mode => $summary)
                <div class="wb-card wb-mail-mode-card">
                    <div class="wb-card-header wb-cluster wb-cluster-2">
                        <label class="wb-radio">
                            <input type="radio" name="cms_mail_mode" value="{{ $mode }}" @checked($selectedMailMode === $mode) required>
                            <span>{{ $systemText($mode === 'custom' ? 'custom_mail_configuration' : 'environment_mail_configuration') }}</span>
                        </label>
                        @if ($activeMailMode === $mode)
                            <span class="wb-status-pill wb-status-info">{{ $systemText('mail_mode_active') }}</span>
                        @endif
                        @if ($mode === 'custom')
                            <button type="button" class="wb-btn wb-btn-secondary wb-btn-sm" data-wb-toggle="modal" data-wb-target="#settings-custom-mail-modal" aria-haspopup="dialog">{{ $adminText('common.edit') }}</button>
                        @endif
                    </div>
                    @include('webblocks-cms::admin.system.partials.mail-summary', ['summary' => $summary])
                </div>
            @endforeach
        </div>
        <p id="settings-mail-mode-pending" class="wb-text-sm wb-text-muted" role="status" @if ($selectedMailMode === $activeMailMode) hidden @endif>{{ $systemText('mail_mode_pending') }}</p>
        @error('cms_mail_mode')
            <p class="wb-alert wb-alert-danger" role="alert">{{ $message }}</p>
        @enderror
    </div>

    <div class="wb-card-footer wb-justify-end">
        <x-webblocks-cms::admin.form-actions :cancel-url="$mailUrl" :cancel-attributes="['data-wb-mail-mode-cancel' => true]" :submit-label="$systemText('save_changes')" />
    </div>
</form>

@push('overlays')
    <dialog class="wb-modal wb-modal-lg" id="settings-custom-mail-modal" aria-labelledby="settings-custom-mail-title" @if ($editingCustomMail) data-wb-admin-autoload-overlay @endif>
        <form class="wb-modal-dialog" method="POST" action="{{ $mailUpdateUrl }}" data-wb-mail-custom @if ($editingCustomMail) data-wb-mail-unsaved @endif>
            @csrf
            @method('PUT')
            <input type="hidden" name="section" value="mail_custom">
            <input type="hidden" name="cms_mail_pending_mode" value="{{ $selectedMailMode }}">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="settings-custom-mail-title">{{ $systemText('mail_custom_edit') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('common.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <div class="wb-modal-body wb-stack wb-gap-4">
                <div class="wb-field">
                    <label for="settings_cms_mail_mailer">{{ $systemText('mailer') }}</label>
                    <select id="settings_cms_mail_mailer" name="cms_mail_mailer" class="wb-select" required>
                        @foreach ($cmsMailMailerOptions as $value => $label)
                            <option value="{{ $value }}" @selected(old('cms_mail_mailer', $customMail['mailer']) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('cms_mail_mailer')<p class="wb-field-error">{{ $message }}</p>@enderror
                </div>
                <div class="wb-grid wb-grid-2 wb-gap-3" data-wb-mail-smtp-fields @if (old('cms_mail_mailer', $customMail['mailer']) !== 'smtp') hidden @endif>
                    @foreach ([
                        'host' => ['text', 'smtp.example.test', ['maxlength' => 255]],
                        'port' => ['number', '587', ['min' => 1, 'max' => 65535]],
                        'timeout' => ['number', '30', ['min' => 1, 'max' => 300]],
                        'username' => ['text', 'smtp-user', ['maxlength' => 255]],
                    ] as $field => $definition)
                        @include('webblocks-cms::admin.system.partials.mail-input')
                    @endforeach
                    <div class="wb-field">
                        <label for="settings_cms_mail_encryption">{{ $systemText('encryption') }}</label>
                        <select id="settings_cms_mail_encryption" name="cms_mail_encryption" class="wb-select">
                            @foreach (['' => $systemText('none'), 'tls' => 'TLS', 'ssl' => 'SSL'] as $value => $label)
                                <option value="{{ $value }}" @selected((string) old('cms_mail_encryption', $customMail['encryption']) === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('cms_mail_encryption')<p class="wb-field-error">{{ $message }}</p>@enderror
                    </div>
                    <div class="wb-field">
                        <label for="settings_cms_mail_password">{{ $systemText('password_secret') }}</label>
                        <input id="settings_cms_mail_password" name="cms_mail_password" type="password" class="wb-input" maxlength="1024" autocomplete="new-password" placeholder="{{ $systemText($mailSummaries['custom']['password_configured'] ? 'mail_password_keep' : 'mail_password_enter') }}" aria-describedby="settings-cms-mail-password-help">
                        <p id="settings-cms-mail-password-help" class="wb-text-sm wb-text-muted">{{ $systemText($mailSummaries['custom']['password_configured'] ? 'secret_stored' : 'secret_not_stored') }}</p>
                        @if ($mailSummaries['custom']['password_configured'])
                            <label class="wb-cluster wb-cluster-2" for="settings_cms_mail_clear_password">
                                <input id="settings_cms_mail_clear_password" name="cms_mail_clear_password" type="checkbox" value="1" @checked(old('cms_mail_clear_password'))>
                                <span>{{ $systemText('clear_stored_secret') }}</span>
                            </label>
                        @endif
                        @error('cms_mail_password')<p class="wb-field-error">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="wb-grid wb-grid-2 wb-gap-3">
                    @foreach ([
                        'from_address' => ['email', 'cms@example.test', ['maxlength' => 255, 'required' => true]],
                        'from_name' => ['text', $systemText('mail_sender_example'), ['maxlength' => 255]],
                        'reply_to_address' => ['email', 'support@example.test', ['maxlength' => 255]],
                    ] as $field => $definition)
                        @include('webblocks-cms::admin.system.partials.mail-input')
                    @endforeach
                </div>
                @error('cms_mail_clear_password')<p class="wb-field-error">{{ $message }}</p>@enderror
                @error('cms_mail_pending_mode')<p class="wb-field-error">{{ $message }}</p>@enderror
            </div>
            <div class="wb-modal-footer">
                <x-webblocks-cms::admin.form-actions
                    :submit-label="$systemText('mail_custom_save')"
                    cancel-type="button"
                    :cancel-attributes="['data-wb-dismiss' => 'modal']"
                />
            </div>
        </form>
    </dialog>
@endpush
