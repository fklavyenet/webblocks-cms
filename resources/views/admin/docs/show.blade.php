@extends('layouts.admin', ['title' => $document['title'], 'heading' => 'Docs'])

@php
    $breadcrumb = '<nav class="wb-page-breadcrumb wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.dashboard').'">Admin</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.docs.index').'">Docs</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($document['title']).'</span></li></ol></nav>';
    $actions = '<a href="'.route('admin.docs.index').'" class="wb-btn wb-btn-secondary">All Docs</a>';
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => $document['title'],
        'description' => $document['description'],
        'breadcrumb' => $breadcrumb,
        'actions' => $actions,
    ])

    <div class="wb-grid wb-grid-4 wb-gap-4">
        <div class="wb-public-main-column">
            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-4">
                    <div class="wb-callout wb-callout-info">
                        <strong class="wb-callout-title">Read-only pilot</strong>
                        Markdown is rendered from the repository with raw HTML stripped. Internal Markdown links that are not part of this pilot route back to the docs index instead of a missing source path.
                    </div>

                    <div class="wb-content-shell wb-content-shell-wide">
                        <header class="wb-content-header">
                            <h2 class="wb-content-title">{{ $document['title'] }}</h2>
                            <p class="wb-content-subtitle">{{ $document['description'] }}</p>
                            <div class="wb-content-meta">
                                <span><code>{{ $document['relative_path'] }}</code></span>
                                @if ($document['updated_at'])
                                    <span class="wb-content-meta-divider"></span>
                                    <span>Updated {{ $document['updated_at']->format('Y-m-d H:i') }}</span>
                                @endif
                            </div>
                        </header>

                        <div class="wb-content-body">
                            {!! $document['html'] !!}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <aside>
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header">
                    <strong>Pilot docs</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-link-list">
                        @foreach ($documents as $item)
                            <a href="{{ route('admin.docs.show', $item['slug']) }}" class="wb-link-list-item">
                                <div class="wb-link-list-main">
                                    <div class="wb-link-list-title">{{ $item['title'] }}</div>
                                    <div class="wb-link-list-meta"><code>{{ $item['relative_path'] }}</code></div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        </aside>
    </div>
@endsection
