@php
    $pageBuilderText = fn (string $key, array $replace = []) => __('webblocks-cms::admin.page_builder.'.$key, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageBuilderText('title_with_page', ['title' => $page->title]), 'heading' => $pageBuilderText('title')])

@section('content')
    <div class="wb-stack wb-stack-2">
        @include('webblocks-cms::admin.partials.page-header', [
            'title' => $pageBuilderText('title_with_page', ['title' => $page->title]),
            'description' => $pageBuilderText('description'),
            'actions' => view('webblocks-cms::admin.partials.page-actions', ['page' => $page]),
        ])

        <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
            <span>{{ $page->publicPath() }}</span>
            <span>{{ $page->pageType?->name ?? ucfirst($page->page_type ?? $pageBuilderText('page_type_fallback')) }}</span>
            <span>{{ $page->layout?->name ?? $pageBuilderText('no_layout') }}</span>
            <span>{{ $pageBuilderText('blocks_count', ['count' => $blockSummary['total']]) }}</span>
            <span>{{ $pageBuilderText('published_count', ['count' => $blockSummary['published']]) }}</span>
            <span>{{ $pageBuilderText('draft_count', ['count' => $blockSummary['draft']]) }}</span>
            <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
        </div>
    </div>

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-accent">
        <div class="wb-card-header">
            <strong>{{ $pageBuilderText('title') }}</strong>
        </div>
        <div class="wb-card-body">
            @if ($outline->isEmpty())
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $pageBuilderText('empty_title') }}</div>
                    <div class="wb-empty-text">{{ $pageBuilderText('empty_text') }}</div>
                    <div class="wb-empty-action">
                        <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">{{ $pageBuilderText('manage_slots') }}</a>
                    </div>
                </div>
            @else
                <div class="wb-stack wb-stack-2">
                    @foreach ($outline as $item)
                        @include('webblocks-cms::admin.pages.partials.block-outline-item', ['item' => $item, 'page' => $page])
                    @endforeach

                    <div class="wb-row wb-row-center">
                        <a href="{{ route('admin.pages.edit', $page) }}" class="wb-btn wb-btn-primary">{{ $pageBuilderText('manage_slots') }}</a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (request()->query('details'))
        @include('webblocks-cms::admin.pages.partials.details-modal', [
            'page' => $page,
            'closeUrl' => request()->fullUrlWithQuery(['details' => null]),
        ])
    @endif
@endsection
