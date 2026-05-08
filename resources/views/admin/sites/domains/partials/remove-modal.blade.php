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
        ? 'A site must keep at least one active primary domain once domains are assigned. Add another active domain before removing this one.'
        : null;
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog" @if (! $isOpen) hidden @endif>
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg {{ $isOpen ? 'is-open' : '' }}" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Remove Domain: {{ $domain->domain }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Confirm whether this {{ $domain->is_primary ? 'primary' : 'alias' }} domain should be removed from the site.</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close remove domain modal">
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
                                <div class="wb-alert-title">Validation Error</div>
                                <div>{{ $errors->first() }}</div>
                            </div>
                        </div>
                    @endif

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div class="wb-cluster wb-cluster-2">
                                <span class="wb-status-pill {{ $domain->is_primary ? 'wb-status-info' : 'wb-status-pending' }}">{{ $domain->is_primary ? 'Primary' : 'Alias' }}</span>
                                <span class="wb-status-pill {{ $domain->isActive() ? 'wb-status-active' : 'wb-status-danger' }}">{{ ucfirst($domain->status) }}</span>
                            </div>
                            <strong>{{ $domain->domain }}</strong>
                            <p class="wb-text-sm wb-text-muted">{{ $domain->is_primary ? 'Primary domain removal changes the site canonical host.' : 'Removing an alias host stops this site from resolving public requests on that host.' }}</p>
                        </div>
                    </div>

                    @if ($domain->is_primary && $replacementPrimary)
                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2 wb-text-sm">
                                <div><strong>Primary replacement</strong></div>
                                <div>If removed, <code>{{ $replacementPrimary->domain }}</code> becomes the new primary domain automatically.</div>
                            </div>
                        </div>
                    @endif

                    @if ($removalBlockedMessage)
                        <div class="wb-alert wb-alert-danger">
                            <div>
                                <div class="wb-alert-title">Removal Blocked</div>
                                <div>{{ $removalBlockedMessage }}</div>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Cancel</a>
                    <button type="submit" class="wb-btn wb-btn-danger" @disabled($removalBlockedMessage !== null)>Remove domain</button>
                </div>
            </form>
        </div>
    </div>
</div>
