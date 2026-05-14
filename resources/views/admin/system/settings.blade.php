@extends('layouts.admin', ['title' => 'System Settings', 'heading' => 'System Settings'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'System Settings',
        'description' => 'Manage compact system-level settings for project identity, locale, timezone, privacy, and runtime information. Public site branding and SEO defaults live on each Site.',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ route('admin.system.settings.update') }}" class="wb-stack wb-gap-0">
            @csrf
            @method('PUT')

            <div class="wb-card-header"><strong>Settings</strong></div>

            <div class="wb-card-body wb-grid wb-grid-2 wb-gap-4">
                <div class="wb-stack wb-gap-4">
                    <section class="wb-stack wb-gap-3" aria-labelledby="settings-general-heading">
                        <div>
                            <strong id="settings-general-heading">General</strong>
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
                    </section>

                    <section class="wb-stack wb-gap-3" aria-labelledby="settings-cookie-heading">
                        <div>
                            <strong id="settings-cookie-heading">Cookie settings</strong>
                        </div>

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
                    </section>
                </div>

                <div class="wb-stack wb-gap-4">
                    <section class="wb-stack wb-gap-3" aria-labelledby="settings-project-heading">
                        <div>
                            <strong id="settings-project-heading">Project</strong>
                        </div>

                        <div class="wb-text-sm wb-text-muted">
                            Project identity is shown only in the admin interface so users can distinguish this CMS project from other WebBlocks CMS installs.
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_name">Project Name</label>
                            <input id="settings_project_name" name="project_name" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_name'] }}">
                            <div class="wb-text-sm wb-text-muted">Shown in the admin topbar and admin browser titles.</div>
                        </div>

                        <div class="wb-stack-2 wb-field">
                            <label for="settings_project_tagline">Project Tagline</label>
                            <input id="settings_project_tagline" name="project_tagline" type="text" class="wb-input" maxlength="255" value="{{ $settings['project_tagline'] }}">
                            <div class="wb-text-sm wb-text-muted">Shown under the project name in the admin topbar when provided.</div>
                        </div>

                        <div class="wb-text-sm wb-text-muted">
                            These fields do not change the WebBlocks CMS product brand, do not change public site metadata, and do not replace Site Branding or Page SEO fields.
                        </div>
                    </section>

                    <section class="wb-stack wb-gap-3" aria-labelledby="settings-information-heading">
                        <div>
                            <strong id="settings-information-heading">Information</strong>
                        </div>

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
                    </section>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-admin.form-actions :cancel-url="route('admin.system.settings.edit')" submit-label="Save Changes" />
            </div>
        </form>
    </div>
@endsection
