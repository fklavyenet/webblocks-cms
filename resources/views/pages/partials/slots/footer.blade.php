@php
    $chrome = $slot['chrome'];
    $supportingBlocks = $chrome['supporting_blocks'] ?? collect();
    $footerItems = $chrome['footer_items'] ?? collect();
    $legalItems = $chrome['legal_items'] ?? collect();
    $footerClass = trim('wb-section wb-section-muted wb-public-footer '.($footerClass ?? ''));

    $renderFooterList = function ($items) {
        $html = '';

        foreach ($items as $item) {
            $url = $item->resolvedUrl();
            $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
            $html .= '<li>';
            $html .= $url
                ? '<a href="'.e($url).'" class="wb-link"'.$target.'>'.e($item->resolvedTitle()).'</a>'
                : '<span>'.e($item->resolvedTitle()).'</span>';
            $html .= '</li>';
        }

        return $html;
    };
@endphp

<footer class="{{ $footerClass }}">
    <div class="wb-container wb-container-lg">
        <div class="wb-grid wb-grid-3 wb-gap-6">
            <div class="wb-stack wb-gap-3">
                @foreach ($supportingBlocks as $block)
                    @include('pages.partials.block', ['block' => $block])
                @endforeach
            </div>

            <div class="wb-stack wb-gap-2">
                <strong>Explore</strong>
                <ul class="wb-stack wb-gap-1">{!! $renderFooterList($footerItems) !!}</ul>
            </div>

            <div class="wb-stack wb-gap-2">
                <strong>Legal</strong>
                <ul class="wb-stack wb-gap-1">{!! $renderFooterList($legalItems) !!}</ul>
                @if (($visitorPrivacy['banner_enabled'] ?? false) === true)
                    <button
                        class="wb-btn wb-btn-ghost wb-btn-sm wb-footer-cookie-settings-link"
                        type="button"
                        data-wb-cookie-consent-open
                        data-wb-target="#wb-cookie-consent-preferences"
                    >
                        Cookie settings
                    </button>
                @endif
                <div class="wb-text-sm wb-text-muted">&copy; {{ now()->year }} {{ config('app.name') }}.</div>
            </div>
        </div>
    </div>
</footer>
