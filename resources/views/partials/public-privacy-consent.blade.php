@php
    $privacy = $visitorPrivacy ?? [
        'banner_enabled' => false,
        'has_choice' => false,
        'server_choice' => null,
    ];
@endphp

@if ($privacy['banner_enabled'])
    @unless ($hasFooterSlot ?? false)
        @include('pages.partials.slots.footer', [
            'slot' => [
                'chrome' => [
                    'supporting_blocks' => collect(),
                    'footer_items' => collect(),
                    'legal_items' => collect(),
                ],
            ],
            'footerClass' => 'wb-public-footer-fallback',
        ])
    @endunless

    <div class="wb-cookie-consent wb-cookie-consent-banner" data-wb-cookie-consent hidden aria-hidden="true">
        <section class="wb-card wb-cookie-consent-card" aria-label="Cookie consent">
            <div class="wb-card-header wb-cluster wb-cluster-between wb-cluster-2 wb-items-start">
                <div class="wb-stack-1">
                    <strong>We use cookies</strong>
                </div>
                <button class="wb-btn wb-btn-secondary wb-btn-icon wb-btn-sm" type="button" data-wb-cookie-consent-close aria-label="Close cookie settings">
                    <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                </button>
            </div>
            <div class="wb-card-body">
                <div class="wb-cluster wb-cluster-between wb-cluster-3 wb-items-center">
                    <p class="wb-text-sm wb-text-muted wb-m-0">
                        Necessary cookies keep the site running. Optional analytics cookies help us understand usage and improve visitor reporting.
                    </p>
                    <div class="wb-cluster wb-cluster-2">
                        <button class="wb-btn wb-btn-secondary wb-btn-sm" type="button" data-wb-cookie-consent-reject>Reject</button>
                        <button class="wb-btn wb-btn-secondary wb-btn-sm" type="button" data-wb-cookie-consent-open data-wb-target="#wb-cookie-consent-preferences">Customize</button>
                        <button class="wb-btn wb-btn-primary wb-btn-sm" type="button" data-wb-cookie-consent-accept>Accept all</button>
                    </div>
                </div>
            </div>
        </section>
    </div>
@endif
