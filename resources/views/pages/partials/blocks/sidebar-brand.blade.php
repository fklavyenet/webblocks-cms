@php
    $site = $block->renderSite();
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $href = $block->sidebarLinkUrl()
        ?? $routeResolver->homePath($block->renderLocaleCode(), $site)
        ?? '/';
    $target = $block->sidebarLinkTarget() === '_blank';
    $title = $block->sidebarNavResolvedLabel();
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $logoUrl = $block->asset?->isImage() ? $block->asset?->url() : null;
    $hasCopy = $title !== null || $subtitle !== null;
    $accessibleLabel = $block->sidebarBrandAccessibleLabel();
    $logoAlt = $title ?? $accessibleLabel ?? '';
    $linkLabel = ! $hasCopy && $accessibleLabel ? $accessibleLabel : null;
@endphp

@if ($logoUrl !== null || $hasCopy)
    <a href="{{ $href }}" class="wb-sidebar-brand"@if ($linkLabel) aria-label="{{ $linkLabel }}"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($logoUrl !== null)
            <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}" class="wb-sidebar-brand-logo">
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
