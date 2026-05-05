@extends('layouts.admin', ['title' => 'Admin Dashboard', 'heading' => 'Dashboard'])

@php
    $visitorSummary = $visitorSummary ?? [
        'is_enabled' => false,
        'table_exists' => false,
        'range_label' => 'Last 7 days',
        'total_page_views' => 0,
        'unique_visitors' => 0,
        'top_page_path' => null,
        'top_page_views' => 0,
    ];
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Dashboard',
        'description' => 'Review the publishing state and jump into the core page-first tools.',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
            <div class="wb-card wb-card-muted">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Actions and Shortcuts</strong>
                    <span class="wb-text-sm wb-text-muted">Jump into the core admin tasks.</span>
                </div>

                <div class="wb-card-body">
                    <div class="wb-stack wb-gap-3">
                        <div class="wb-text-sm wb-text-muted">Quick actions</div>
                        <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                            <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">New Page</a>
                            <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">Pages</a>
                            <a href="{{ route('admin.shared-slots.index') }}" class="wb-btn wb-btn-secondary">Shared Slots</a>
                            @can('access-system')
                                <a href="{{ route('admin.sites.index') }}" class="wb-btn wb-btn-secondary">Sites</a>
                                <a href="{{ route('admin.system.backups.index') }}" class="wb-btn wb-btn-secondary">Backups</a>
                                <a href="{{ route('admin.system.updates.index') }}" class="wb-btn wb-btn-secondary">Update</a>
                            @endcan
                        </div>

                        @cannot('access-system')
                            <div class="wb-text-sm wb-text-muted">Sites, backups, and system updates are available to super admins only.</div>
                        @endcannot
                    </div>
                </div>
            </div>

            <div class="wb-card">
                <div class="wb-card-header">
                    <strong>Overview</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span>Pages</span>
                            <span class="wb-status-pill wb-status-info">{{ $stats['pages'] }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">{{ $stats['publishedPages'] }} published | {{ $stats['draftPages'] }} drafts</div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span>Blocks</span>
                            <span class="wb-status-pill wb-status-info">{{ $stats['blocks'] }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">Block instances placed on pages</div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span>Media</span>
                            <span class="wb-status-pill wb-status-info">{{ $stats['media'] }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">Shared uploads and asset references</div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span>Slot Types</span>
                            <span class="wb-status-pill wb-status-pending">{{ $stats['slotTypes'] }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">Fixed page regions: Header, Main, Sidebar, Footer</div>

                        <div class="wb-cluster wb-cluster-between wb-cluster-2">
                            <span>Block Types</span>
                            <span class="wb-status-pill wb-status-pending">{{ $stats['blockTypes'] }}</span>
                        </div>
                        <div class="wb-text-sm wb-text-muted">Available block definitions for editors</div>
                    </div>
                </div>
            </div>

        </div>

        <div class="wb-grid wb-grid-2">
            <div class="wb-card">
                <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2">
                    <strong>Recent Pages</strong>
                    <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">View All</a>
                </div>

                <div class="wb-card-body">
                    @if ($recentPages->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No pages yet</div>
                            <div class="wb-empty-text">Create the first page to start editing content.</div>
                            <div class="wb-empty-action">
                                <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">Create Page</a>
                            </div>
                        </div>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentPages as $page)
                                <a href="{{ route('admin.pages.edit', $page) }}" class="wb-link-list-item">
                                    <div class="wb-link-list-main">
                                        <div class="wb-link-list-title">{{ $page->title }}</div>
                                        <div class="wb-link-list-meta">
                                            <code>{{ $page->slug }}</code> | {{ $page->site?->name }} | {{ $page->slots->pluck('slotType.name')->filter()->implode(', ') ?: 'No slots' }} |
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
                    <strong>Recent Media</strong>
                    <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Manage</a>
                </div>

                <div class="wb-card-body">
                    @if ($recentAssets->isEmpty())
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">No media yet</div>
                        </div>
                    @else
                        <div class="wb-link-list">
                            @foreach ($recentAssets as $asset)
                                <a href="{{ route('admin.media.show', $asset) }}" class="wb-link-list-item">
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
                    <strong>Visitor Summary</strong>
                    <span class="wb-text-sm wb-text-muted">{{ $visitorSummary['range_label'] }}</span>
                </div>

                <div class="wb-card-body">
                    @if (! $visitorSummary['is_enabled'])
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Visitor reports are disabled</div>
                        </div>
                    @elseif (! $visitorSummary['table_exists'])
                        <div class="wb-empty wb-empty-sm">
                            <div class="wb-empty-title">Visitor reports migration is missing</div>
                        </div>
                    @else
                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack wb-gap-1">
                                <div class="wb-text-sm wb-text-muted">Page views</div>
                                <strong>{{ number_format($visitorSummary['total_page_views']) }}</strong>
                            </div>
                            <div class="wb-stack wb-gap-1">
                                <div class="wb-text-sm wb-text-muted">Unique visitors</div>
                                <strong>{{ number_format($visitorSummary['unique_visitors']) }}</strong>
                            </div>
                        </div>

                        <div class="wb-stack wb-gap-1">
                            <div class="wb-text-sm wb-text-muted">Top page</div>
                            @if ($visitorSummary['top_page_path'])
                                <div><code>{{ $visitorSummary['top_page_path'] }}</code></div>
                                <div class="wb-text-sm wb-text-muted">{{ number_format($visitorSummary['top_page_views']) }} views</div>
                            @else
                                <div class="wb-text-sm wb-text-muted">No public page visits in the current window yet.</div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
