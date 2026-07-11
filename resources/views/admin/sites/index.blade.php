@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocaleCode = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin($key, $adminLocaleCode, $replace);
    $siteExportUi = $siteExportUi ?? ['requestedModal' => '', 'selectedSite' => null, 'closeUrl' => route('admin.sites.index')];
    $showExportModal = $canExportSites && $siteExportUi['requestedModal'] === 'export-site' && $siteExportUi['selectedSite'];
    $siteDetailsUi = $siteDetailsUi ?? ['requestedModal' => '', 'selectedSite' => null, 'closeUrl' => route('admin.sites.index')];
    $showDetailsModal = $siteDetailsUi['requestedModal'] === 'site-details' && $siteDetailsUi['selectedSite'];
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $adminText('sites.title'), 'heading' => $adminText('sites.title')])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $adminText('sites.title'),
        'description' => $adminText('sites.description'),
        'count' => $totalCount,
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
            <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                <strong>{{ $adminText('sites.title') }}</strong>
                <span class="wb-status-pill wb-status-info" data-admin-list-count>{{ $sites->total() }}</span>
            </div>

            <div class="wb-cluster wb-cluster-2">
                <a href="{{ route('admin.sites.create') }}" class="wb-btn wb-btn-primary">{{ $adminText('sites.add_site') }}</a>
            </div>
        </div>

        <div class="wb-card-body">
            <div class="wb-table-wrap">
                <table class="wb-table wb-table-striped wb-table-hover">
                    <thead>
                        <tr>
                            <th>{{ $adminText('sites.columns.name') }}</th>
                            <th>{{ $adminText('sites.columns.handle') }}</th>
                            <th>{{ $adminText('sites.columns.domains') }}</th>
                            <th>{{ $adminText('sites.columns.locales') }}</th>
                            <th>{{ $adminText('sites.columns.pages') }}</th>
                            <th>{{ $adminText('sites.columns.status') }}</th>
                            <th>{{ $adminText('sites.columns.actions') }}</th>
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
                                        <span>{{ $site->canonicalDomain() ?: $adminText('common.not_set') }}</span>
                                        @if ($site->canonicalDomain())
                                            <span class="wb-text-sm wb-text-muted">https://{{ $site->canonicalDomain() }}</span>
                                        @endif
                                        @php($aliasCount = $site->siteDomains()->where('is_primary', false)->count())
                                        @if ($aliasCount > 0)
                                            <span class="wb-text-sm wb-text-muted">{{ $adminText('sites.alias_count', ['count' => $aliasCount]) }}</span>
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
                                    <span class="wb-status-pill {{ $site->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $site->is_primary ? $adminText('sites.primary') : $adminText('sites.standard') }}</span>
                                </td>
                                <td>
                                    <div class="wb-dropdown wb-dropdown-end">
                                        <button
                                            class="wb-btn wb-btn-secondary"
                                            type="button"
                                            data-wb-toggle="dropdown"
                                            data-wb-target="#site-actions-{{ $site->id }}"
                                            aria-expanded="false"
                                            title="{{ $adminText('sites.manage_named', ['name' => $site->name]) }}"
                                            aria-label="{{ $adminText('sites.manage_named', ['name' => $site->name]) }}"
                                        >
                                            {{ $adminText('common.manage') }}
                                        </button>

                                        <div class="wb-dropdown-menu" id="site-actions-{{ $site->id }}">
                                            <a href="{{ route('admin.sites.index', ['modal' => 'site-details', 'details_site' => $site->id]) }}" class="wb-dropdown-item" aria-haspopup="dialog" aria-controls="siteDetailsModal">{{ $adminText('sites.view_details') }}</a>
                                            <a href="{{ route('admin.sites.edit', $site) }}" class="wb-dropdown-item">{{ $adminText('sites.edit_site') }}</a>
                                            <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-dropdown-item">{{ $adminText('sites.manage_domains') }}</a>
                                            <a href="{{ route('admin.sites.clone.prefill', $site) }}" class="wb-dropdown-item">{{ $adminText('sites.clone_site') }}</a>
                                            @if ($canExportSites)
                                                <a href="{{ route('admin.sites.index', ['modal' => 'export-site', 'export_site' => $site->id]) }}" class="wb-dropdown-item" aria-haspopup="dialog" aria-controls="siteIndexExportModal">{{ $adminText('sites.export_site') }}</a>
                                                <a href="{{ route('admin.sites.promote', ['target_site_id' => $site->id]) }}" class="wb-dropdown-item">{{ $adminText('sites.promote_to_site') }}</a>
                                            @endif
                                            <hr class="wb-dropdown-divider">
                                            <a href="{{ route('admin.sites.delete', $site) }}" class="wb-dropdown-item wb-text-danger" @if (! $deleteReport?->canDelete) aria-disabled="true" @endif>{{ $adminText('sites.delete_site') }}</a>
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
        @include('webblocks-cms::admin.site-transfers.partials.export-modal', [
            'modalId' => 'siteIndexExportModal',
            'modalTitle' => $adminText('sites.export_modal_title'),
            'modalDescription' => $adminText('sites.export_modal_description'),
            'selectedSite' => $siteExportUi['selectedSite'],
            'show' => $showExportModal,
            'closeUrl' => $siteExportUi['closeUrl'],
            'formAction' => $siteExportUi['selectedSite'] ? route('admin.sites.export', $siteExportUi['selectedSite']) : route('admin.sites.index'),
            'modalKey' => 'export-site',
        ])
    @endif
@endsection
