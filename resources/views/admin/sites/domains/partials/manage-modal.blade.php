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
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $adminText('manage_domain_title', ['domain' => $domain->domain]) }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $adminText('manage_domain_description') }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_domain_settings_modal') }}">
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
                                <div class="wb-alert-title">{{ $adminText('validation_error') }}</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                            <div>{{ $adminText('active_domain_help') }}</div>
                            <div>{{ $adminText('host_resolution_primary_help') }}</div>
                        </div>
                    </div>

                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack-2 wb-field">
                            <label for="manage_domain_status_{{ $domain->id }}">{{ $adminText('status') }}</label>
                            <select id="manage_domain_status_{{ $domain->id }}" name="status" class="wb-select">
                                <option value="active" @selected($draftStatus === 'active')>{{ $adminText('active') }}</option>
                                <option value="inactive" @selected($draftStatus === 'inactive')>{{ $adminText('inactive') }}</option>
                            </select>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>{{ $adminText('domain_role') }}</strong></div>
                                <div>{{ $domain->is_primary ? $adminText('domain_current_primary') : $adminText('domain_current_alias') }}</div>
                            </div>
                        </div>
                    </div>

                    @if ($domain->is_primary)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>{{ $adminText('primary_domain') }}</strong></div>
                                <div>{{ $adminText('primary_domain_locked_help') }}</div>
                            </div>
                        </div>
                    @else
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-3 wb-text-sm">
                                <label class="wb-check" for="manage_domain_redirect_to_primary_{{ $domain->id }}">
                                    <input id="manage_domain_redirect_to_primary_{{ $domain->id }}" type="checkbox" name="redirect_to_primary" value="1" @checked($draftRedirectToPrimary)>
                                    <span>{{ $adminText('redirect_alias_to_primary') }}</span>
                                </label>

                                <label class="wb-check" for="manage_domain_is_primary_{{ $domain->id }}">
                                    <input id="manage_domain_is_primary_{{ $domain->id }}" type="checkbox" name="is_primary" value="1" @checked($draftIsPrimary)>
                                    <span>{{ $adminText('make_primary_domain') }}</span>
                                </label>
                            </div>
                        </div>
                    @endif
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :submit-label="$adminText('save_changes')"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
