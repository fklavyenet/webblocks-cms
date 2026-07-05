@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Keep locale setup small and safe. Default locale remains enabled automatically, and delete is reserved for disabled locales that are fully unused.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-0">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <div class="wb-card-body">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-3">
                            @if (! $locale->exists)
                                @php
                                    $localeOptionGroups = $localeOptionGroups ?? ['common' => [], 'all' => []];
                                    $selectedLocaleMode = old('locale_mode', 'standard');
                                    $selectedLocaleOption = old('locale_option', old('code', ''));
                                    $customFieldsHidden = $selectedLocaleMode !== 'custom';
                                @endphp

                                <div class="wb-stack-2 wb-field" data-wb-locale-picker>
                                    <label for="locale_option_filter">Locale</label>
                                    <input id="locale_option_filter" class="wb-input" type="search" placeholder="Search by language, region, or code" autocomplete="off" data-wb-locale-filter>
                                    <select id="locale_option" name="locale_option" class="wb-select" size="12" data-wb-locale-options>
                                        <option value="">Select a standard locale</option>
                                        @if (! empty($localeOptionGroups['common']))
                                            <optgroup label="Common locales">
                                                @foreach ($localeOptionGroups['common'] as $option)
                                                    <option value="{{ $option['code'] }}" data-search="{{ $option['search'] }}" @selected($selectedLocaleOption === $option['code']) @disabled($option['installed'])>
                                                        {{ $option['label'] }}{{ $option['installed'] ? ' - already installed' : '' }}
                                                    </option>
                                                @endforeach
                                            </optgroup>
                                        @endif
                                        <optgroup label="All standard locales">
                                            @foreach ($localeOptionGroups['all'] as $option)
                                                <option value="{{ $option['code'] }}" data-search="{{ $option['search'] }}" @selected($selectedLocaleOption === $option['code']) @disabled($option['installed'])>
                                                    {{ $option['label'] }}{{ $option['installed'] ? ' - already installed' : '' }}
                                                </option>
                                            @endforeach
                                        </optgroup>
                                    </select>
                                    <input type="hidden" name="locale_mode" value="{{ $selectedLocaleMode }}" data-wb-locale-mode>
                                    @error('locale_option')
                                        <div class="wb-alert wb-alert-danger">{{ $message }}</div>
                                    @enderror
                                    <div class="wb-text-sm wb-text-muted">Search the standard ICU locale list and select the exact language or language-region tag to install.</div>
                                </div>

                                <details class="wb-card wb-card-muted" data-wb-locale-custom @if (! $customFieldsHidden) open @endif>
                                    <summary class="wb-card-header">Use custom locale details</summary>
                                    <div class="wb-card-body wb-stack wb-gap-3">
                                        <div class="wb-stack-2 wb-field">
                                            <label for="locale_code">Code</label>
                                            <input id="locale_code" name="code" class="wb-input" type="text" value="{{ old('code', $locale->code) }}" data-wb-locale-custom-input>
                                        </div>

                                        <div class="wb-stack-2 wb-field">
                                            <label for="locale_name">Name</label>
                                            <input id="locale_name" name="name" class="wb-input" type="text" value="{{ old('name', $locale->name) }}" data-wb-locale-custom-input>
                                        </div>

                                        <div class="wb-text-sm wb-text-muted">Use this only for valid BCP 47 style tags that are not present in the standard picker.</div>
                                    </div>
                                </details>
                            @else
                                <div class="wb-stack-2 wb-field">
                                    <label for="locale_code">Code</label>
                                    <input id="locale_code" name="code" class="wb-input" type="text" value="{{ old('code', $locale->code) }}" required>
                                </div>

                                <div class="wb-stack-2 wb-field">
                                    <label for="locale_name">Name</label>
                                    <input id="locale_name" name="name" class="wb-input" type="text" value="{{ old('name', $locale->name) }}" required>
                                </div>
                            @endif
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <label class="wb-nowrap">
                                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $locale->is_default))>
                                    <span>Default</span>
                                </label>

                                @if ($locale->is_default)
                                    <div class="wb-text-sm wb-text-muted">The default locale remains enabled automatically.</div>
                                @elseif ($locale->exists)
                                    <div class="wb-text-sm wb-text-muted">Use the locale index actions to enable, disable, or delete this locale safely.</div>
                                @else
                                    <div class="wb-text-sm wb-text-muted">New locales start enabled. Disable them later from the locale index if they should stop participating in routing and editing.</div>
                                @endif

                                <div class="wb-text-sm wb-text-muted">
                                    Current state: <strong>{{ $locale->exists ? ($locale->is_enabled ? 'Enabled' : 'Disabled') : 'Enabled on create' }}</strong>
                                </div>

                                @if (isset($report) && $locale->exists)
                                    <div class="wb-text-sm wb-text-muted">
                                        Usage: {{ $report->count('site_assignments') }} site assignments, {{ $report->count('page_translations') }} page translations, {{ $report->count('block_translation_rows') }} block translation rows.
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.locales.index')" />
            </div>
        </form>
    </div>
@endsection

@if (! $locale->exists)
    @push('admin-scripts')
        @php($localePickerJsPath = public_path('cms/js/admin/locale-picker.js'))
        @if (is_file($localePickerJsPath))
            <script src="{{ asset('cms/js/admin/locale-picker.js') }}?v={{ filemtime($localePickerJsPath) }}" defer></script>
        @endif
    @endpush
@endif
