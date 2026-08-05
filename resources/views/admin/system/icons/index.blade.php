@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('icons.'.$key, $adminLocale, $replace);
    $hasActiveFilters = $filters['search'] !== '' || $filters['source'] !== '' || $filters['tag'] !== '' || $filters['status'] !== '';
    $requestedModal = $requestedModal ?? null;
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('title'), 'heading' => $adminText('title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('title'),
        'description' => $adminText('description'),
        'count' => $totalCount,
        'actions' => '<form method="POST" action="'.e(route('admin.system.icons.sync-webblocks-ui')).'">'.csrf_field().'<button type="submit" class="wb-btn wb-btn-primary">'.e($adminText('sync_manifest')).'</button></form><a href="'.e($defaultManifest).'" class="wb-btn wb-btn-secondary" target="_blank" rel="noopener noreferrer">'.e($adminText('open_manifest')).'</a>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            @include('webblocks-cms::admin.partials.listing-filters', [
                'action' => route('admin.system.icons.index'),
                'search' => [
                    'id' => 'icons_search',
                    'name' => 'search',
                    'label' => $adminText('search'),
                    'value' => $filters['search'],
                    'placeholder' => $adminText('search_placeholder'),
                ],
                'selects' => [
                    [
                        'id' => 'icons_source',
                        'name' => 'source',
                        'label' => $adminText('source'),
                        'value' => $filters['source'],
                        'placeholder' => $adminText('all_sources'),
                        'options' => collect($sources)->mapWithKeys(fn (string $source) => [$source => $source])->all(),
                    ],
                    [
                        'id' => 'icons_tag',
                        'name' => 'tag',
                        'label' => $adminText('context'),
                        'value' => $filters['tag'],
                        'placeholder' => $adminText('all_contexts'),
                        'options' => $tags,
                    ],
                    [
                        'id' => 'icons_status',
                        'name' => 'status',
                        'label' => $adminText('status'),
                        'value' => $filters['status'],
                        'placeholder' => $adminText('all_statuses'),
                        'options' => ['active' => $adminText('active'), 'inactive' => $adminText('inactive')],
                    ],
                ],
                'showReset' => $hasActiveFilters,
                'resetUrl' => route('admin.system.icons.index'),
                'applyLabel' => $adminText('apply_filters'),
            ])
        </div>
    </div>

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $filteredCount }}</span>
            </div>
        </div>
        @if ($icons->isEmpty())
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">{{ $adminText('empty_title') }}</div>
                    <div class="wb-empty-text">
                        {{ $hasActiveFilters ? $adminText('empty_filtered_text') : $adminText('empty_text') }}
                    </div>
                    @if ($hasActiveFilters)
                        <div class="wb-empty-action">
                            <a href="{{ route('admin.system.icons.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('clear_filters') }}</a>
                        </div>
                    @endif
                </div>
            </div>
        @else
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>{{ $adminText('icon') }}</th>
                                <th>{{ $adminText('label') }}</th>
                                <th>{{ $adminText('source') }}</th>
                                <th>{{ $adminText('contexts') }}</th>
                                <th>{{ $adminText('status') }}</th>
                                <th>{{ $adminText('sort') }}</th>
                                <th>{{ $adminText('actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($icons as $icon)
                                <tr>
                                    <td class="wb-nowrap">
                                        <span class="wb-cluster wb-cluster-2">
                                            <i class="wb-icon {{ $icon->css_class }}" aria-hidden="true"></i>
                                            <code>{{ $icon->css_class }}</code>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <strong>{{ $icon->label }}</strong>
                                            <span class="wb-text-sm wb-text-muted"><code>{{ $icon->slug }}</code></span>
                                        </div>
                                    </td>
                                    <td>{{ $icon->source }}</td>
                                    <td>
                                        <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                            @foreach (array_values(array_unique(array_merge($icon->contexts ?? [], $icon->categories ?? []))) as $tag)
                                                <span class="wb-status-pill wb-status-pending">{{ $tag }}</span>
                                            @endforeach
                                        </div>
                                    </td>
                                    <td>
                                        <span class="wb-status-pill {{ $icon->is_active ? 'wb-status-active' : 'wb-status-danger' }}">{{ $icon->is_active ? $adminText('active') : $adminText('inactive') }}</span>
                                    </td>
                                    <td>{{ $icon->sort_order }}</td>
                                    <td>
                                        <div class="wb-action-group">
                                            <a href="{{ route('admin.system.icons.index', array_filter(array_merge(request()->query(), ['modal' => 'edit-icon', 'icon' => $icon->id]))) }}" class="wb-action-btn wb-action-btn-edit" title="{{ $adminText('edit_icon') }}" aria-label="{{ $adminText('edit_icon') }}" aria-haspopup="dialog" aria-controls="iconEditModal-{{ $icon->id }}">
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
        @endif

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $icons, 'ariaLabel' => $adminText('pagination'), 'compact' => true])
    </div>
@endsection

@push('overlays')
    @if ($requestedModal === 'edit-icon' && $editIcon)
        @include('webblocks-cms::admin.system.icons.partials.edit-modal', [
            'icon' => $editIcon,
            'closeUrl' => $closeUrl,
        ])
    @endif
@endpush
