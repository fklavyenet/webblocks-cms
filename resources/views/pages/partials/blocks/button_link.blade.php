@php
    $buttonUrl = $block->buttonLinkUrl();

    // Internal links follow the render locale: "/products" becomes
    // "/es/productos" on the Spanish page. Only the public renderer does
    // this — buttonLinkUrl() itself stays the stored value, which the admin
    // form and CTA synchronizer rely on.
    if ($buttonUrl !== null) {
        $buttonUrl = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class)
            ->localizedPublicUrl($buttonUrl, $block->renderLocaleCode(), $block->renderSite());
    }

    $blankTarget = $block->buttonLinkTarget() === '_blank';
@endphp

@if ($buttonUrl)
    <a href="{{ $buttonUrl }}" class="{{ $block->buttonLinkVariantClass() }}"@if ($blankTarget) target="_blank" rel="noopener noreferrer"@endif>{{ $block->title }}</a>
@endif
