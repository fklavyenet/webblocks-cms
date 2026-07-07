@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;

    $adminLocale = app(AdminLocaleResolver::class)->locale();
    $adminTranslator = app(CmsTranslator::class);
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('site_form.'.$key, $adminLocale, $replace);
    $localizedPageTitle = $adminText('domains_title');
    $indexUrl = route('admin.sites.domains.index', $site);
    $requestedModal = old('_site_domain_modal', request('modal'));
    $requestedSiteDomainId = (int) old('_site_domain_id', request('site_domain'));
    $activeModalDomain = $requestedSiteDomainId > 0 ? $domains->firstWhere('id', $requestedSiteDomainId) : null;
    $createModalId = 'siteDomainCreateModal';
    $createModalTitleId = $createModalId.'Title';
    $createModalDescriptionId = $createModalId.'Description';
    $showCreateModal = $requestedModal === 'create-domain';
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $localizedPageTitle, 'heading' => $localizedPageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="'.e($adminText('breadcrumb')).'"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.index').'">'.e($adminText('sites')).'</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.edit', $site).'">'.e($site->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">'.e($adminText('domains_title')).'</span></li></ol></nav>',
        'title' => $adminText('domains_title'),
        'description' => $adminText('domains_description'),
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.sites.edit', $site).'" class="wb-btn wb-btn-secondary">'.e($adminText('back_to_site')).'</a></div>',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>{{ $adminText('host_resolution') }}</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
            <div>{{ $adminText('host_resolution_dns_help') }}</div>
            <div>{{ $adminText('host_resolution_cms_help') }}</div>
            <div>{{ $adminText('host_resolution_unknown_help') }}</div>
            <div>{{ $adminText('host_resolution_primary_help') }}</div>
        </div>
    </div>

    <div class="wb-stack wb-gap-4">
        <div class="wb-card">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-flex-wrap">
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <strong>{{ $adminText('assigned_domains') }}</strong>
                    <span class="wb-status-pill wb-status-info">{{ $domains->count() }}</span>
                </div>

                <a
                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'create-domain']) }}"
                    class="wb-btn wb-btn-primary"
                    aria-haspopup="dialog"
                    aria-controls="{{ $createModalId }}"
                >
                    {{ $adminText('add_domain') }}
                </a>
            </div>
            <div class="wb-card-body">
                @if ($domains->isEmpty())
                    <div class="wb-empty">
                        <div class="wb-empty-title">{{ $adminText('no_domains_assigned') }}</div>
                        <div class="wb-empty-text">{{ $adminText('no_domains_help') }}</div>
                    </div>
                @else
                    <div class="wb-table-wrap">
                        <table class="wb-table wb-table-striped wb-table-hover">
                            <thead>
                                <tr>
                                    <th>{{ $adminText('domain') }}</th>
                                    <th>{{ $adminText('domain_role') }}</th>
                                    <th>{{ $adminText('domain_redirect') }}</th>
                                    <th>{{ $adminText('status') }}</th>
                                    <th>{{ $adminText('actions') }}</th>
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
                                            <span class="wb-status-pill {{ $domain->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $domain->is_primary ? $adminText('primary') : $adminText('alias') }}</span>
                                        </td>
                                        <td>{{ $domain->redirect_to_primary ? $adminText('yes') : $adminText('no') }}</td>
                                        <td>
                                            <span class="wb-status-pill {{ $domain->isActive() ? 'wb-status-active' : 'wb-status-danger' }}">{{ $adminText($domain->isActive() ? 'active' : 'inactive') }}</span>
                                        </td>
                                        <td>
                                            <div class="wb-action-group">
                                                <a
                                                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'manage-domain', 'site_domain' => $domain->id]) }}"
                                                    class="wb-action-btn wb-action-btn-edit"
                                                    title="{{ $adminText('domain_manage_settings') }}"
                                                    aria-label="{{ $adminText('domain_manage_settings') }}"
                                                    aria-haspopup="dialog"
                                                    aria-controls="siteDomainManageModal-{{ $domain->id }}"
                                                >
                                                    <i class="wb-icon wb-icon-pencil" aria-hidden="true"></i>
                                                </a>
                                                <a
                                                    href="{{ route('admin.sites.domains.index', ['site' => $site, 'modal' => 'remove-domain', 'site_domain' => $domain->id]) }}"
                                                    class="wb-action-btn wb-action-btn-delete"
                                                    title="{{ $adminText('domain_remove') }}"
                                                    aria-label="{{ $adminText('domain_remove') }}"
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
                        <h2 class="wb-modal-title" id="{{ $createModalTitleId }}">{{ $adminText('add_domain') }}</h2>
                        <span class="wb-text-sm wb-text-muted" id="{{ $createModalDescriptionId }}">{{ $adminText('add_domain_description') }}</span>
                    </div>

                    <a href="{{ $indexUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_add_domain_modal') }}">
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
                                    <div class="wb-alert-title">{{ $adminText('validation_error') }}</div>
                                    <div>{{ $errors->first() }}</div>
                                </div>
                            </div>
                        @endif

                        <div class="wb-stack-2 wb-field">
                            <label for="site_domain_domain">{{ $adminText('domain') }}</label>
                            <input id="site_domain_domain" name="domain" class="wb-input" type="text" value="{{ old('domain') }}" required>
                            <div class="wb-text-sm wb-text-muted">{{ $adminText('domain_help_example') }} <code>www.example.com</code> or <code>docs.example.com</code>.</div>
                        </div>

                        <div class="wb-grid wb-grid-2">
                            <div class="wb-stack-2 wb-field">
                                <label for="site_domain_status">{{ $adminText('status') }}</label>
                                <select id="site_domain_status" name="status" class="wb-select">
                                    <option value="active" @selected(old('status', 'active') === 'active')>{{ $adminText('active') }}</option>
                                    <option value="inactive" @selected(old('status') === 'inactive')>{{ $adminText('inactive') }}</option>
                                </select>
                            </div>

                            <div class="wb-stack wb-gap-2 wb-field">
                                <label class="wb-nowrap"><input type="checkbox" name="is_primary" value="1" @checked(old('is_primary'))> <span>{{ $adminText('make_primary') }}</span></label>
                                <label class="wb-nowrap"><input type="checkbox" name="redirect_to_primary" value="1" @checked(old('redirect_to_primary'))> <span>{{ $adminText('redirect_alias_to_primary') }}</span></label>
                            </div>
                        </div>
                    </div>

                    <x-webblocks-cms::admin.form-actions
                        :cancel-url="$indexUrl"
                        :submit-label="$adminText('add_domain')"
                        container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                    />
                </form>
            </div>
        </div>
    </div>

    @if ($requestedModal === 'manage-domain' && $activeModalDomain)
        @include('webblocks-cms::admin.sites.domains.partials.manage-modal', [
            'site' => $site,
            'domain' => $activeModalDomain,
            'closeUrl' => $indexUrl,
        ])
    @endif

    @if ($requestedModal === 'remove-domain' && $activeModalDomain)
        @include('webblocks-cms::admin.sites.domains.partials.remove-modal', [
            'site' => $site,
            'domain' => $activeModalDomain,
            'closeUrl' => $indexUrl,
        ])
    @endif
@endsection
