@php
    $pageTitle = 'Move Page to Another Site';
    $siteName = $page->site?->name ?? 'Site';
    $backUrl = route('admin.pages.edit', $page);
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Move this page to another site with explicit validation and site-scoped remapping.',
        'actions' => '<a href="'.$backUrl.'" class="wb-btn wb-btn-secondary">Back to Page</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div>
                    <strong>{{ $page->title }}</strong>
                    <div class="wb-text-sm wb-text-muted">Current site: {{ $siteName }}</div>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>Current public path:</strong> {{ $page->publicPath() ?? 'Not routable' }}</div>
                    <div><strong>Workflow:</strong> {{ $page->workflowLabel() }}</div>
                    <div><strong>Translations:</strong> {{ $page->translations->pluck('locale.code')->filter()->implode(', ') ?: 'None' }}</div>
                </div>

                <div class="wb-alert wb-alert-warning">
                    <div>
                        <div class="wb-alert-title">Warning</div>
                        <div>Moving a page changes site ownership. Path conflicts block the move. Shared Slot references must be remappable. Navigation may need manual review after the move.</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="wb-card wb-card-muted">
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.pages.move-site.store', $page) }}" class="wb-stack wb-gap-4">
                    @csrf

                    <div class="wb-field">
                        <label for="target_site_id">Target site</label>
                        <select id="target_site_id" name="target_site_id" class="wb-select" required>
                            <option value="">Choose a site</option>
                            @foreach ($sites as $site)
                                <option value="{{ $site->id }}" @selected((int) old('target_site_id') === (int) $site->id)>{{ $site->name }}</option>
                            @endforeach
                        </select>
                        @error('target_site_id')
                            <div class="wb-alert wb-alert-danger wb-text-sm">{{ $message }}</div>
                        @enderror
                    </div>

                    <x-admin.form-actions
                        :cancel-url="$backUrl"
                        submit-label="Move to another site"
                    />
                </form>
            </div>
        </div>
    </div>
@endsection
