@php
    $trustedHtml = app(\App\Support\Blocks\TrustedHtmlOverlayExtractor::class)->extract((string) $block->content);
    $overlayHtml = $trustedHtml['overlay'] ?? null;

    if ($overlayHtml) {
        app(\App\Support\Blocks\PublicOverlayRegistry::class)->push($overlayHtml);
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
