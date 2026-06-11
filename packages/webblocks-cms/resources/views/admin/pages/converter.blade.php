@extends('webblocks-cms::layouts.admin', ['title' => 'Page Converter', 'heading' => 'Page Converter'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Page Converter',
        'description' => 'Convert pasted or uploaded static HTML into a draft CMS page made from structured blocks.',
        'actions' => '<a href="'.route('admin.pages.index').'" class="wb-btn wb-btn-secondary">Back to Pages</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    @if ($conversionPlan)
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Analysis Preview</strong>
                    <span class="wb-status-pill wb-status-info">{{ $conversionPlan->suggestionCount() }} suggested blocks</span>
                    <span class="wb-status-pill {{ $conversionPlan->fallbackCount() > 0 ? 'wb-status-pending' : 'wb-status-active' }}">{{ $conversionPlan->fallbackCount() }} fallbacks</span>
                    <span class="wb-status-pill {{ $conversionPlan->warningCount() > 0 ? 'wb-status-pending' : 'wb-status-active' }}">{{ $conversionPlan->warningCount() }} warnings</span>
                </div>
            </div>
            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-alert wb-alert-info">
                    {{ $conversionPlan->message }}
                </div>

                <div class="wb-alert wb-alert-info">
                    A signed conversion plan has been prepared for review. No page has been created yet, and draft creation will be implemented next.
                </div>

                <div class="wb-table-wrap">
                    <table class="wb-table">
                        <tbody>
                            <tr>
                                <th>Target title</th>
                                <td>{{ $conversionPlan->input->pageTitle }}</td>
                            </tr>
                            <tr>
                                <th>Target path</th>
                                <td>{{ $conversionPlan->input->pagePath }}</td>
                            </tr>
                            <tr>
                                <th>Page layout</th>
                                <td>{{ $conversionPlan->input->pageLayout }}</td>
                            </tr>
                            <tr>
                                <th>Conversion profile</th>
                                <td>{{ $conversionPlan->profileLabel() }}</td>
                            </tr>
                            <tr>
                                <th>Source</th>
                                <td>{{ $conversionPlan->input->sourceName }} ({{ number_format($conversionPlan->sourceBytes) }} bytes)</td>
                            </tr>
                            <tr>
                                <th>Extracted content root</th>
                                <td>{{ $conversionPlan->contentRootSummary }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                @if ($conversionPlan->suggestions === [])
                    <div class="wb-empty">
                        <div class="wb-empty-title">No content fragments detected</div>
                        <div class="wb-empty-text">Paste or upload HTML with visible page content and analyze again.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Order</th>
                                    <th>Suggested block</th>
                                    <th>Preview</th>
                                    <th>Confidence</th>
                                    <th>Source fragment</th>
                                    <th>Warnings</th>
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
                    <h3>Create draft page</h3>
                    <form method="POST" action="{{ route('admin.pages.converter.create-draft') }}" class="wb-stack wb-gap-0">
                        @csrf
                        <input type="hidden" name="plan_payload" value="{{ $planPayload }}">
                        <input type="hidden" name="plan_signature" value="{{ $planSignature }}">

                        <div class="wb-stack wb-gap-3">
                            <div class="wb-table-wrap">
                                <table class="wb-table">
                                    <tbody>
                                        <tr>
                                            <th>Signed plan blocks</th>
                                            <td>{{ $conversionPlan->suggestionCount() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Signed plan fallbacks</th>
                                            <td>{{ $conversionPlan->fallbackCount() }}</td>
                                        </tr>
                                        <tr>
                                            <th>Signed plan warnings</th>
                                            <td>{{ $conversionPlan->warningCount() }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <div class="wb-alert wb-alert-info">
                                Draft creation will be implemented in the next step.
                            </div>
                            @error('plan_payload')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                            <span class="wb-text-sm wb-text-muted">Submitting this review action will not create pages, slots, blocks, translations, media, revisions, or published content yet.</span>
                            <button type="submit" class="wb-btn wb-btn-primary">Create draft page</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif

    <div class="wb-card">
        <div class="wb-card-header">
            <strong>Source and Target</strong>
        </div>

        <form method="POST" action="{{ route('admin.pages.converter.analyze') }}" enctype="multipart/form-data" class="wb-stack wb-gap-0">
            @csrf

            <div class="wb-card-body wb-stack wb-gap-4">
                <div class="wb-alert wb-alert-info">
                    This first Page Converter step analyzes input only. It does not create, publish, overwrite, crawl, fetch remote URLs, or batch import pages.
                </div>

                @if ($sites->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No accessible sites</div>
                        <div class="wb-empty-text">Page Converter becomes available after your account has access to at least one site.</div>
                    </div>
                @else
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_site_id">Target site</label>
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
                            <label class="wb-label" for="page_converter_locale_id">Target locale</label>
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
                            <label class="wb-label" for="page_converter_page_layout">Page layout</label>
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
                            <label class="wb-label" for="page_converter_conversion_profile">Conversion profile</label>
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
                            <label class="wb-label" for="page_converter_page_title">Page title</label>
                            <input class="wb-input" id="page_converter_page_title" type="text" name="page_title" value="{{ old('page_title') }}" required>
                            @error('page_title')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label class="wb-label" for="page_converter_page_path">Page path or slug</label>
                            <input class="wb-input" id="page_converter_page_path" type="text" name="page_path" value="{{ old('page_path') }}" placeholder="docs/imported-page" required>
                            @error('page_path')
                                <div class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="page_converter_source_html">Source HTML</label>
                        <textarea class="wb-textarea" id="page_converter_source_html" name="source_html" rows="14" placeholder="<main>...</main>">{{ old('source_html') }}</textarea>
                        @error('source_html')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-field">
                        <label class="wb-label" for="page_converter_source_file">Optional HTML file</label>
                        <input class="wb-input" id="page_converter_source_file" type="file" name="source_file" accept=".html,.htm,text/html">
                        @error('source_file')
                            <div class="wb-field-error">{{ $message }}</div>
                        @enderror
                    </div>
                @endif
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.pages.index')" submit-label="Analyze HTML" />
            </div>
        </form>
    </div>
@endsection
