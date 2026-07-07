@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@php
    $localesFormText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.locales_form.'.$key, $replace);
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $localesFormText('description'),
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
                            @php
                                $localeOptions = $localeOptions ?? [];
                                $selectedLocaleOption = old('locale_option', $locale->exists ? $locale->code : '');
                                $selectedStandardOption = collect($localeOptions)->firstWhere('code', $selectedLocaleOption);
                                $selectedLocaleMode = old('locale_mode', $selectedStandardOption ? 'standard' : ($locale->exists ? 'custom' : 'standard'));
                                $customFieldsHidden = $selectedLocaleMode !== 'custom';
                            @endphp

                            <div class="wb-stack-2 wb-field" data-wb-locale-picker>
                                <label for="locale_option">{{ $localesFormText('locale') }}</label>
                                <select id="locale_option" name="locale_option" class="wb-select" data-wb-locale-options>
                                    <option value="">{{ $localesFormText('select_standard_locale') }}</option>
                                    @foreach ($localeOptions as $option)
                                        <option value="{{ $option['code'] }}" data-search="{{ $option['search'] }}" @selected($selectedLocaleOption === $option['code']) @disabled($option['installed'] && $option['code'] !== $locale->code)>
                                            {{ $option['label'] }}{{ $option['installed'] && $option['code'] !== $locale->code ? ' - '.$localesFormText('already_installed') : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="hidden" name="locale_mode" value="{{ $selectedLocaleMode }}" data-wb-locale-mode>
                                @error('locale_option')
                                    <div class="wb-alert wb-alert-danger">{{ $message }}</div>
                                @enderror
                                <div class="wb-text-sm wb-text-muted">{{ $localesFormText('locale_help') }}</div>
                            </div>

                            <details class="wb-card wb-card-muted" data-wb-locale-custom @if (! $customFieldsHidden) open @endif>
                                <summary class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                                    <span>{{ $localesFormText('use_custom_details') }}</span>
                                    <span class="wb-icon wb-icon-chevron-down" aria-hidden="true"></span>
                                </summary>
                                <div class="wb-card-body wb-stack wb-gap-3">
                                    <div class="wb-stack-2 wb-field">
                                        <label for="locale_code">{{ $localesFormText('code') }}</label>
                                        <input id="locale_code" name="code" class="wb-input" type="text" value="{{ old('code', $locale->code) }}" data-wb-locale-custom-input>
                                    </div>

                                    <div class="wb-stack-2 wb-field">
                                        <label for="locale_name">{{ $localesFormText('name') }}</label>
                                        <input id="locale_name" name="name" class="wb-input" type="text" value="{{ old('name', $locale->name) }}" data-wb-locale-custom-input>
                                    </div>

                                    <div class="wb-text-sm wb-text-muted">{{ $localesFormText('custom_help') }}</div>
                                </div>
                            </details>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <label class="wb-nowrap">
                                    <input type="checkbox" name="is_default" value="1" @checked(old('is_default', $locale->is_default))>
                                    <span>{{ $localesFormText('default') }}</span>
                                </label>

                                @if ($locale->is_default)
                                    <div class="wb-text-sm wb-text-muted">{{ $localesFormText('default_enabled_help') }}</div>
                                @elseif ($locale->exists)
                                    <div class="wb-text-sm wb-text-muted">{{ $localesFormText('existing_help') }}</div>
                                @else
                                    <div class="wb-text-sm wb-text-muted">{{ $localesFormText('new_help') }}</div>
                                @endif

                                <div class="wb-text-sm wb-text-muted">
                                    {{ $localesFormText('current_state') }} <strong>{{ $locale->exists ? ($locale->is_enabled ? $localesFormText('enabled') : $localesFormText('disabled')) : $localesFormText('enabled_on_create') }}</strong>
                                </div>

                                @if (isset($report) && $locale->exists)
                                    <div class="wb-text-sm wb-text-muted">
                                        {{ $localesFormText('usage_summary', ['sites' => $report->count('site_assignments'), 'pages' => $report->count('page_translations'), 'blocks' => $report->count('block_translation_rows')]) }}
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

@push('admin-scripts')
    @php($localePickerJsPath = public_path('cms/js/admin/locale-picker.js'))
    @if (is_file($localePickerJsPath))
        <script src="{{ asset('cms/js/admin/locale-picker.js') }}?v={{ filemtime($localePickerJsPath) }}" defer></script>
    @endif
@endpush
