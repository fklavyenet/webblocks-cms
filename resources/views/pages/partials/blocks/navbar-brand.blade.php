@php
    $site = $block->renderSite();
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $href = $block->localizedPublicUrl($block->navbarBrandUrl())
        ?? $routeResolver->homePath($block->renderLocaleCode(), $site)
        ?? '/';
    $target = $block->navbarBrandTarget() === '_blank';
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $logoSources = app(\WebBlocks\Cms\Support\Media\PublicMediaSources::class)->forImage($block->asset, logo: true);
    $logoUrl = $logoSources['url'];
    $hasCopy = $title !== null || $subtitle !== null;
    $accessibleLabel = $block->navbarBrandAccessibleLabel();
    $logoAlt = $title ?? $accessibleLabel ?? '';
    $linkLabel = ! $hasCopy && $accessibleLabel ? $accessibleLabel : null;
@endphp

@if ($logoUrl !== null || $hasCopy)
    <a href="{{ $href }}" class="wb-navbar-brand" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($linkLabel) aria-label="{{ $linkLabel }}"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($logoUrl !== null)
            @if ($logoSources['webp'])
                <picture class="wb-flex">
                    <source type="image/webp" srcset="{{ $logoSources['webp'] }}">
            @endif
            <img src="{{ $logoUrl }}" @if ($block->asset?->width) width="{{ $block->asset->width }}" @endif @if ($block->asset?->height) height="{{ $block->asset->height }}" @endif decoding="async" alt="{{ $logoAlt }}">
            @if ($logoSources['webp'])
                </picture>
            @endif
        @endif

        @if ($hasCopy)
            <span class="wb-navbar-identity">
                @if ($title !== null)
                    <span>{{ $title }}</span>
                @endif
                @if ($subtitle !== null)
                    <span class="wb-navbar-brand-note">{{ $subtitle }}</span>
                @endif
            </span>
        @endif
    </a>
@endif
