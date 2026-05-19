@php
    $trustedHtml = app(\WebBlocks\Cms\Support\Blocks\TrustedHtmlOverlayExtractor::class)->extract((string) $block->content);
    $overlayHtml = $trustedHtml['overlay'] ?? null;
    $bodyEndHtml = $trustedHtml['body_end'] ?? [];

    if ($overlayHtml) {
        app(\WebBlocks\Cms\Support\Blocks\PublicOverlayRegistry::class)->push($overlayHtml);
    }

    foreach (is_array($bodyEndHtml) ? $bodyEndHtml : [$bodyEndHtml] as $html) {
        app(\WebBlocks\Cms\Support\Blocks\PublicBodyEndRegistry::class)->push($html);
    }
@endphp

<div>{!! $trustedHtml['content'] ?? $block->content !!}</div>
