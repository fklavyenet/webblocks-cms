@php
    $privacy = $visitorPrivacy ?? [
        'banner_enabled' => false,
        'show_banner' => false,
        'has_choice' => false,
        'reopen_url' => null,
        'show_settings' => false,
        'redirect_to' => '/',
    ];
    $showPanel = $privacy['show_banner'] || $privacy['show_settings'];
@endphp

@if ($privacy['banner_enabled'])
    <div class="wb-card wb-card-muted">
        <div class="wb-card-body wb-cluster wb-cluster-between wb-cluster-2">
            <div class="wb-text-sm wb-text-muted">Necessary cookies always stay active. Optional analytics can be changed at any time.</div>
            <a href="{{ $privacy['reopen_url'] }}#wb-privacy-consent" class="wb-link wb-text-sm">Privacy settings</a>
        </div>
    </div>

    @if ($showPanel)
        <div id="wb-privacy-consent" class="wb-card wb-card-accent">
            <div class="wb-card-body wb-stack wb-gap-3">
                <div class="wb-stack wb-gap-1">
                    <strong>Privacy settings</strong>
                    <div class="wb-text-sm wb-text-muted">
                        Accept optional analytics to enable richer visitor reports. Decline keeps privacy-safe anonymous page view counts only.
                    </div>
                </div>

                <div class="wb-stack wb-gap-1 wb-text-sm">
                    <div><strong>Necessary:</strong> always active</div>
                    <div><strong>Analytics:</strong> optional, enables richer visitor reports such as sessions, unique visitors, referrers, UTM campaigns, browser/device summaries</div>
                </div>

                <div class="wb-cluster wb-cluster-2">
                    <form method="POST" action="{{ route('public.privacy-consent.accept') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $privacy['redirect_to'] }}">
                        <button type="submit" class="wb-btn wb-btn-primary">Accept</button>
                    </form>

                    <form method="POST" action="{{ route('public.privacy-consent.decline') }}">
                        @csrf
                        <input type="hidden" name="redirect_to" value="{{ $privacy['redirect_to'] }}">
                        <button type="submit" class="wb-btn wb-btn-secondary">Decline</button>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endif
