@extends('webblocks-cms::layouts.admin', ['title' => 'Sites', 'heading' => 'Sites'])

@php
    $siteExportUi = $siteExportUi ?? ['requestedModal' => '', 'selectedSite' => null, 'closeUrl' => route('admin.sites.index')];
    $showExportModal = $canExportSites && $siteExportUi['requestedModal'] === 'export-site' && $siteExportUi['selectedSite'];
    $siteDetailsUi = $siteDetailsUi ?? ['requestedModal' => '', 'selectedSite' => null, 'closeUrl' => route('admin.sites.index')];
    $showDetailsModal = $siteDetailsUi['requestedModal'] === 'site-details' && $siteDetailsUi['selectedSite'];
@endphp

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Sites',
        'description' => 'Manage the small multisite foundation and the locales available on each site.',
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>Sites</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $sites->total() }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
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
                            <th>Actions</th>
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
                                    <div class="wb-dropdown wb-dropdown-end">
                                        <button
                                            class="wb-btn wb-btn-secondary"
                                            type="button"
                                            data-wb-toggle="dropdown"
                                            data-wb-target="#site-actions-{{ $site->id }}"
                                            aria-expanded="false"
                                            title="Manage {{ $site->name }}"
                                            aria-label="Manage {{ $site->name }}"
                                        >
                                            Manage
                                        </button>

                                        <div class="wb-dropdown-menu" id="site-actions-{{ $site->id }}">
                                            <a href="{{ route('admin.sites.index', ['modal' => 'site-details', 'details_site' => $site->id]) }}" class="wb-dropdown-item" aria-haspopup="dialog" aria-controls="siteDetailsModal">View details</a>
                                            <a href="{{ route('admin.sites.edit', $site) }}" class="wb-dropdown-item">Edit site</a>
                                            <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-dropdown-item">Manage domains</a>
                                            <a href="{{ route('admin.sites.clone.prefill', $site) }}" class="wb-dropdown-item">Clone site</a>
                                            @if ($canExportSites)
                                                <a href="{{ route('admin.sites.index', ['modal' => 'export-site', 'export_site' => $site->id]) }}" class="wb-dropdown-item" aria-haspopup="dialog" aria-controls="siteIndexExportModal">Export site</a>
                                                <a href="{{ route('admin.sites.promote', ['target_site_id' => $site->id]) }}" class="wb-dropdown-item">Promote to this site</a>
                                            @endif
                                            <hr class="wb-dropdown-divider">
                                            <a href="{{ route('admin.sites.delete', $site) }}" class="wb-dropdown-item wb-text-danger" @if (! $deleteReport?->canDelete) aria-disabled="true" @endif>Delete site</a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        @include('webblocks-cms::admin.partials.pagination', ['paginator' => $sites])
    </div>

    @if ($showDetailsModal)
        @include('webblocks-cms::admin.sites.partials.details-modal', [
            'site' => $siteDetailsUi['selectedSite'],
            'closeUrl' => $siteDetailsUi['closeUrl'],
        ])
    @endif

    @if ($canExportSites)
        @include('admin.site-transfers.partials.export-modal', [
            'modalId' => 'siteIndexExportModal',
            'modalTitle' => 'Export Site',
            'modalDescription' => 'Create a portable site export package for the selected site without leaving the Sites list.',
            'selectedSite' => $siteExportUi['selectedSite'],
            'show' => $showExportModal,
            'closeUrl' => $siteExportUi['closeUrl'],
            'formAction' => $siteExportUi['selectedSite'] ? route('admin.sites.export', $siteExportUi['selectedSite']) : route('admin.sites.index'),
            'modalKey' => 'export-site',
        ])
    @endif
@endsection
