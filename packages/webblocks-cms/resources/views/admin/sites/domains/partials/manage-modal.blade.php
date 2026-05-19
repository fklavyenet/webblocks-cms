@php
    $modalId = 'siteDomainManageModal-'.$domain->id;
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isOpen = old('_site_domain_modal', request('modal')) === 'manage-domain'
        && (int) old('_site_domain_id', request('site_domain')) === $domain->id;
    $draftStatus = $isOpen ? old('status', $domain->status) : $domain->status;
    $draftRedirectToPrimary = $isOpen ? old('redirect_to_primary', $domain->redirect_to_primary) : $domain->redirect_to_primary;
    $draftIsPrimary = $domain->is_primary || ($isOpen && old('is_primary'));
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Manage Domain: {{ $domain->domain }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Update resolution status, alias redirect behavior, and canonical primary-domain selection for this host.</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close domain settings modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.sites.domains.update', ['site' => $site, 'domain' => $domain]) }}" class="wb-stack wb-gap-4">
                @csrf
                @method('PUT')

                <input type="hidden" name="_site_domain_modal" value="manage-domain">
                <input type="hidden" name="_site_domain_id" value="{{ $domain->id }}">
                <input type="hidden" name="domain" value="{{ $domain->domain }}">
                @if (! $draftIsPrimary)
                    <input type="hidden" name="is_primary" value="0">
                @endif

                <div class="wb-modal-body wb-stack wb-gap-4">
                    @if ($errors->any() && $isOpen)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Validation Error</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div>Active domains participate in host resolution. Inactive domains do not resolve public requests.</div>
                            <div>Primary domain is used for canonical public URLs. Alias domains can serve the site directly or redirect to the primary domain.</div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack-2 wb-field">
                            <label for="manage_domain_status_{{ $domain->id }}">Status</label>
                            <select id="manage_domain_status_{{ $domain->id }}" name="status" class="wb-select">
                                <option value="active" @selected($draftStatus === 'active')>Active</option>
                                <option value="inactive" @selected($draftStatus === 'inactive')>Inactive</option>
                            </select>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>Role</strong></div>
                                <div>{{ $domain->is_primary ? 'This host is currently the primary canonical domain for the site.' : 'This host is currently an alias domain for the site.' }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($domain->is_primary)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>Primary domain</strong></div>
                                <div>This domain already owns canonical public URLs and remains active while it stays primary.</div>
                            </div>
                        </div>
                    @else
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3 wb-text-sm">
                                <label class="wb-checkbox" for="manage_domain_redirect_to_primary_{{ $domain->id }}">
                                    <input id="manage_domain_redirect_to_primary_{{ $domain->id }}" type="checkbox" name="redirect_to_primary" value="1" @checked($draftRedirectToPrimary)>
                                    <span>Redirect alias to primary</span>
                                </label>

                                <label class="wb-checkbox" for="manage_domain_is_primary_{{ $domain->id }}">
                                    <input id="manage_domain_is_primary_{{ $domain->id }}" type="checkbox" name="is_primary" value="1" @checked($draftIsPrimary)>
                                    <span>Make primary domain</span>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    submit-label="Save changes"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
