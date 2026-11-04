@php
    $site = $block->renderSite();
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $href = $block->localizedPublicUrl($block->sidebarLinkUrl())
        ?? $routeResolver->homePath($block->renderLocaleCode(), $site)
        ?? '/';
    $target = $block->sidebarLinkTarget() === '_blank';
    $title = $block->sidebarNavResolvedLabel();
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $logoSources = app(\WebBlocks\Cms\Support\Media\PublicMediaSources::class)->forImage($block->asset, logo: true);
    $logoUrl = $logoSources['url'];
    $hasCopy = $title !== null || $subtitle !== null;
    $accessibleLabel = $block->sidebarBrandAccessibleLabel();
    $logoAlt = $title ?? $accessibleLabel ?? '';
    $linkLabel = ! $hasCopy && $accessibleLabel ? $accessibleLabel : null;
@endphp

@if ($logoUrl !== null || $hasCopy)
    <a href="{{ $href }}" class="wb-sidebar-brand"@if ($linkLabel) aria-label="{{ $linkLabel }}"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($logoUrl !== null)
            @if ($logoSources['webp'])
                <picture class="wb-flex">
                    <source type="image/webp" srcset="{{ $logoSources['webp'] }}">
            @endif
            <img src="{{ $logoUrl }}" @if ($block->asset?->width) width="{{ $block->asset->width }}" @endif @if ($block->asset?->height) height="{{ $block->asset->height }}" @endif decoding="async" alt="{{ $logoAlt }}" class="wb-sidebar-brand-logo">
            @if ($logoSources['webp'])
                </picture>
            @endif
        @endif

        @if ($hasCopy)
            <span class="wb-sidebar-brand-copy">
                @if ($title !== null)
                    <span>{{ $title }}</span>
                @endif
                @if ($subtitle !== null)
                    <span class="wb-sidebar-brand-note">{{ $subtitle }}</span>
                @endif
            </span>
        @endif
    </a>
@endif
