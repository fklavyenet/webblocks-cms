@php
    $trustedHtml = app(\App\Support\Blocks\TrustedHtmlOverlayExtractor::class)->extract((string) $block->content);
    $overlayHtml = $trustedHtml['overlay'] ?? null;
    $bodyEndHtml = $trustedHtml['body_end'] ?? [];

    if ($overlayHtml) {
        app(\App\Support\Blocks\PublicOverlayRegistry::class)->push($overlayHtml);
    }

    foreach (is_array($bodyEndHtml) ? $bodyEndHtml : [$bodyEndHtml] as $html) {
        app(\App\Support\Blocks\PublicBodyEndRegistry::class)->push($html);
    }
@endphp

<div>{!! $trustedHtml['content'] ?? $block->content !!}</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
