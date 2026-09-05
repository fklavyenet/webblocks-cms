@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.pages.converter.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'actions' => '<a href="'.route('admin.pages.index').'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_pages')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($conversionPlan)
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('analysis_preview') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $adminText('suggested_blocks_count', ['count' => $conversionPlan->suggestionCount()]) }}</span>
                    <span class="wb-status-pill {{ $conversionPlan->fallbackCount() > 0 ? 'wb-status-pending' : 'wb-status-active' }}">{{ $adminText('fallbacks_count', ['count' => $conversionPlan->fallbackCount()]) }}</span>
                    <span class="wb-status-pill {{ $conversionPlan->warningCount() > 0 ? 'wb-status-pending' : 'wb-status-active' }}">{{ $adminText('warnings_count', ['count' => $conversionPlan->warningCount()]) }}</span>
                </div>
            </div>
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-alert wb-alert-info">
                    {{ $conversionPlan->message }}
                </div>

                <div class="wb-alert wb-alert-info">
                    {{ $adminText('signed_plan_notice') }}
                </div>

                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <tbody>
                            <tr>
                                <th>{{ $adminText('target_title') }}</th>
                                <td>{{ $conversionPlan->input->pageTitle }}</td>
                            </tr>
                            <tr>
                                <th>{{ $adminText('target_path') }}</th>
                                <td>{{ $conversionPlan->input->pagePath }}</td>
                            </tr>
                            <tr>
                                <th>{{ $adminText('page_layout') }}</th>
                                <td>{{ $conversionPlan->input->pageLayout }}</td>
                            </tr>
                            <tr>
                                <th>{{ $adminText('conversion_profile') }}</th>
                                <td>{{ $conversionPlan->profileLabel() }}</td>
                            </tr>
                            <tr>
                                <th>{{ $adminText('source') }}</th>
                                <td>{{ $conversionPlan->input->sourceName }} ({{ number_format($conversionPlan->sourceBytes) }} bytes)</td>
                            </tr>
                            <tr>
                                <th>{{ $adminText('extracted_content_root') }}</th>
                                <td>{{ $conversionPlan->contentRootSummary }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($conversionPlan->suggestions === [])
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_fragments_title') }}</div>
                        <div class="wb-empty-text">{{ $adminText('no_fragments_help') }}</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('order') }}</th>
                                    <th>{{ $adminText('suggested_block') }}</th>
                                    <th>{{ $adminText('preview') }}</th>
                                    <th>{{ $adminText('confidence') }}</th>
                                    <th>{{ $adminText('source_fragment') }}</th>
                                    <th>{{ $adminText('warnings') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($conversionPlan->suggestions as $suggestion)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>
                                            <div class="wb-stack wb-gap-1">
                                                <strong>{{ $suggestion->label }}</strong>
                                                <span class="wb-text-sm wb-text-muted">{{ $suggestion->blockSlug }}</span>
                                            </div>
                                        </td>
                                        <td>{{ $suggestion->previewText ?: '-' }}</td>
                                        <td>
                                            <span class="wb-status-pill {{ $suggestion->confidence >= 85 ? 'wb-status-active' : ($suggestion->confidence >= 65 ? 'wb-status-pending' : 'wb-status-info') }}">{{ $suggestion->confidence }}%</span>
                                        </td>
                                        <td><code>{{ $suggestion->sourceSummary }}</code></td>
                                        <td>
                                            @if ($suggestion->warnings === [])
                                                <span class="wb-text-muted">-</span>
                                            @else
                                                <div class="wb-stack wb-gap-1">
                                                    @foreach ($suggestion->warnings as $warning)
                                                        <span class="wb-status-pill wb-status-pending">{{ $warning }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="wb-stack wb-gap-3">
                    <h3>{{ $adminText('create_draft_page') }}</h3>
                    <form method="POST" action="{{ route('admin.pages.converter.create-draft') }}" class="wb-stack wb-gap-0">
                        @csrf
                        <input type="hidden" name="plan_payload" value="{{ $planPayload }}">
                        <input type="hidden" name="plan_signature" value="{{ $planSignature }}">

                        <div class="wb-stack wb-gap-3">
                            <div class="wb-table-wrap">
                                <table class="wb-table">
                                    <tbody>
                                        <tr>
                                            <th>{{ $adminText('signed_plan_blocks') }}</th>
                                            <td>{{ $conversionPlan->suggestionCount() }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ $adminText('signed_plan_fallbacks') }}</th>
                                            <td>{{ $conversionPlan->fallbackCount() }}</td>
                                        </tr>
                                        <tr>
                                            <th>{{ $adminText('signed_plan_warnings') }}</th>
                                            <td>{{ $conversionPlan->warningCount() }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="wb-alert wb-alert-info">
                                {{ $adminText('draft_notice') }}
                            </div>
                            @error('plan_payload')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                            <span class="wb-text-sm wb-text-muted">{{ $adminText('create_draft_help') }}</span>
                            <button type="submit" class="wb-btn wb-btn-primary">{{ $adminText('create_draft_page') }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>{{ $adminText('source_and_target') }}</strong>
        </div>

        <form method="POST" action="{{ route('admin.pages.converter.analyze') }}" enctype="multipart/form-data" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-alert wb-alert-info">
                    {{ $adminText('analysis_only_notice') }}
                </div>

                @if ($sites->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_sites_title') }}</div>
                        <div class="wb-empty-text">{{ $adminText('no_sites_help') }}</div>
                    </div>
                @else
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_site_id">{{ $adminText('target_site') }}</label>
                            <select class="wb-select" id="page_converter_site_id" name="site_id" required>
                                @foreach ($sites as $site)
                                    <option value="{{ $site->id }}" @selected((int) old('site_id', $selectedSite?->id) === $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                            @error('site_id')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_locale_id">{{ $adminText('target_locale') }}</label>
                            <select class="wb-select" id="page_converter_locale_id" name="locale_id" required>
                                @foreach ($locales as $locale)
                                    <option value="{{ $locale->id }}" @selected((int) old('locale_id', $locales->firstWhere('is_default', true)?->id ?? $locales->first()?->id) === $locale->id)>{{ $locale->name }} ({{ $locale->code }})</option>
                                @endforeach
                            </select>
                            @error('locale_id')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_page_layout">{{ $adminText('page_layout') }}</label>
                            <select class="wb-select" id="page_converter_page_layout" name="page_layout" required>
                                @foreach ($pageLayoutOptions as $option)
                                    <option value="{{ $option['value'] }}" @selected(old('page_layout', 'default') === $option['value'])>{{ $option['label'] }}</option>
                                @endforeach
                            </select>
                            @error('page_layout')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_conversion_profile">{{ $adminText('conversion_profile') }}</label>
                            <select class="wb-select" id="page_converter_conversion_profile" name="conversion_profile" required>
                                @foreach ($profiles as $value => $label)
                                    <option value="{{ $value }}" @selected(old('conversion_profile', 'conservative') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('conversion_profile')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_page_title">{{ $adminText('page_title') }}</label>
                            <input class="wb-input" id="page_converter_page_title" type="text" name="page_title" value="{{ old('page_title') }}" required>
                            @error('page_title')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_page_path">{{ $adminText('page_path_or_slug') }}</label>
                            <input class="wb-input" id="page_converter_page_path" type="text" name="page_path" value="{{ old('page_path') }}" placeholder="docs/imported-page" required>
                            @error('page_path')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="page_converter_source_html">{{ $adminText('source_html') }}</label>
                        <textarea class="wb-textarea" id="page_converter_source_html" name="source_html" rows="14" placeholder="<main>...</main>">{{ old('source_html') }}</textarea>
                        @error('source_html')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="page_converter_source_file">{{ $adminText('optional_html_file') }}</label>
                        <input class="wb-file" id="page_converter_source_file" type="file" name="source_file" accept=".html,.htm,text/html">
                        @error('source_file')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.pages.index')" :submit-label="$adminText('analyze_html')" />
            </div>
        </form>
    </div>
@endsection
