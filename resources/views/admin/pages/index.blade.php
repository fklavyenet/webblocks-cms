@extends('layouts.admin', ['title' => 'Pages', 'heading' => 'Pages'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Pages',
        'description' => 'Manage pages, review status, and jump into content editing.',
        'count' => $pages->total(),
        'actions' => '<a href="'.route('admin.pages.create').'" class="wb-btn wb-btn-primary">New Page</a>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-body">
            <form method="GET" action="{{ route('admin.pages.index') }}" class="wb-cluster wb-cluster-2 wb-cluster-between">
                <div class="wb-cluster wb-cluster-2">
                    <div class="wb-stack wb-gap-1">
                    <label for="pages_search">Search</label>
                    <input id="pages_search" name="search" type="text" class="wb-input" value="{{ $filters['search'] }}" placeholder="Search by title, slug, or page type">
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="pages_status">Status</label>
                        <select id="pages_status" name="status" class="wb-select">
                            <option value="">All statuses</option>
                            <option value="draft" @selected($filters['status'] === 'draft')>Draft</option>
                            <option value="published" @selected($filters['status'] === 'published')>Published</option>
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="pages_sort">Sort by</label>
                        <select id="pages_sort" name="sort" class="wb-select">
                            <option value="created_at" @selected($filters['sort'] === 'created_at')>Created at</option>
                            <option value="updated_at" @selected($filters['sort'] === 'updated_at')>Updated at</option>
                            <option value="title" @selected($filters['sort'] === 'title')>Title</option>
                            <option value="slug" @selected($filters['sort'] === 'slug')>Slug</option>
                            <option value="status" @selected($filters['sort'] === 'status')>Status</option>
                        </select>
                    </div>

                    <div class="wb-stack wb-gap-1">
                        <label for="pages_direction">Direction</label>
                        <select id="pages_direction" name="direction" class="wb-select">
                            <option value="desc" @selected($filters['direction'] === 'desc')>Descending</option>
                            <option value="asc" @selected($filters['direction'] === 'asc')>Ascending</option>
                        </select>
                    </div>
                </div>

                <div class="wb-cluster wb-cluster-2" style="align-self: end;">
                    <button type="submit" class="wb-btn wb-btn-primary">Apply</button>
                    @if ($filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'created_at' || $filters['direction'] !== 'desc')
                        <a href="{{ route('admin.pages.index') }}" class="wb-btn wb-btn-secondary">Clear</a>
                    @endif
                </div>
            </form>
        </div>
    </div>

    @if ($pages->isEmpty())
        <div class="wb-card">
            <div class="wb-card-body">
                <div class="wb-empty">
                    <div class="wb-empty-title">No pages found</div>
                    <div class="wb-empty-text">Adjust the filters or create your first page to start the CMS content flow.</div>
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
                                        <div class="wb-action-group">
                                            <button
                                                type="button"
                                                class="wb-action-btn"
                                                data-wb-toggle="drawer"
                                                data-wb-target="#pageDetailsDrawer-{{ $page->id }}"
                                                aria-controls="pageDetailsDrawer-{{ $page->id }}"
                                                title="Page details"
                                                aria-label="Open page details"
                                            >
                                                <i class="wb-icon wb-icon-panel-right" aria-hidden="true"></i>
                                            </button>

                                            <a href="{{ route('admin.pages.edit', $page) }}" class="wb-action-btn wb-action-btn-edit" title="Edit page" aria-label="Edit page"><i class="wb-icon wb-icon-pencil" aria-hidden="true"></i></a>
                                            <form method="POST" action="{{ route('admin.pages.destroy', $page) }}" onsubmit="return confirm('Delete this page?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="wb-action-btn wb-action-btn-delete" title="Delete page" aria-label="Delete page"><i class="wb-icon wb-icon-trash" aria-hidden="true"></i></button>
                                            </form>
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
