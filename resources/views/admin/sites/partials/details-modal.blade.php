@php
    $modalId = 'siteDetailsModal';
    $modalTitleId = $modalId.'Title';
    $modalDescriptionId = $modalId.'Description';
    $aliasCount = $site->siteDomains()->where('is_primary', false)->count();
    $adminText = static fn (string $key, array $replace = []) => $adminTranslator->admin('site_form.'.$key, $adminLocaleCode, $replace);
@endphp

<div class="wb-overlay-layer wb-overlay-layer--dialog">
    <div class="wb-overlay-backdrop"></div>

    <div class="wb-modal wb-modal-lg is-open" id="{{ $modalId }}" role="dialog" aria-modal="true" aria-labelledby="{{ $modalTitleId }}" aria-describedby="{{ $modalDescriptionId }}">
        <div class="wb-modal-dialog">
            <div class="wb-modal-header">
                <div class="wb-stack wb-gap-1">
                    <h2 class="wb-modal-title" id="{{ $modalTitleId }}">{{ $adminText('site_details') }}</h2>
                    <span class="wb-text-sm wb-text-muted" id="{{ $modalDescriptionId }}">{{ $adminText('site_details_help') }}</span>
                </div>

                <a href="{{ $closeUrl }}" class="wb-modal-close" aria-label="{{ $adminText('close_site_details_modal') }}">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </a>
            </div>

            <div class="wb-modal-body wb-stack wb-gap-4">
                <div class="wb-grid wb-grid-2">
                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>{{ $adminText('site') }}</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div class="wb-table-wrap">
                                <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('site') }}">
                                    <tbody>
                                        <tr><th scope="row">{{ $adminText('name') }}</th><td>{{ $site->name }}</td></tr>
                                        <tr><th scope="row">{{ rtrim($adminText('handle_label'), ': ') }}</th><td><code>{{ $site->handle }}</code></td></tr>
                                        <tr><th scope="row">{{ rtrim($adminText('status_label'), ': ') }}</th><td>{{ $site->is_primary ? $adminText('primary') : $adminText('standard') }}</td></tr>
                                        <tr><th scope="row">{{ rtrim($adminText('pages_label'), ': ') }}</th><td>{{ $site->pages_count }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="wb-card wb-card-muted">
                        <div class="wb-card-header"><strong>{{ $adminText('domains_and_locales') }}</strong></div>
                        <div class="wb-card-body wb-stack wb-gap-2">
                            <div class="wb-table-wrap">
                                <table class="wb-table wb-table-sm wb-table-striped wb-table-break wb-cms-detail-table" aria-label="{{ $adminText('domains_and_locales') }}">
                                    <tbody>
                                        <tr><th scope="row">{{ rtrim($adminText('primary_domain_label'), ': ') }}</th><td>{{ $site->canonicalDomain() ?: $adminText('not_set') }}</td></tr>
                                        <tr><th scope="row">{{ rtrim($adminText('alias_domains_label'), ': ') }}</th><td>{{ $aliasCount }}</td></tr>
                                        <tr><th scope="row">{{ rtrim($adminText('locales_label'), ': ') }}</th><td>{{ $site->locales->pluck('code')->implode(', ') ?: $adminText('none_assigned') }}</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-modal-footer wb-flex wb-items-center wb-justify-between wb-gap-3 wb-flex-wrap">
                <div class="wb-flex wb-items-center wb-gap-3 wb-flex-wrap">
                    <a href="{{ route('admin.sites.edit', $site) }}" class="wb-btn wb-btn-primary">{{ $adminText('edit_title', ['name' => $site->name]) }}</a>
                    <a href="{{ route('admin.sites.domains.index', $site) }}" class="wb-btn wb-btn-secondary">{{ $adminText('manage_domains') }}</a>
                    <a href="{{ $closeUrl }}" class="wb-btn wb-btn-secondary">{{ $adminText('close') }}</a>
                </div>
            </div>
        </div>
    </div>
</div>
