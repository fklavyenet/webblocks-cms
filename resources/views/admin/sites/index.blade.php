@extends('layouts.admin', ['title' => 'Sites', 'heading' => 'Sites'])

@section('content')
    @include('admin.partials.page-header', [
        'title' => 'Sites',
        'description' => 'Manage the small multisite foundation and the locales available on each site.',
        'count' => $sites->total(),
    ])

    @include('admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Sites</strong>
                <span class="wb-status-pill wb-status-info">{{ $sites->total() }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.sites.clone') }}" class="wb-btn wb-btn-secondary">Clone Site</a>
                <a href="{{ route('admin.sites.promote') }}" class="wb-btn wb-btn-secondary">Promote</a>
                <a href="{{ route('admin.sites.create') }}" class="wb-btn wb-btn-primary">Add Site</a>
            </div>
        </div>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Handle</th>
                            <th>Domains</th>
                            <th>Locales</th>
                            <th>Pages</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($sites as $site)
                            @php($deleteReport = $siteDeleteReports[$site->id] ?? null)
                            <tr data-site-id="{{ $site->id }}">
                                <td><strong>{{ $site->name }}</strong></td>
                                <td><code>{{ $site->handle }}</code></td>
                                <td>
                                    <div class="wb-stack wb-gap-1">
                                        <span>{{ $site->canonicalDomain() ?: 'Not set' }}</span>
                                        @if ($site->canonicalDomain())
                                            <span class="wb-text-sm wb-text-muted">https://{{ $site->canonicalDomain() }}</span>
                                        @endif
                                        @php($aliasCount = $site->siteDomains()->where('is_primary', false)->count())
                                        @if ($aliasCount > 0)
                                            <span class="wb-text-sm wb-text-muted">+{{ $aliasCount }} alias{{ $aliasCount === 1 ? '' : 'es' }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <div class="wb-cluster wb-cluster-2 wb-text-sm">
                                        @foreach ($site->locales as $locale)
                                            <span class="wb-status-pill {{ $locale->is_default ? 'wb-status-info' : 'wb-status-active' }}">{{ $locale->code }}</span>
                                        @endforeach
                                    </div>
                                </td>
                                <td data-column="pages">{{ $site->pages_count }}</td>
                                <td>
                                    <span class="wb-status-pill {{ $site->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $site->is_primary ? 'Primary' : 'Standard' }}</span>
                                </td>
                                <td>
                                    <div class="wb-action-group">
                                        <a href="{{ route('admin.pages.index', ['site' => $site->id]) }}" class="wb-action-btn wb-action-btn-view" title="Open site pages" aria-label="Open site pages">
                                            <i class="wb-icon wb-icon-file-text" aria-hidden="true"></i>
                                        </a>
                                        <a href="{{ route('admin.sites.edit', $site) }}" class="wb-action-btn wb-action-btn-edit" title="Edit site" aria-label="Edit site">
                                            <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                        </a>
                                        <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-action-btn" title="Manage domains" aria-label="Manage domains">
                                            <i class="wb-icon wb-icon-globe" aria-hidden="true"></i>
                                        </a>
                                        <a href="{{ route('admin.sites.clone.prefill', $site) }}" class="wb-action-btn" title="Clone site" aria-label="Clone site">
                                            <i class="wb-icon wb-icon-copy" aria-hidden="true"></i>
                                        </a>
                                        @if ($deleteReport?->canDelete)
                                            <a href="{{ route('admin.sites.delete', $site) }}" class="wb-action-btn wb-action-btn-delete" title="Delete site" aria-label="Delete site">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </a>
                                        @else
                                            <a href="{{ route('admin.sites.delete', $site) }}" class="wb-action-btn wb-action-btn-delete" title="Delete site" aria-label="Delete site" aria-disabled="true">
                                                <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                            </a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('admin.partials.pagination', ['paginator' => $sites])
    </div>
@endsection
