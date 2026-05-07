@extends('layouts.admin', ['title' => 'Site Domains', 'heading' => 'Site Domains'])

@section('content')
    @include('admin.partials.page-header', [
        'breadcrumb' => '<nav class="wb-breadcrumb" aria-label="Breadcrumb"><ol class="wb-breadcrumb-list"><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.index').'">Sites</a></li><li class="wb-breadcrumb-item"><a class="wb-breadcrumb-link" href="'.route('admin.sites.edit', $site).'">'.e($site->name).'</a></li><li class="wb-breadcrumb-item"><span class="wb-breadcrumb-current" aria-current="page">Domains</span></li></ol></nav>',
        'title' => 'Domains',
        'description' => 'Assign one primary public domain plus optional aliases for this site. DNS, Nginx, SSL, and server routing are managed outside CMS by Herne Panel or the server operator.',
        'actions' => '<div class="wb-cluster wb-cluster-2"><a href="'.route('admin.sites.edit', $site).'" class="wb-btn wb-btn-secondary">Back to Site</a></div>',
    ])

    @include('admin.partials.flash')

    <div class="wb-card wb-card-muted">
        <div class="wb-card-header"><strong>Host Resolution</strong></div>
        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
            <div>The CMS resolves the incoming host to this site through active site domains.</div>
            <div>Unknown hosts return a not found response unless local or development fallback is explicitly enabled.</div>
            <div>The primary domain is used for canonical public URLs. Alias domains can serve the site directly or redirect to the primary domain.</div>
        </div>
    </div>

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header"><strong>Add Domain</strong></div>
            <div class="wb-card-body">
                <form method="POST" action="{{ route('admin.sites.domains.store', $site) }}" class="wb-stack wb-gap-3">
                    @csrf

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

                    <div>
                        <button type="submit" class="wb-btn wb-btn-primary">Add Domain</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header"><strong>Assigned Domains</strong></div>
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
                                            <div class="wb-stack wb-gap-2">
                                                <form method="POST" action="{{ route('admin.sites.domains.update', ['site' => $site, 'domain' => $domain]) }}" class="wb-cluster wb-cluster-2 wb-items-end wb-flex-wrap">
                                                    @csrf
                                                    @method('PUT')
                                                    <input type="hidden" name="domain" value="{{ $domain->domain }}">
                                                    <input type="hidden" name="is_primary" value="{{ $domain->is_primary ? 1 : 0 }}">

                                                    <div class="wb-stack-2 wb-field">
                                                        <label for="domain_status_{{ $domain->id }}">Status</label>
                                                        <select id="domain_status_{{ $domain->id }}" name="status" class="wb-select wb-w-auto">
                                                            <option value="active" @selected($domain->status === 'active')>Active</option>
                                                            <option value="inactive" @selected($domain->status === 'inactive')>Inactive</option>
                                                        </select>
                                                    </div>

                                                    <label class="wb-nowrap"><input type="checkbox" name="redirect_to_primary" value="1" @checked($domain->redirect_to_primary)> <span>Redirect</span></label>
                                                    <button type="submit" class="wb-btn wb-btn-secondary">Save</button>
                                                </form>

                                                <div class="wb-cluster wb-cluster-2">
                                                    @if (! $domain->is_primary)
                                                        <form method="POST" action="{{ route('admin.sites.domains.primary', ['site' => $site, 'domain' => $domain]) }}">
                                                            @csrf
                                                            <button type="submit" class="wb-btn wb-btn-secondary">Make Primary</button>
                                                        </form>
                                                    @endif

                                                    @if (! $domain->is_primary)
                                                        <form method="POST" action="{{ route('admin.sites.domains.destroy', ['site' => $site, 'domain' => $domain]) }}">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="wb-btn wb-btn-danger">Remove</button>
                                                        </form>
                                                    @endif
                                                </div>
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
@endsection
