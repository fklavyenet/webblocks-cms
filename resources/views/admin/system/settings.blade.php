@extends('layouts.admin', ['title' => 'Settings', 'heading' => 'Settings'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Settings',
        'description' => 'Manage compact system-level application settings. Content and editorial fields stay in pages, blocks, navigation, and sites.',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>General</strong></div>

            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('PUT')

                    <div class="wb-stack wb-gap-3">
                        <div class="wb-stack-2 wb-field">
                            <label for="settings_app_name">Application name</label>
                            <input id="settings_app_name" name="app_name" class="wb-input" type="text" value="{{ $settings['app_name'] }}" required>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="settings_app_slogan">Application slogan</label>
                            <input id="settings_app_slogan" name="app_slogan" class="wb-input" type="text" value="{{ $settings['app_slogan'] }}">
                        </div>

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
                    </div>

                    <x-admin.form-actions :cancel-url="route('admin.system.settings.edit')" />
                </form>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Cookie settings</strong></div>

            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('PUT')

                    <input type="hidden" name="app_name" value="{{ $settings['app_name'] }}">
                    <input type="hidden" name="app_slogan" value="{{ $settings['app_slogan'] }}">
                    <input type="hidden" name="default_locale" value="{{ $settings['default_locale'] }}">
                    <input type="hidden" name="timezone" value="{{ $settings['timezone'] }}">

                    <div class="wb-stack wb-gap-3">
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

                    <x-admin.form-actions :cancel-url="route('admin.system.settings.edit')" />
                </form>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-header"><strong>Information</strong></div>

            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-settings-row">
                    <div class="wb-settings-row-label">
                        <strong>Installed version</strong>
                        <span>Current recorded application version.</span>
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
            </div>
        </div>
    </div>
@endsection
