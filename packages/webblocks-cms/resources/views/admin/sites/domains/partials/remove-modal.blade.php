@php
    $modalId = 'siteDomainRemoveModal-'.$domain->id;
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $isOpen = old('_site_domain_modal', request('modal')) === 'remove-domain'
        && (int) old('_site_domain_id', request('site_domain')) === $domain->id;
    $replacementPrimary = $domain->is_primary
        ? $site->siteDomains()->whereKeyNot($domain->id)->active()->orderBy('domain')->first()
        : null;
    $removalBlockedMessage = $domain->is_primary && ! $replacementPrimary
        ? $adminText('domain_removal_blocked_help')
        : null;
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $adminText('remove_domain_title', ['domain' => $domain->domain]) }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $adminText('remove_domain_description', ['role' => $domain->is_primary ? $adminText('primary') : $adminText('alias')]) }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_remove_domain_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <form method="POST" action="{{ route('admin.sites.domains.destroy', ['site' => $site, 'domain' => $domain]) }}">
                @csrf
                @method('DELETE')

                <input type="hidden" name="_site_domain_modal" value="remove-domain">
                <input type="hidden" name="_site_domain_id" value="{{ $domain->id }}">

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
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div class="wb-cluster wb-cluster-2">
                                <span class="wb-status-pill {{ $domain->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $domain->is_primary ? $adminText('primary') : $adminText('alias') }}</span>
                                <span class="wb-status-pill {{ $domain->isActive() ? 'wb-status-active' : 'wb-status-danger' }}">{{ $adminText($domain->isActive() ? 'active' : 'inactive') }}</span>
                            </div>
                            <strong>{{ $domain->domain }}</strong>
                            <p class="wb-text-sm wb-text-muted">{{ $domain->is_primary ? $adminText('primary_domain_remove_help') : $adminText('alias_domain_remove_help') }}</p>
                        </div>
                    </div>

                    @if ($domain->is_primary && $replacementPrimary)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>{{ $adminText('primary_replacement') }}</strong></div>
                                <div>{!! $adminText('primary_replacement_help', ['domain' => '<code>'.e($replacementPrimary->domain).'</code>']) !!}</div>
                            </div>
                        </div>
                    @endif

                    @if ($removalBlockedMessage)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">{{ $adminText('removal_blocked') }}</div>
                                <div>{{ $removalBlockedMessage }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <x-webblocks-cms::admin.form-actions
                    :cancel-url="$closeUrl"
                    :show-submit="false"
                    :delete-submit="true"
                    :delete-label="$adminText('domain_remove')"
                    :delete-disabled="$removalBlockedMessage !== null"
                    container-class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap"
                />
            </form>
        </div>
    </div>
</div>
