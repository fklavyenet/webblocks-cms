@php
    $pageTitle = 'Duplicate Page';
    $siteName = $page->site?->name ?? 'Site';
    $backUrl = route('admin.pages.edit', $page);
    $defaultTitle = old('title', trim(($defaultTranslation?->name ?? $page->title ?? 'Page').' Copy'));
    $defaultSlug = old('slug', trim(($defaultTranslation?->slug ?? $page->slug ?? 'page').'-copy', '-'));
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Create a new draft page by copying the current page into the same site or another accessible site.',
        'actions' => '<a href="'.$backUrl.'" class="wb-btn wb-btn-secondary">Back to Page</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $page->title }}</strong>
                    <div class="wb-text-sm wb-text-muted">Source site: {{ $siteName }}</div>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>Workflow:</strong> {{ $page->workflowLabel() }}</div>
                    <div><strong>Translations:</strong> {{ $page->translations->pluck('locale.code')->filter()->implode(', ') ?: 'None' }}</div>
                    <div><strong>Revision history:</strong> Existing revisions are not copied to the duplicate.</div>
                    <div><strong>Navigation:</strong> Page-linked navigation is not duplicated automatically.</div>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">Warnings</div>
                        <div>Duplicated pages always start as draft. Every copied locale needs a unique path on the target site.</div>
                        @if ($sharedSlotHandles->isNotEmpty())
                            <div class="wb-text-sm wb-mt-2">Shared Slot references stay in place for same-site duplicates. Cross-site duplicates remap only compatible same-handle Shared Slots: {{ $sharedSlotHandles->implode(', ') }}.</div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.pages.duplicate.store', $page) }}" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-field wb-stack-2">
                        <label for="target_site_id">Target site</label>
                        <select id="target_site_id" name="target_site_id" class="wb-select" required>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('target_site_id', $page->site_id) === (int) $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                        @error('target_site_id')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-field wb-stack-2">
                            <label for="title">New page title</label>
                            <input id="title" name="title" class="wb-input" type="text" value="{{ $defaultTitle }}" required>
                            @error('title')
                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field wb-stack-2">
                            <label for="slug">New page slug</label>
                            <input id="slug" name="slug" class="wb-input" type="text" value="{{ $defaultSlug }}" required>
                            <div class="wb-text-sm wb-text-muted">Source path: {{ $defaultTranslation?->path ?? 'Missing' }}</div>
                            @error('slug')
                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>

                    @error('translations')
                        <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                    @enderror

                    @if ($secondaryTranslations->isNotEmpty())
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                                <strong>Additional Translations</strong>
                                <span class="wb-text-sm wb-text-muted">These locales will also be copied and must stay unique on the target site.</span>
                            </div>
                            <div class="wb-card-body wb-stack wb-gap-4">
                                @foreach ($secondaryTranslations as $index => $translation)
                                    @php
                                        $oldTranslation = old('translations.'.$index, []);
                                        $suggestedName = trim(($translation->name ?? strtoupper($translation->locale?->code ?? 'Locale')).' Copy');
                                        $suggestedSlug = trim(($translation->slug ?? 'page').'-copy', '-');
                                    @endphp
                                    <input type="hidden" name="translations[{{ $index }}][locale_id]" value="{{ $translation->locale_id }}">
                                    <div class="wb-grid wb-grid-2">
                                        <div class="wb-field wb-stack-2">
                                            <label for="translation-name-{{ $translation->locale_id }}">{{ strtoupper($translation->locale?->code ?? (string) $translation->locale_id) }} title</label>
                                            <input id="translation-name-{{ $translation->locale_id }}" name="translations[{{ $index }}][name]" class="wb-input" type="text" value="{{ $oldTranslation['name'] ?? $suggestedName }}" required>
                                            @error('translations.'.$index.'.name')
                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="wb-field wb-stack-2">
                                            <label for="translation-slug-{{ $translation->locale_id }}">{{ strtoupper($translation->locale?->code ?? (string) $translation->locale_id) }} slug</label>
                                            <input id="translation-slug-{{ $translation->locale_id }}" name="translations[{{ $index }}][slug]" class="wb-input" type="text" value="{{ $oldTranslation['slug'] ?? $suggestedSlug }}" required>
                                            <div class="wb-text-sm wb-text-muted">Source path: {{ $translation->path }}</div>
                                            @error('translations.'.$index.'.slug')
                                                <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <x-admin.form-actions
                        :cancel-url="$backUrl"
                        submit-label="Duplicate page"
                    />
                </form>
            </div>
        </div>
    </div>
@endsection
