@php
    $modalId = 'siteDetailsModal';
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $aliasCount = $site->siteDomains()->where('is_primary', false)->count();
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog">
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg is-open" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">Site Details</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">Review core site identity, domains, locales, and current status.</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="Close site details modal">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body wb-stack wb-gap-4">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Site</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div><strong>Name:</strong> {{ $site->name }}</div>
                            <div><strong>Handle:</strong> <code>{{ $site->handle }}</code></div>
                            <div><strong>Status:</strong> {{ $site->is_primary ? 'Primary' : 'Standard' }}</div>
                            <div><strong>Pages:</strong> {{ $site->pages_count }}</div>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>Domains & Locales</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div><strong>Primary domain:</strong> {{ $site->canonicalDomain() ?: 'Not set' }}</div>
                            <div><strong>Alias domains:</strong> {{ $aliasCount }}</div>
                            <div>
                                <strong>Locales:</strong>
                                {{ $site->locales->pluck('code')->implode(', ') ?: 'None assigned' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">Close</a>
                <div class="wb-cluster wb-cluster-2 wb-flex-wrap">
                    <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-btn wb-btn-secondary">Manage Domains</a>
                    <a href="{{ route('admin.sites.edit', $site) }}" class="wb-btn wb-btn-primary">Edit Site</a>
                </div>
            </div>
        </div>
    </div>
</div>
