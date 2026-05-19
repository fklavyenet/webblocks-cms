@php
    $publicSearchInputId = 'wb-public-search-query';
    $publicSearchTitleId = 'wb-public-search-modal-title';
    $publicSearchDescriptionId = 'wb-public-search-modal-description';
    $publicRouteResolver = app(\App\Support\Pages\PageRouteResolver::class);
    $publicSearchSite = $publicRouteResolver->currentSite(request());
    $publicSearchLocale = $publicRouteResolver->currentLocale(request());
    $publicSearchSearchPath = $publicRouteResolver->searchPath($publicSearchLocale->code, $publicSearchSite);
    $publicSearchJsonPath = $publicRouteResolver->searchJsonPath($publicSearchLocale->code, $publicSearchSite);
    $publicSearchSiteLabel = trim((string) ($publicSearchSite->display_name ?: $publicSearchSite->seo_title ?: $publicSearchSite->name));
    $publicSearchDescription = $publicSearchSiteLabel !== ''
        ? 'Search published content in '.$publicSearchSiteLabel.'.'
        : 'Search published content for this site.';
@endphp

@if ($publicSearchSearchPath && $publicSearchJsonPath)
    <div class="wb-overlay-layer wb-overlay-layer--dialog" data-wb-public-search-overlay>
        <div class="wb-overlay-backdrop" data-wb-public-search-close hidden></div>

        <div
            class="wb-modal wb-modal-lg"
            id="wb-public-search-modal"
            role="dialog"
            aria-modal="true"
            aria-labelledby="{{ $publicSearchTitleId }}"
            aria-describedby="{{ $publicSearchDescriptionId }}"
            data-wb-public-search-modal
            hidden
        >
            <div class="wb-modal-dialog">
                <div class="wb-modal-header">
                    <div class="wb-stack wb-gap-1">
                        <h2 class="wb-modal-title" id="{{ $publicSearchTitleId }}">Search</h2>
                        <p class="wb-text-sm wb-text-muted" id="{{ $publicSearchDescriptionId }}">{{ $publicSearchDescription }}</p>
                    </div>

                    <button type="button" class="wb-modal-close" data-wb-public-search-close aria-label="Close search">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="wb-modal-body wb-stack wb-gap-4">
                    <form action="{{ $publicSearchSearchPath }}" method="GET" role="search" class="wb-stack wb-gap-4" data-wb-public-search-form data-search-path="{{ $publicSearchSearchPath }}" data-search-json-path="{{ $publicSearchJsonPath }}">
                        <div class="wb-cluster wb-cluster-2 wb-items-end">
                            <div class="wb-stack wb-gap-1 wb-flex-1">
                                <label for="{{ $publicSearchInputId }}">Search query</label>
                                <input id="{{ $publicSearchInputId }}" type="search" name="q" class="wb-input" placeholder="Search published pages" autocomplete="off" data-wb-public-search-input>
                            </div>

                            <button type="submit" class="wb-btn wb-btn-primary" data-wb-public-search-submit>Search</button>
                        </div>
                    </form>

                    <div class="wb-stack wb-gap-2" data-wb-public-search-status>
                        <p class="wb-text-sm wb-text-muted" data-wb-public-search-loading hidden>Searching...</p>
                        <p class="wb-text-sm wb-text-muted" data-wb-public-search-count hidden></p>
                        <div class="wb-stack wb-gap-1 wb-text-sm wb-text-muted" data-wb-public-search-message>
                            <div>Enter a search term to find published content for this site and locale.</div>
                        </div>
                    </div>

                    <div class="wb-public-search-results" data-wb-public-search-results hidden></div>
                </div>
            </div>
        </div>
    </div>
@endif
