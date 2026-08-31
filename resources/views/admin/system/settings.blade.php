@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocale, $replace);
    $systemText = static fn (string $key, array $replace = []) => $adminTranslator->admin('system_settings.'.$key, $adminLocale, $replace);
    $systemTabs = [
        'general' => $systemText('general'),
        'project' => $systemText('project_identity'),
        'mail' => $systemText('mail'),
        'privacy' => $systemText('privacy'),
        'runtime' => $systemText('runtime_information'),
    ];
    $systemTab = old('section', request()->query('tab', 'general'));

    if ($errors->has('recipient_email')) {
        $systemTab = 'mail';
    }

    if (! array_key_exists($systemTab, $systemTabs)) {
        $systemTab = 'general';
    }
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $systemText('title'), 'heading' => $systemText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $systemText('title'),
        'description' => $systemText('description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header"><strong>{{ $systemText('settings') }}</strong></div>

        <div class="wb-card-body">
            <div class="wb-tabs" data-wb-tabs>
                <div class="wb-tabs-nav" role="tablist" aria-label="{{ $systemText('sections') }}">
                    @foreach ($systemTabs as $tabKey => $tabLabel)
                        <button
                            type="button"
                            class="wb-tabs-btn {{ $systemTab === $tabKey ? 'is-active' : '' }}"
                            data-wb-tab="system-settings-{{ $tabKey }}-panel"
                            aria-selected="{{ $systemTab === $tabKey ? 'true' : 'false' }}"
                            @if ($systemTab !== $tabKey) tabindex="-1" @endif
                        >{{ $tabLabel }}</button>
                    @endforeach
                </div>

                <div class="wb-tabs-panels">
                    <div class="wb-tabs-panel {{ $systemTab === 'general' ? 'is-active' : '' }}" id="system-settings-general-panel">
        <div class="wb-card wb-card-muted">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="general">

                <div class="wb-card-header"><strong>{{ $systemText('general') }}</strong></div>

                <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                    <div class="wb-stack-2 wb-field">
                        <label for="settings_default_locale">{{ $systemText('default_locale') }}</label>
                        <select id="settings_default_locale" name="default_locale" class="wb-select" required>
                            @foreach ($localeOptions as $code => $label)
                                <option value="{{ $code }}" @selected($settings['default_locale'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_timezone">{{ $systemText('timezone') }}</label>
                        <select id="settings_timezone" name="timezone" class="wb-select" required>
                            @foreach ($timezoneOptions as $timezone => $label)
                                <option value="{{ $timezone }}" @selected($settings['timezone'] === $timezone)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_admin_listing_per_page">{{ $systemText('admin_listing_per_page') }}</label>
                        <input
                            id="settings_admin_listing_per_page"
                            name="admin_listing_per_page"
                            type="number"
                            class="wb-input"
                            min="1"
                            max="100"
                            step="1"
                            required
                            value="{{ $settings['admin_listing_per_page'] }}"
                        >
                        <div class="wb-text-sm wb-text-muted">{{ $systemText('admin_listing_per_page_help') }}</div>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_admin_locale">{{ $adminText('settings.admin_locale') }}</label>
                        <select id="settings_admin_locale" name="admin_locale" class="wb-select" required>
                            @foreach ($adminLocaleOptions as $code => $label)
                                <option value="{{ $code }}" @selected($settings['admin_locale'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('settings.admin_locale_help') }}</div>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" :submit-label="$systemText('save_changes')" />
                </div>
            </form>
        </div>

        </div>

                    <div class="wb-tabs-panel {{ $systemTab === 'project' ? 'is-active' : '' }}" id="system-settings-project-panel">
        <div class="wb-card wb-card-muted">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="project">

                <div class="wb-card-header"><strong>{{ $systemText('project_identity') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">
                        {{ $systemText('project_identity_help') }}
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-4">
                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_name">{{ $systemText('project_name') }}</label>
                            <input id="settings_project_name" name="project_name" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_name'] }}">
                            <div class="wb-text-sm wb-text-muted">{{ $systemText('project_name_help') }}</div>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_tagline">{{ $systemText('project_tagline') }}</label>
                            <input id="settings_project_tagline" name="project_tagline" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_tagline'] }}">
                            <div class="wb-text-sm wb-text-muted">{{ $systemText('project_tagline_help') }}</div>
                        </div>
                    </div>

                    <div class="wb-text-sm wb-text-muted">
                        {{ $systemText('project_identity_scope') }}
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" :submit-label="$systemText('save_changes')" />
                </div>
            </form>
        </div>

        </div>

                    <div class="wb-tabs-panel {{ $systemTab === 'mail' ? 'is-active' : '' }}" id="system-settings-mail-panel">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $systemText('mail') }}</strong></div>

            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="mail">

                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-text-sm wb-text-muted">
                        {{ $systemText('mail_help') }}
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_cms_mail_mode">{{ $systemText('mail_mode') }}</label>
                        <select id="settings_cms_mail_mode" name="cms_mail_mode" class="wb-select" required>
                            <option value="env" @selected($settings['cms_mail_mode'] === 'env')>{{ $systemText('mail_mode_env') }}</option>
                            <option value="custom" @selected($settings['cms_mail_mode'] === 'custom')>{{ $systemText('mail_mode_custom') }}</option>
                        </select>
                    </div>

                    @if ($settings['cms_mail_mode'] === 'custom')
                        <div class="wb-grid wb-grid-2 wb-gap-4">
                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_mailer">{{ $systemText('mailer') }}</label>
                                <select id="settings_cms_mail_mailer" name="cms_mail_mailer" class="wb-select">
                                    @foreach ($cmsMailMailerOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($settings['cms_mail_mailer'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_host">{{ $systemText('host') }}</label>
                                <input id="settings_cms_mail_host" name="cms_mail_host" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_host'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_port">{{ $systemText('port') }}</label>
                                <input id="settings_cms_mail_port" name="cms_mail_port" type="number" class="wb-input" min="1" max="65535" step="1" value="{{ $settings['cms_mail_port'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_encryption">{{ $systemText('encryption') }}</label>
                                <select id="settings_cms_mail_encryption" name="cms_mail_encryption" class="wb-select">
                                    <option value="" @selected($settings['cms_mail_encryption'] === null || $settings['cms_mail_encryption'] === '')>{{ $systemText('none') }}</option>
                                    <option value="tls" @selected($settings['cms_mail_encryption'] === 'tls')>tls</option>
                                    <option value="ssl" @selected($settings['cms_mail_encryption'] === 'ssl')>ssl</option>
                                </select>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_timeout">{{ $systemText('timeout') }}</label>
                                <input id="settings_cms_mail_timeout" name="cms_mail_timeout" type="number" class="wb-input" min="1" max="300" step="1" value="{{ $settings['cms_mail_timeout'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_username">{{ $systemText('username') }}</label>
                                <input id="settings_cms_mail_username" name="cms_mail_username" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_username'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_password">{{ $systemText('password_secret') }}</label>
                                <input id="settings_cms_mail_password" name="cms_mail_password" type="password" class="wb-input" autocomplete="new-password" value="">
                                <div class="wb-text-sm wb-text-muted">{{ $settings['cms_mail_password_configured'] ? $systemText('secret_stored') : $systemText('secret_not_stored') }}</div>
                                <input type="hidden" name="cms_mail_clear_password" value="0">
                                <label class="wb-cluster wb-cluster-2" for="settings_cms_mail_clear_password">
                                    <input id="settings_cms_mail_clear_password" name="cms_mail_clear_password" type="checkbox" value="1">
                                    <span>{{ $systemText('clear_stored_secret') }}</span>
                                </label>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_from_address">{{ $systemText('from_address') }}</label>
                                <input id="settings_cms_mail_from_address" name="cms_mail_from_address" type="email" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_from_address'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_from_name">{{ $systemText('from_name') }}</label>
                                <input id="settings_cms_mail_from_name" name="cms_mail_from_name" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_from_name'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_reply_to_address">{{ $systemText('reply_to_address') }}</label>
                                <input id="settings_cms_mail_reply_to_address" name="cms_mail_reply_to_address" type="email" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_reply_to_address'] }}">
                            </div>
                        </div>
                    @else
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>{{ $systemText('environment_mail_configuration') }}</strong>
                                <span>{{ $systemText('environment_mail_configuration_help') }}</span>
                            </div>
                            <div class="wb-settings-row-control">
                                <span>{{ $systemText('custom_smtp_hidden') }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="wb-card-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <div class="wb-cluster wb-cluster-2">
                        <button type="button" class="wb-btn wb-btn-secondary" data-wb-toggle="modal" data-wb-target="#settings-mail-diagnostics-modal" aria-haspopup="dialog">{{ $systemText('diagnostics') }}</button>
                        <button type="button" class="wb-btn wb-btn-primary" data-wb-toggle="modal" data-wb-target="#settings-mail-test-modal" aria-haspopup="dialog">{{ $systemText('send_test_email') }}</button>
                    </div>

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="route('admin.system.settings.edit', ['tab' => 'mail'])"
                        :submit-label="$systemText('save_changes')"
                        container-class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap"
                    />
                </div>
            </form>

            @php
                $mailDiagnosticItems = [
                    ['label' => $systemText('mode'), 'value' => $mailDiagnostics['active_mode']],
                    ['label' => $systemText('mailer'), 'value' => $mailDiagnostics['mailer']],
                    ['label' => $systemText('host'), 'value' => $mailDiagnostics['host'] ?: $systemText('not_configured')],
                    ['label' => $systemText('port'), 'value' => $mailDiagnostics['port'] ?: $systemText('not_configured')],
                    ['label' => $systemText('encryption'), 'value' => $mailDiagnostics['encryption'] ?: $systemText('none')],
                    ['label' => $systemText('username_configured'), 'value' => $mailDiagnostics['username_configured'] ? $systemText('yes') : $systemText('no')],
                    ['label' => $systemText('password_configured'), 'value' => $mailDiagnostics['password_configured'] ? $systemText('yes') : $systemText('no')],
                    ['label' => $systemText('from_address'), 'value' => $mailDiagnostics['from_address'] ?: $systemText('not_configured'), 'mailto' => filled($mailDiagnostics['from_address'])],
                    ['label' => $systemText('from_name'), 'value' => $mailDiagnostics['from_name'] ?: $systemText('not_configured')],
                    ['label' => $systemText('config_cached'), 'value' => $mailDiagnostics['config_cached'] ? $systemText('yes') : $systemText('no')],
                    ['label' => $systemText('environment'), 'value' => $mailDiagnostics['environment']],
                    ['label' => $systemText('status'), 'value' => $mailDiagnostics['status']],
                ];
            @endphp

        </div>

        </div>

                    <div class="wb-tabs-panel {{ $systemTab === 'privacy' ? 'is-active' : '' }}" id="system-settings-privacy-panel">
        <div class="wb-card wb-card-muted">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="privacy">

                <div class="wb-card-header"><strong>{{ $systemText('privacy') }}</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">
                        {{ $systemText('privacy_help') }}
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <input type="hidden" name="visitor_consent_banner_enabled" value="0">
                        <label class="wb-cluster wb-cluster-2" for="settings_visitor_consent_banner_enabled">
                            <input
                                id="settings_visitor_consent_banner_enabled"
                                name="visitor_consent_banner_enabled"
                                type="checkbox"
                                value="1"
                                @checked($settings['visitor_consent_banner_enabled'])
                            >
                            <span>{{ $systemText('visitor_consent_banner_enabled') }}</span>
                        </label>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" :submit-label="$systemText('save_changes')" />
                </div>
            </form>
        </div>

        </div>

                    <div class="wb-tabs-panel {{ $systemTab === 'runtime' ? 'is-active' : '' }}" id="system-settings-runtime-panel">
        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>{{ $systemText('runtime_information') }}</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>{{ $systemText('application_version') }}</strong>
                        <span>{{ $systemText('application_version_help') }}</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $installedVersionDisplay }}</span>
                    </div>
                </div>

                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>{{ $systemText('environment') }}</strong>
                        <span>{{ $systemText('environment_help') }}</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $environment }}</span>
                    </div>
                </div>

                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>{{ $systemText('config_cached') }}</strong>
                        <span>{{ $systemText('config_cached_help') }}</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $mailDiagnostics['config_cached'] ? $systemText('yes') : $systemText('no') }}</span>
                    </div>
                </div>
            </div>
        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('overlays')
    <div class="wb-modal wb-modal-lg" id="settings-mail-diagnostics-modal" role="dialog" aria-modal="true" aria-labelledby="settings-mail-diagnostics-title">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <h2 class="wb-modal-title" id="settings-mail-diagnostics-title">{{ $systemText('diagnostics') }}</h2>
                <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('common.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
            </div>
            <div class="wb-modal-body wb-stack wb-gap-4" data-wb-mail-diagnostics>
                <div class="wb-table-wrap" data-wb-mail-diagnostics-table>
                    <table class="wb-table wb-table-striped">
                        <thead>
                            <tr>
                                <th scope="col">{{ $systemText('setting') }}</th>
                                <th scope="col">{{ $systemText('value') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($mailDiagnosticItems as $item)
                                <tr data-wb-mail-diagnostic-item>
                                    <th scope="row" class="wb-text-muted" data-wb-mail-diagnostic-label>{{ $item['label'] }}</th>
                                    <td class="wb-admin-break-anywhere" data-wb-mail-diagnostic-value>
                                        @if (($item['mailto'] ?? false) && $item['value'] !== $systemText('not_configured'))
                                            <a href="mailto:{{ $item['value'] }}">{{ $item['value'] }}</a>
                                        @else
                                            {{ $item['value'] }}
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="wb-text-sm wb-text-muted">{{ $systemText('secret_values_hidden') }}</div>
            </div>
            <div class="wb-modal-footer">
                <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.close') }}</button>
            </div>
        </div>
    </div>

    <div class="wb-modal {{ $errors->has('recipient_email') ? 'is-open' : '' }}" id="settings-mail-test-modal" role="dialog" aria-modal="true" aria-labelledby="settings-mail-test-title">
        <div class="wb-modal-dialog">
            <form method="POST" action="{{ route('admin.system.settings.mail.test') }}">
                @csrf
                <div class="wb-modal-header">
                    <h2 class="wb-modal-title" id="settings-mail-test-title">{{ $systemText('send_test_email') }}</h2>
                    <button type="button" class="wb-modal-close" data-wb-dismiss="modal" aria-label="{{ $adminText('common.close') }}"><i class="wb-icon wb-icon-x" aria-hidden="true"></i></button>
                </div>
                <div class="wb-modal-body">
                    <div class="wb-stack-2 wb-field">
                        <label for="settings_mail_test_recipient_email">{{ $systemText('recipient_email') }}</label>
                        <input id="settings_mail_test_recipient_email" name="recipient_email" type="email" class="wb-input" maxlength="255" required value="{{ old('recipient_email') }}" @error('recipient_email') aria-invalid="true" aria-describedby="settings_mail_test_recipient_email_error" @enderror>
                        @error('recipient_email')
                            <div id="settings_mail_test_recipient_email_error" class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>
                </div>
                <div class="wb-modal-footer wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <button type="submit" class="wb-btn wb-btn-primary">{{ $systemText('send_test_email_button') }}</button>
                    <button type="button" class="wb-btn wb-btn-secondary" data-wb-dismiss="modal">{{ $adminText('common.cancel') }}</button>
                </div>
            </form>
        </div>
    </div>
@endpush
