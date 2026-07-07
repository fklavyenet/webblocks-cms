@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $pageLayoutsText = fn (string $key, array $replace = []) => $adminTranslator->admin('page_layouts.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageLayoutsText('title'), 'heading' => $pageLayoutsText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageLayoutsText('title'),
        'description' => $pageLayoutsText('index_description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $pageLayoutsText('title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>

            <a href="{{ route('admin.page-layouts.create') }}" class="wb-btn wb-btn-primary">{{ $pageLayoutsText('new_page_layout') }}</a>
        </div>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $pageLayoutsText('name') }}</th>
                            <th>{{ $pageLayoutsText('handle') }}</th>
                            <th>{{ $pageLayoutsText('body_class') }}</th>
                            <th>{{ $pageLayoutsText('status') }}</th>
                            <th>{{ $pageLayoutsText('ownership') }}</th>
                            <th>{{ $pageLayoutsText('sort_order') }}</th>
                            <th>{{ $pageLayoutsText('actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pageLayouts as $pageLayout)
                            <tr>
                                <td>
                                    <div class="wb-stack wb-stack-1">
                                        <strong>{{ $pageLayout->name }}</strong>
                                        <span class="wb-text-sm wb-text-muted">{{ $pageLayout->description ?: '-' }}</span>
                                    </div>
                                </td>
                                <td class="wb-nowrap"><code>{{ $pageLayout->handle }}</code></td>
                                <td class="wb-nowrap"><code>{{ $pageLayout->body_class ?: '-' }}</code></td>
                                <td><span class="wb-status-pill {{ $pageLayout->statusBadgeClass() }}">{{ $pageLayout->is_active ? $pageLayoutsText('active') : $pageLayoutsText('inactive') }}</span></td>
                                <td><span class="wb-status-pill {{ $pageLayout->is_system ? 'wb-status-info' : 'wb-status-pending' }}">{{ $pageLayout->is_system ? $pageLayoutsText('system') : $pageLayoutsText('custom') }}</span></td>
                                <td class="wb-nowrap">{{ $pageLayout->sort_order }}</td>
                                <td class="wb-nowrap">
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.page-layouts.edit', $pageLayout) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $pageLayoutsText('edit_page_layout') }}" aria-label="{{ $pageLayoutsText('edit_page_layout') }}">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="wb-card-footer wb-text-sm wb-text-muted">
            {{ $pageLayoutsText('index_footer') }}
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $pageLayouts])
    </div>
@endsection
