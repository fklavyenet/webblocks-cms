@extends('layouts.admin', ['title' => 'Site Domains', 'heading' => 'Site Domains'])

@php
    $indexUrl = route('admin.sites.domains.index', $site);
    $requestedModal = old('_site_domain_modal', request('modal'));
    $requestedSiteDomainId = (int) old('_site_domain_id', request('site_domain'));
    $activeModalDomain = $requestedSiteDomainId > 0 ? $domains->firstWhere('id', $requestedSiteDomainId) : null;
    $createModalId = 'siteDomainCreateModal';
    $createModalTitleId = $createModalId.'Title';
    $createModalDescriptionId = $createModalId.'Description';
    $showCreateModal = $requestedModal === 'create-domain';
@endphp

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.index').'">Sites</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.edit', $site).'">'.e($site->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">Domains</span></li></ol></nav>',
        'title' => 'Domains',
        'description' => 'Map incoming hosts to this CMS site after DNS, SSL, and server routing are already configured in Herne Panel or by the server operator.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.sites.edit', $site).'" class="wb-btn wb-btn-secondary">Back to Site</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Host Resolution</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
            <div>Configure DNS, SSL, Nginx, Herne Panel, virtual hosts, and server routing outside CMS first.</div>
            <div>Then add the same host here so WebBlocks CMS can resolve the incoming request to this site.</div>
            <div>Unknown hosts return a not found response unless local or development fallback is explicitly enabled.</div>
            <div>The primary domain is used for canonical public URLs. Alias domains can serve this site directly or redirect to the primary domain.</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>Assigned Domains</strong>
                    <span class="wb-status-pill wb-status-info">{{ $domains->count() }}</span>
                </div>

                <a
                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'create-domain']) }}"
                    class="wb-btn wb-btn-primary"
                    aria-haspopup="dialog"
                    aria-controls="{{ $createModalId }}"
                >
                    Add Domain
                </a>
            </div>
            <div class="wb-card-body">
                @if ($domains->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">No domains assigned</div>
                        <div class="wb-empty-text">This site can still use the current local fallback behavior, but production public host resolution should use explicit site domains.</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Role</th>
                                    <th>Redirect</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($domains as $domain)
                                    <tr>
                                        <td>
                                            <div class="wb-stack wb-gap-1">
                                                <strong>{{ $domain->domain }}</strong>
                                                <span class="wb-text-sm wb-text-muted">https://{{ $domain->domain }}</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="wb-status-pill {{ $domain->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $domain->is_primary ? 'Primary' : 'Alias' }}</span>
                                        </td>
                                        <td>{{ $domain->redirect_to_primary ? 'Yes' : 'No' }}</td>
                                        <td>
                                            <span class="wb-status-pill {{ $domain->isActive() ? 'wb-status-active' : 'wb-status-danger' }}">{{ ucfirst($domain->status) }}</span>
                                        </td>
                                        <td>
                                            <div class="wb-action-group">
                                                <a
                                                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $domain->id]) }}"
                                                    class="wb-action-btn wb-action-btn-edit"
                                                    title="Manage domain settings"
                                                    aria-label="Manage domain settings"
                                                    aria-haspopup="dialog"
                                                    aria-controls="siteDomainManageModal-{{ $domain->id }}"
                                                >
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>
                                                <a
                                                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'remove-domain', 'site_domain' => $domain->id]) }}"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="Remove domain"
                                                    aria-label="Remove domain"
                                                    aria-haspopup="dialog"
                                                    aria-controls="siteDomainRemoveModal-{{ $domain->id }}"
                                                >
                                                    <i class="wb-icon wb-icon-trash" aria-hidden="true"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $showCreateModal) hidden @endif>
        <div class="wb-overlay-backdrop"></div>

        <div class="wb-modal wb-modal-lg {{ $showCreateModal ? 'is-open' : '' }}" id="{{ $createModalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $createModalTitleId }}" aria-describedby="{{ $createModalDescriptionId }}">
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="{{ $createModalTitleId }}">Add Domain</h2>
                        <span class="wb-text-sm wb-text-muted" id="{{ $createModalDescriptionId }}">Store the host only, then choose whether it should resolve as an alias or become the primary canonical domain.</span>
                    </div>

                    <a href="{{ $indexUrl }}" class="wb-modal-close" aria-label="Close add domain modal">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </a>
                </div>

                <form method="POST" action="{{ route('admin.sites.domains.store', $site) }}" class="wb-stack wb-gap-4">
                    @csrf
                    <input type="hidden" name="_site_domain_modal" value="create-domain">

                    <div class="wb-modal-body wb-stack wb-gap-4">
                        @if ($errors->any() && $showCreateModal)
                            <div class="wb-alert wb-alert-danger">
                                <div>
                                    <div class="wb-alert-title">Validation Error</div>
                                    <div>{{ $errors->first() }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="wb-stack-2 wb-field">
                            <label for="site_domain_domain">Domain</label>
                            <input id="site_domain_domain" name="domain" class="wb-input" type="text" value="{{ old('domain') }}" required>
                            <div class="wb-text-sm wb-text-muted">Store the host only, for example <code>www.example.com</code> or <code>docs.example.com</code>.</div>
                        </div>

                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack-2 wb-field">
                                <label for="site_domain_status">Status</label>
                                <select id="site_domain_status" name="status" class="wb-select">
                                    <option value="active" @selected(old('status', 'active') === 'active')>Active</option>
                                    <option value="inactive" @selected(old('status') === 'inactive')>Inactive</option>
                                </select>
                            </div>

                            <div class="wb-stack wb-gap-2 wb-field">
                                <label class="wb-nowrap"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))> <span>Make primary</span></label>
                                <label class="wb-nowrap"><input type="checkbox" name="redirect_to_primary" value="1" @checked(old('redirect_to_primary'))> <span>Redirect alias to primary</span></label>
                            </div>
                        </div>
                    </div>

                    <x-admin.form-actions
                        :cancel-url="$indexUrl"
                        submit-label="Add Domain"
                        container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                    />
                </form>
            </div>
        </div>
    </div>

    @if ($requestedModal === 'manage-domain' && $activeModalDomain)
        @include('admin.sites.domains.partials.manage-modal', [
            'site' => $site,
            'domain' => $activeModalDomain,
            'closeUrl' => $indexUrl,
        ])
    @endif

    @if ($requestedModal === 'remove-domain' && $activeModalDomain)
        @include('admin.sites.domains.partials.remove-modal', [
            'site' => $site,
            'domain' => $activeModalDomain,
            'closeUrl' => $indexUrl,
        ])
    @endif
@endsection
