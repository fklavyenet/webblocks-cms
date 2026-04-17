@extends('layouts.admin', ['title' => 'Pages', 'heading' => 'Pages'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Pages',
        'description' => 'Manage pages, review status, and jump into content editing.',
        'count' => $pages->total(),
        'actions' => '<a href="'.route('admin.pages.create').'" class="wb-btn wb-btn-primary">New Page</a>',
    ])

    @include('admin.partials.flash')

    @if ($pages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No pages yet</div>
                    <div class="wb-empty-text">Create your first page to start the CMS content flow.</div>
                    <div class="wb-empty-action">
                        <a href="{{ route('admin.pages.create') }}" class="wb-btn wb-btn-primary">Create Page</a>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-table-wrap">
                    <table class="wb-table wb-table-striped wb-table-hover">
                        <thead>
                            <tr>
                                <th>View</th>
                                <th>Title</th>
                                <th>Slug</th>
                                <th>Slots</th>
                                <th>Blocks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                <tr>
                                    <td>
                                        @if ($page->status === 'published')
                                            <a
                                                href="{{ $page->publicUrl() }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="wb-action-btn wb-action-btn-view"
                                                title="Open page in new tab"
                                                aria-label="Open page in new tab"
                                            >
                                                <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                            </a>
                                        @else
                                            <span class="wb-action-btn" title="Draft page cannot be previewed yet" aria-label="Draft page cannot be previewed yet" aria-disabled="true">
                                                <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                            </span>
                                        @endif
                                    </td>
                                    <td>{{ $page->title }}</td>
                                    <td><code>{{ $page->slug }}</code></td>
                                    <td>{{ $page->slots->pluck('slotType.name')->filter()->implode(', ') ?: '-' }}</td>
                                    <td>{{ $page->blocks_count ?? $page->blocks()->count() }}</td>
                                    <td>
                                        <span class="wb-status-pill {{ $page->status === 'published' ? 'wb-status-active' : 'wb-status-pending' }}">
                                            {{ $page->status }}
                                        </span>
                                    </td>
                                    <td>
                                        <div class="wb-cluster wb-cluster-2 wb-row-end">
                                            <button
                                                type="button"
                                                class="wb-btn wb-btn-secondary"
                                                data-wb-toggle="drawer"
                                                data-wb-target="#pageDetailsDrawer-{{ $page->id }}"
                                                aria-controls="pageDetailsDrawer-{{ $page->id }}"
                                                title="Page details"
                                                aria-label="Open page details"
                                            >
                                                <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
                                            </button>

                                            <div class="wb-action-group">
                                                <a href="{{ route('admin.pages.edit', $page) }}" class="wb-action-btn wb-action-btn-edit" title="Edit page" aria-label="Edit page"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                                <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete page" aria-label="Delete page"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            @include('admin.partials.pagination', ['paginator' => $pages])
        </div>

        @foreach ($pages as $page)
            @include('admin.pages.partials.details-drawer', [
                'page' => $page,
                'drawerId' => 'pageDetailsDrawer-'.$page->id,
            ])
        @endforeach
    @endif
@endsection
