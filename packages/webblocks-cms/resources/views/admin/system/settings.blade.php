@extends('webblocks-cms::layouts.admin', ['title' => 'System Settings', 'heading' => 'System Settings'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'System Settings',
        'description' => 'Manage compact system-level settings for project identity, locale, timezone, privacy, and runtime information. Public site branding and SEO defaults live on each Site.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="general">

                <div class="wb-card-header"><strong>General</strong></div>

                <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                    <div class="wb-stack-2 wb-field">
                        <label for="settings_default_locale">Default locale</label>
                        <select id="settings_default_locale" name="default_locale" class="wb-select" required>
                            @foreach ($localeOptions as $code => $label)
                                <option value="{{ $code }}" @selected($settings['default_locale'] === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_timezone">Timezone</label>
                        <select id="settings_timezone" name="timezone" class="wb-select" required>
                            @foreach ($timezoneOptions as $timezone => $label)
                                <option value="{{ $timezone }}" @selected($settings['timezone'] === $timezone)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_admin_listing_per_page">Admin listing rows per page</label>
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
                        <div class="wb-text-sm wb-text-muted">Controls the default number of rows shown on paginated admin listing screens.</div>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" submit-label="Save Changes" />
                </div>
            </form>
        </div>

        <div class="wb-card">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="project">

                <div class="wb-card-header"><strong>Project Identity</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">
                        Project identity is shown only in the admin interface so users can distinguish this CMS project from other WebBlocks CMS installs.
                    </div>

                    <div class="wb-grid wb-grid-2 wb-gap-4">
                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_name">Project Name</label>
                            <input id="settings_project_name" name="project_name" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_name'] }}">
                            <div class="wb-text-sm wb-text-muted">Shown in the admin topbar.</div>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_tagline">Project Tagline</label>
                            <input id="settings_project_tagline" name="project_tagline" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_tagline'] }}">
                            <div class="wb-text-sm wb-text-muted">Shown under the project name in the admin topbar when provided.</div>
                        </div>
                    </div>

                    <div class="wb-text-sm wb-text-muted">
                        These fields do not change the WebBlocks CMS product brand, do not change public site metadata, and do not replace Site Branding or Page SEO fields.
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" submit-label="Save Changes" />
                </div>
            </form>
        </div>

        <div class="wb-card">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="mail">

                <div class="wb-card-header"><strong>Mail</strong></div>

                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-text-sm wb-text-muted">
                        CMS-owned password reset and system notification mail can use Laravel environment mail config or database-backed CMS custom mail settings. These settings do not write to .env.
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="settings_cms_mail_mode">Mail mode</label>
                        <select id="settings_cms_mail_mode" name="cms_mail_mode" class="wb-select" required>
                            <option value="env" @selected($settings['cms_mail_mode'] === 'env')>Use environment mail config</option>
                            <option value="custom" @selected($settings['cms_mail_mode'] === 'custom')>Use CMS custom mail settings</option>
                        </select>
                    </div>

                    @if ($settings['cms_mail_mode'] === 'custom')
                        <div class="wb-grid wb-grid-2 wb-gap-4">
                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_mailer">Mailer</label>
                                <select id="settings_cms_mail_mailer" name="cms_mail_mailer" class="wb-select">
                                    @foreach ($cmsMailMailerOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($settings['cms_mail_mailer'] === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_host">Host</label>
                                <input id="settings_cms_mail_host" name="cms_mail_host" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_host'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_port">Port</label>
                                <input id="settings_cms_mail_port" name="cms_mail_port" type="number" class="wb-input" min="1" max="65535" step="1" value="{{ $settings['cms_mail_port'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_encryption">Encryption</label>
                                <select id="settings_cms_mail_encryption" name="cms_mail_encryption" class="wb-select">
                                    <option value="" @selected($settings['cms_mail_encryption'] === null || $settings['cms_mail_encryption'] === '')>None</option>
                                    <option value="tls" @selected($settings['cms_mail_encryption'] === 'tls')>tls</option>
                                    <option value="ssl" @selected($settings['cms_mail_encryption'] === 'ssl')>ssl</option>
                                </select>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_timeout">Timeout</label>
                                <input id="settings_cms_mail_timeout" name="cms_mail_timeout" type="number" class="wb-input" min="1" max="300" step="1" value="{{ $settings['cms_mail_timeout'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_username">Username</label>
                                <input id="settings_cms_mail_username" name="cms_mail_username" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_username'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_password">Password / secret</label>
                                <input id="settings_cms_mail_password" name="cms_mail_password" type="password" class="wb-input" autocomplete="new-password" value="">
                                <div class="wb-text-sm wb-text-muted">{{ $settings['cms_mail_password_configured'] ? 'A secret is stored. Leave blank to keep it.' : 'No secret is stored.' }}</div>
                                <input type="hidden" name="cms_mail_clear_password" value="0">
                                <label class="wb-cluster wb-cluster-2" for="settings_cms_mail_clear_password">
                                    <input id="settings_cms_mail_clear_password" name="cms_mail_clear_password" type="checkbox" value="1">
                                    <span>Clear stored secret</span>
                                </label>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_from_address">From address</label>
                                <input id="settings_cms_mail_from_address" name="cms_mail_from_address" type="email" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_from_address'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_from_name">From name</label>
                                <input id="settings_cms_mail_from_name" name="cms_mail_from_name" type="text" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_from_name'] }}">
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="settings_cms_mail_reply_to_address">Reply-to address</label>
                                <input id="settings_cms_mail_reply_to_address" name="cms_mail_reply_to_address" type="email" class="wb-input" maxlength="255" value="{{ $settings['cms_mail_reply_to_address'] }}">
                            </div>
                        </div>
                    @else
                        <div class="wb-settings-row">
                            <div class="wb-settings-row-label">
                                <strong>Environment mail configuration</strong>
                                <span>Using Laravel environment mail configuration. These settings do not write to .env.</span>
                            </div>
                            <div class="wb-settings-row-control">
                                <span>Custom SMTP fields are hidden while environment mail mode is active.</span>
                            </div>
                        </div>
                    @endif

                    @php
                        $mailDiagnosticItems = [
                            ['label' => 'Mode', 'value' => $mailDiagnostics['active_mode']],
                            ['label' => 'Mailer', 'value' => $mailDiagnostics['mailer']],
                            ['label' => 'Host', 'value' => $mailDiagnostics['host'] ?: 'Not configured'],
                            ['label' => 'Port', 'value' => $mailDiagnostics['port'] ?: 'Not configured'],
                            ['label' => 'Encryption', 'value' => $mailDiagnostics['encryption'] ?: 'None'],
                            ['label' => 'Username configured', 'value' => $mailDiagnostics['username_configured'] ? 'yes' : 'no'],
                            ['label' => 'Password configured', 'value' => $mailDiagnostics['password_configured'] ? 'yes' : 'no'],
                            ['label' => 'From address', 'value' => $mailDiagnostics['from_address'] ?: 'Not configured', 'mailto' => filled($mailDiagnostics['from_address'])],
                            ['label' => 'From name', 'value' => $mailDiagnostics['from_name'] ?: 'Not configured'],
                            ['label' => 'Config cached', 'value' => $mailDiagnostics['config_cached'] ? 'yes' : 'no'],
                            ['label' => 'Environment', 'value' => $mailDiagnostics['environment']],
                            ['label' => 'Status', 'value' => $mailDiagnostics['ready'] ? 'Ready' : 'Incomplete custom settings'],
                        ];
                    @endphp

                    <section class="wb-stack wb-gap-3" aria-labelledby="settings-mail-diagnostics-heading" data-wb-mail-diagnostics>
                        <div>
                            <strong id="settings-mail-diagnostics-heading">Diagnostics</strong>
                        </div>

                        <div class="wb-table-wrap" data-wb-mail-diagnostics-table>
                            <table class="wb-table wb-table-striped">
                                <thead>
                                    <tr>
                                        <th scope="col">Setting</th>
                                        <th scope="col">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($mailDiagnosticItems as $item)
                                        <tr data-wb-mail-diagnostic-item>
                                            <th scope="row" class="wb-text-muted" data-wb-mail-diagnostic-label>{{ $item['label'] }}</th>
                                            <td data-wb-mail-diagnostic-value style="overflow-wrap: anywhere;">
                                                @if (($item['mailto'] ?? false) && $item['value'] !== 'Not configured')
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

                        <div class="wb-text-sm wb-text-muted">Secret values are never displayed. They are reported only as configured or not configured.</div>
                        <div class="wb-text-sm wb-text-muted">Test email sending is planned as a follow-up action for this diagnostics panel.</div>
                    </section>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" submit-label="Save Changes" />
                </div>
            </form>
        </div>

        <div class="wb-card">
            <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
                @csrf
                @method('PUT')
                <input type="hidden" name="section" value="privacy">

                <div class="wb-card-header"><strong>Privacy</strong></div>

                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-text-sm wb-text-muted">
                        The public cookie settings panel lets visitors accept or decline optional analytics tracking. Necessary Laravel, admin, CSRF, and security cookies remain separate. Visitors who decline still contribute privacy-safe anonymous page view counts.
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
                            <span>Show the public privacy settings banner when visitor reports are enabled.</span>
                        </label>
                    </div>
                </div>

                <div class="wb-card-footer">
                    <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.system.settings.edit')" submit-label="Save Changes" />
                </div>
            </form>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Runtime Information</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>Application version</strong>
                        <span>Current product version from the codebase source of truth.</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $installedVersionDisplay }}</span>
                    </div>
                </div>

                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>Environment</strong>
                        <span>Current Laravel runtime environment.</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $environment }}</span>
                    </div>
                </div>

                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>Config cached</strong>
                        <span>Current Laravel configuration cache state.</span>
                    </div>
                    <div class="wb-settings-row-control">
                        <span>{{ $mailDiagnostics['config_cached'] ? 'yes' : 'no' }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
