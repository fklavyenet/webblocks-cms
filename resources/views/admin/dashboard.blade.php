@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
    $visitorSummary = $visitorSummary ?? [
        'is_enabled' => false,
        'table_exists' => false,
        'range_label' => $adminText('dashboard.last_7_days'),
        'total_page_views' => 0,
        'unique_visitors' => 0,
        'top_page_path' => null,
        'top_page_views' => 0,
    ];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('dashboard.title'), 'heading' => $adminText('dashboard.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('dashboard.title'),
        'description' => $adminText('dashboard.description'),
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.actions_title') }}</strong>
                    <span class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.actions_subtitle') }}</span>
                </div>

                <div class="wb-card-body">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.quick_actions') }}</div>
                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                            <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('dashboard.new_page') }}</a>
                            <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.pages') }}</a>
                            <a href="{{ route('admin.shared-slots.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.shared_slots') }}</a>
                            @can('access-system')
                                <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.sites') }}</a>
                                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.backups') }}</a>
                                <a href="{{ route('admin.system.updates.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.update') }}</a>
                            @endcan
                        </div>

                        @cannot('access-system')
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('dashboard.system_only') }}</div>
                        @endcannot
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>{{ $adminText('dashboard.overview') }}</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped">
                            <tbody>
                                <tr>
                                    <th scope="row">{{ $adminText('dashboard.pages') }}</th>
                                    <td>{{ number_format($stats['pages']) }}</td>
                                    <td class="wb-text-muted">{{ $adminText('dashboard.published_drafts', ['published' => $stats['publishedPages'], 'drafts' => $stats['draftPages']]) }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ $adminText('dashboard.blocks') }}</th>
                                    <td>{{ number_format($stats['blocks']) }}</td>
                                    <td class="wb-text-muted">{{ $adminText('dashboard.blocks_help') }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ $adminText('dashboard.media') }}</th>
                                    <td>{{ number_format($stats['media']) }}</td>
                                    <td class="wb-text-muted">{{ $adminText('dashboard.media_help') }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ $adminText('dashboard.slot_types') }}</th>
                                    <td>{{ number_format($stats['slotTypes']) }}</td>
                                    <td class="wb-text-muted">{{ $adminText('dashboard.slot_types_help') }}</td>
                                </tr>
                                <tr>
                                    <th scope="row">{{ $adminText('dashboard.block_types') }}</th>
                                    <td>{{ number_format($stats['blockTypes']) }}</td>
                                    <td class="wb-text-muted">{{ $adminText('dashboard.block_types_help') }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.recent_pages') }}</strong>
                    <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.view_all') }}</a>
                </div>

                <div class="wb-card-body">
                    @if ($recentPages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('dashboard.no_pages') }}</div>
                            <div class="wb-empty-text">{{ $adminText('dashboard.no_pages_help') }}</div>
                            <div class="wb-empty-action">
                                <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('dashboard.create_page') }}</a>
                            </div>
                        </div>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentPages as $page)
                                <a href="{{ route('admin.pages.edit', $page) }}" class="wb-link-list-item">
                                    <div class="wb-link-list-main">
                                        <div class="wb-link-list-title">{{ $page->title }}</div>
                                        <div class="wb-link-list-meta">
                                            <code>{{ $page->slug }}</code> | {{ $page->site?->name }} | {{ $page->slots->pluck('slotType.name')->filter()->implode(', ') ?: $adminText('dashboard.no_slots') }} |
                                            <span class="wb-status-pill {{ $page->workflowBadgeClass() }}">{{ $page->workflowLabel() }}</span>
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.recent_media') }}</strong>
                    <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.manage') }}</a>
                </div>

                <div class="wb-card-body">
                    @if ($recentAssets->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('dashboard.no_media') }}</div>
                        </div>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentAssets as $asset)
                                <a href="{{ route('admin.media.edit', $asset) }}" class="wb-link-list-item">
                                    <div class="wb-link-list-main">
                                        <div class="wb-link-list-title">{{ $asset->title ?: $asset->original_name }}</div>
                                        <div class="wb-link-list-meta">{{ $asset->kind }} | {{ $asset->humanSize() }}</div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="wb-grid wb-grid-1">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>{{ $adminText('dashboard.visitor_summary') }}</strong>
                    <span class="wb-text-sm wb-text-muted">{{ $visitorSummary['range_label'] }}</span>
                </div>

                <div class="wb-card-body">
                    @if (! $visitorSummary['is_enabled'])
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('dashboard.visitor_disabled') }}</div>
                        </div>
                    @elseif (! $visitorSummary['table_exists'])
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">{{ $adminText('dashboard.visitor_missing') }}</div>
                        </div>
                    @else
                        <div class="wb-table-wrap">
                            <table class="wb-table wb-table-striped">
                                <tbody>
                                    <tr>
                                        <th scope="row">{{ $adminText('dashboard.page_views') }}</th>
                                        <td>{{ number_format($visitorSummary['total_page_views']) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{{ $adminText('dashboard.unique_visitors') }}</th>
                                        <td>{{ number_format($visitorSummary['unique_visitors']) }}</td>
                                    </tr>
                                    <tr>
                                        <th scope="row">{{ $adminText('dashboard.top_page') }}</th>
                                        @if ($visitorSummary['top_page_path'])
                                            <td><code>{{ $visitorSummary['top_page_path'] }}</code></td>
                                            <td class="wb-text-muted">{{ $adminText('dashboard.views', ['count' => number_format($visitorSummary['top_page_views'])]) }}</td>
                                        @else
                                            <td colspan="2" class="wb-text-muted">{{ $adminText('dashboard.no_visits') }}</td>
                                        @endif
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        @if (! empty($pluginDashboardWidgets))
            <div class="wb-grid wb-grid-2">
                @foreach ($pluginDashboardWidgets as $widget)
                    <div class="wb-card" data-plugin-dashboard-widget="{{ $widget->key() }}" data-plugin-handle="{{ $widget->pluginHandle() }}">
                        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                            <strong>{{ $widget->titleText() }}</strong>
                            <span class="wb-text-sm wb-text-muted">{{ $widget->pluginHandle() }}</span>
                        </div>
                        <div class="wb-card-body wb-stack wb-gap-2">
                            @if ($widget->valueText() !== null)
                                <strong>{{ $widget->valueText() }}</strong>
                            @endif
                            @if ($widget->descriptionText() !== null)
                                <div class="wb-text-sm wb-text-muted">{{ $widget->descriptionText() }}</div>
                            @endif
                            @if ($widget->urlValue() !== null)
                                <a href="{{ $widget->urlValue() }}" class="wb-btn wb-btn-secondary">{{ $adminText('dashboard.open') }}</a>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
