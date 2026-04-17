@extends('layouts.admin', ['title' => 'Page Builder: '.$page->title, 'heading' => 'Page Builder'])

@section('content')
    <div class="wb-stack wb-stack-2">
        @include('admin.partials.page-header', [
            'title' => 'Page Builder: '.$page->title,
            'description' => 'Build the page by arranging and editing only the blocks that belong to this page.',
            'actions' => view('admin.partials.page-actions', ['page' => $page]),
        ])

        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $page->publicPath() }}</span>
            <span>{{ $page->pageType?->name ?? ucfirst($page->page_type ?? 'page') }}</span>
            <span>{{ $page->layout?->name ?? 'No layout' }}</span>
            <span>{{ $blockSummary['total'] }} blocks</span>
            <span>{{ $blockSummary['published'] }} published</span>
            <span>{{ $blockSummary['draft'] }} draft</span>
            <span class="wb-status-pill {{ $page->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $page->status }}</span>
        </div>
    </div>

    @include('admin.partials.flash')

    <div class="wb-card wb-card-accent">
        <div class="wb-card-header">
            <strong>Page Builder</strong>
        </div>
        <div class="wb-card-body">
            @if ($outline->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">No starter content found</div>
                    <div class="wb-empty-text">This page should begin with a starter structure so editing feels like composing a real page.</div>
                    <div class="wb-empty-action">
                        <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">Manage Slots</a>
                    </div>
                </div>
            @else
                <div class="wb-stack wb-stack-2">
                    @foreach ($outline as $item)
                        @include('admin.pages.partials.block-outline-item', ['item' => $item, 'page' => $page])
                    @endforeach

                    <div class="wb-row wb-row-center">
                        <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">Manage Slots</a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @include('admin.pages.partials.details-drawer', ['page' => $page])
@endsection
