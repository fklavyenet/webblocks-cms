@php
    $pageTitle = 'Edit Page: '.$page->title;
    $pagePublicUrl = $page->publicUrl();
@endphp

@extends('layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.pages.index').'">Pages</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.$page->title.'</span></li></ol></nav>',
        'title' => $pageTitle,
        'description' => 'Manage the canonical page, English base fields, and translation routing from one compact screen.',
        'actions' => $pagePublicUrl ? '<a href="'.$pagePublicUrl.'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i> <span>View Page</span></a>' : '',
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-body">
            <form method="POST" action="{{ route('admin.pages.update', $page) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                @include('admin.pages._form')
            </form>
        </div>
    </div>

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
            <strong>Translations</strong>
            <span class="wb-text-sm wb-text-muted">Page title and routing only</span>
        </div>
        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Locale</th>
                            <th>Status</th>
                            <th>Slug</th>
                            <th>Path</th>
                            <th>Open</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($translationStatuses as $translationStatus)
                            @php
                                $locale = $translationStatus['locale'];
                                $translation = $translationStatus['translation'];
                            @endphp
                            <tr>
                                <td>
                                    <div class="wb-cluster wb-cluster-2">
                                        <strong>{{ strtoupper($locale->code) }}</strong>
                                        <span>{{ $locale->name }}</span>
                                        @if ($translationStatus['is_default'])
                                            <span class="wb-status-pill wb-status-info">Default</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <span class="wb-status-pill {{ $translationStatus['is_missing'] ? 'wb-status-pending' : 'wb-status-active' }}">
                                        {{ $translationStatus['is_missing'] ? 'Missing' : 'Ready' }}
                                    </span>
                                </td>
                                <td>{{ $translation?->slug ?? 'Missing' }}</td>
                                <td>{{ $translationStatus['public_path'] ?? 'Missing' }}</td>
                                <td>
                                    @if ($translationStatus['public_url'])
                                        <a href="{{ $translationStatus['public_url'] }}" target="_blank" rel="noopener noreferrer" class="wb-action-btn wb-action-btn-view" title="Open translation" aria-label="Open translation">
                                            <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                        </a>
                                    @else
                                        <span class="wb-action-btn" aria-disabled="true"><i class="wb-icon wb-icon-globe" aria-hidden="true"></i></span>
                                    @endif
                                </td>
                                <td>
                                    @if ($translation)
                                        <a href="{{ route('admin.pages.translations.edit', [$page, $translation]) }}" class="wb-btn wb-btn-secondary">Edit translation</a>
                                    @else
                                        <a href="{{ route('admin.pages.translations.create', [$page, $locale]) }}" class="wb-btn wb-btn-secondary">Add translation</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
