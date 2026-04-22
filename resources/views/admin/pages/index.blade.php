@php
    $siteContext = $activeSite?->name ?? 'All sites';
    $siteContextDescription = $showAllSites
        ? 'Showing pages across all sites. Choose a site to return to the normal editorial flow.'
        : 'Showing pages for '.$activeSite->name.($activeSite->domain ? ' ('.$activeSite->domain.')' : '').'.';
    $newPageUrl = $activeSite ? route('admin.pages.create', ['site' => $activeSite->id]) : route('admin.pages.create');
    $clearUrl = route('admin.pages.index', $showAllSites ? ['site' => 'all'] : ['site' => $activeSite?->id]);
    $siteSwitcher = '<form method="GET" action="'.route('admin.pages.index').'" class="wb-cluster wb-cluster-2">'
        .'<div class="wb-cluster wb-cluster-2">'
        .'<label for="pages_site_context" class="wb-text-sm wb-text-muted wb-nowrap">Site</label>'
        .'<select id="pages_site_context" name="site" class="wb-select" onchange="this.form.submit()">'
        .collect($sites)->map(function ($site) use ($filters) {
            $selected = $filters['site'] === (string) $site->id ? ' selected' : '';

            return '<option value="'.$site->id.'"'.$selected.'>'.$site->name.'</option>';
        })->implode('')
        .'<option value="all"'.($filters['site'] === 'all' ? ' selected' : '').'>All sites</option>'
        .'</select>'
        .'</div>'
        .($filters['search'] !== '' ? '<input type="hidden" name="search" value="'.e($filters['search']).'">' : '')
        .($filters['status'] !== '' ? '<input type="hidden" name="status" value="'.e($filters['status']).'">' : '')
        .($filters['sort'] !== 'created_at' ? '<input type="hidden" name="sort" value="'.e($filters['sort']).'">' : '')
        .($filters['direction'] !== 'desc' ? '<input type="hidden" name="direction" value="'.e($filters['direction']).'">' : '')
        .'</form>';
    $headerActions = '<div class="wb-cluster wb-cluster-2">'.$siteSwitcher.'<a href="'.$newPageUrl.'" class="wb-btn wb-btn-primary">New Page</a></div>';
@endphp

@extends('layouts.admin', ['title' => 'Pages', 'heading' => 'Pages'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Pages',
        'description' => null,
        'context' => '<span>'.e($siteContextDescription).'</span>',
        'count' => $pages->total(),
        'actions' => $headerActions,
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

                <div class="wb-cluster wb-cluster-2 wb-admin-filter-actions-end">
                    <input type="hidden" name="site" value="{{ $filters['site'] }}">
                    <button type="submit" class="wb-btn wb-btn-primary">Apply</button>
                    @if ($filters['search'] !== '' || $filters['status'] !== '' || $filters['sort'] !== 'created_at' || $filters['direction'] !== 'desc')
                        <a href="{{ $clearUrl }}" class="wb-btn wb-btn-secondary">Clear</a>
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
                        <div class="wb-empty-text">Adjust the filters or create your first page for {{ strtolower($siteContext) }}.</div>
                        <div class="wb-empty-action">
                            <a href="{{ $newPageUrl }}" class="wb-btn wb-btn-primary">Create Page</a>
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
                                <th>Page</th>
                                <th>Blocks</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                @php
                                    $translations = $page->translations->sortByDesc(fn ($translation) => $translation->locale?->is_default)->values();
                                    $enabledLocaleCount = (int) ($siteLocaleCounts[$page->site_id] ?? $translations->count());
                                    $missingTranslations = max($enabledLocaleCount - $translations->count(), 0);
                                    $defaultPublicUrl = $page->publicUrl();
                                @endphp
                                <tr>
                                    <td>
                                        @if ($page->status === 'published' && $defaultPublicUrl)
                                            <a
                                                href="{{ $defaultPublicUrl }}"
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
                                    <td>
                                        <div class="wb-stack wb-gap-1">
                                            <div class="wb-cluster wb-cluster-2">
                                                <strong>{{ $page->title }}</strong>
                                                @if ($showAllSites)
                                                    <span class="wb-status-pill {{ $page->site?->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $page->site?->name }}</span>
                                                    @if ($page->site?->domain)
                                                        <span class="wb-text-sm wb-text-muted">{{ $page->site->domain }}</span>
                                                    @endif
                                                @endif
                                            </div>

                                            <div class="wb-cluster wb-cluster-2 wb-text-sm wb-text-muted">
                                                @foreach ($translations as $translation)
                                                    <span class="wb-status-pill {{ $translation->locale?->is_default ? 'wb-status-info' : 'wb-status-active' }}">
                                                        {{ $translation->locale?->code }}
                                                        @if ($translation->locale?->is_default)
                                                            Default
                                                        @endif
                                                    </span>
                                                @endforeach

                                                @if ($missingTranslations > 0)
                                                    <span class="wb-text-sm wb-text-muted">Missing {{ $missingTranslations }}</span>
                                                @endif
                                            </div>

                                            <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                                @foreach ($translations->take(3) as $translation)
                                                    @php
                                                        $translationPublicUrl = $page->publicUrl($translation->locale?->code);
                                                        $translationPublicPath = $page->publicPath($translation->locale?->code);
                                                    @endphp
                                                    @if ($translationPublicUrl && $translationPublicPath)
                                                        <a href="{{ $translationPublicUrl }}" target="_blank" rel="noopener noreferrer" class="wb-link">
                                                            {{ strtoupper($translation->locale?->code ?? 'en') }} {{ $translationPublicPath }}
                                                        </a>
                                                    @else
                                                        <span class="wb-text-muted">{{ strtoupper($translation->locale?->code ?? 'en') }} Missing route</span>
                                                    @endif
                                                @endforeach
                                            </div>
                                        </div>
                                    </td>
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

    @endif
@endsection

@push('overlays')
    @foreach ($pages as $page)
        @include('admin.pages.partials.details-drawer', [
            'page' => $page,
            'drawerId' => 'pageDetailsDrawer-'.$page->id,
        ])
    @endforeach
@endpush
