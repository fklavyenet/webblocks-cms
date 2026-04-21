@extends('layouts.admin', ['title' => 'Admin Dashboard', 'heading' => 'Dashboard'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Dashboard',
        'description' => 'Review the publishing state and jump into the core page-first tools.',
    ])

    @include('admin.partials.flash')

    <div class="wb-stack wb-stack-4">
        <div class="wb-grid wb-grid-2">
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

            <div class="wb-card wb-card-muted">
                <div class="wb-card-header">
                    <strong>Actions and Shortcuts</strong>
                </div>

                <div class="wb-card-body">
                    <div class="wb-stack wb-stack-4">
                        <div class="wb-stack wb-stack-2">
                            <div class="wb-text-sm wb-text-muted">Start editing</div>
                            <div class="wb-cluster wb-cluster-2">
                                <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">Create Page</a>
                                <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">Open Pages</a>
                                <a href="{{ route('admin.media.index') }}" class="wb-btn wb-btn-secondary">Open Media</a>
                            </div>
                        </div>

                        <div class="wb-stack wb-stack-2">
                            <div class="wb-text-sm wb-text-muted">Catalog</div>
                            <div class="wb-cluster wb-cluster-2">
                                <a href="{{ route('admin.slot-types.index') }}" class="wb-btn wb-btn-secondary">Slot Types</a>
                                <a href="{{ route('admin.block-types.index') }}" class="wb-btn wb-btn-secondary">Block Types</a>
                            </div>
                        </div>
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
                                            <span class="wb-status-pill {{ $page->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">{{ $page->status }}</span>
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
    </div>
@endsection
