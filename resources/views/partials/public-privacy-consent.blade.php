@php
    $privacy = $visitorPrivacy ?? [
        'banner_enabled' => false,
        'show_banner' => false,
        'has_choice' => false,
        'reopen_url' => null,
        'show_settings' => false,
        'panel_open' => false,
        'redirect_to' => '/',
    ];
@endphp

@if ($privacy['banner_enabled'])
    @unless ($hasFooterSlot ?? false)
        <footer class="wb-section wb-section-muted wb-public-footer wb-public-footer-fallback">
            <div class="wb-container wb-container-lg">
                <div class="wb-stack wb-gap-2">
                    <strong>Legal</strong>
                    <button
                        type="button"
                        class="wb-link wb-text-sm wb-text-start wb-footer-cookie-settings-link"
                        data-wb-cookie-settings-open
                        data-wb-target="#wb-cookie-settings-panel"
                        aria-expanded="{{ $privacy['panel_open'] ? 'true' : 'false' }}"
                        aria-controls="wb-cookie-settings-panel"
                    >
                        Cookie settings
                    </button>
                    <div class="wb-text-sm wb-text-muted">&copy; {{ now()->year }} {{ config('app.name') }}.</div>
                </div>
            </div>
        </footer>
    @endunless

    <div class="wb-cookie-settings-shell {{ $privacy['panel_open'] ? 'is-open' : '' }}" data-wb-cookie-settings-shell>
        <div class="wb-cookie-settings-panel wb-card wb-card-accent" id="wb-cookie-settings-panel" role="dialog" aria-modal="false" aria-labelledby="wb-cookie-settings-title" @if (! $privacy['panel_open']) hidden @endif>
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-cookie-settings-header wb-cluster wb-cluster-between wb-cluster-2">
                    <div class="wb-stack wb-gap-1">
                        <strong id="wb-cookie-settings-title">Cookie settings</strong>
                        <div class="wb-text-sm wb-text-muted">
                            Choose whether optional analytics can be used for richer visitor reports. Necessary cookies remain active for normal site and security behavior.
                        </div>
                    </div>

                    <button type="button" class="wb-cookie-settings-close wb-btn wb-btn-secondary wb-btn-sm" data-wb-cookie-settings-close aria-label="Close cookie settings">
                        <i class="wb-icon wb-icon-x" aria-hidden="true"></i>
                    </button>
                </div>

                <div class="wb-stack wb-gap-2 wb-text-sm">
                    <div><strong>Necessary:</strong> always active</div>
                    <div><strong>Analytics:</strong> optional, enables richer visitor reports such as sessions, unique visitors, referrers, UTM campaigns, browser/device summaries</div>
                </div>

                <div class="wb-cluster wb-cluster-2 wb-cluster-between wb-flex-wrap">
                    <div class="wb-text-sm wb-text-muted">Accept or decline optional analytics in one click. Closing this panel does not save a choice.</div>

                    <div class="wb-cluster wb-cluster-2">
                        <form method="POST" action="{{ route('public.privacy-consent.decline') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ $privacy['redirect_to'] }}">
                            <button type="submit" class="wb-btn wb-btn-secondary">Decline</button>
                        </form>

                        <form method="POST" action="{{ route('public.privacy-consent.accept') }}">
                            @csrf
                            <input type="hidden" name="redirect_to" value="{{ $privacy['redirect_to'] }}">
                            <button type="submit" class="wb-btn wb-btn-primary">Accept</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif
