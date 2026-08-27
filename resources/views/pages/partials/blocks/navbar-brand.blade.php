@php
    $site = $block->renderSite();
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $href = $block->localizedPublicUrl($block->navbarBrandUrl())
        ?? $routeResolver->homePath($block->renderLocaleCode(), $site)
        ?? '/';
    $target = $block->navbarBrandTarget() === '_blank';
    $title = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $subtitle = $block->stringValueOrNull($block->subtitle) ?? $block->translatedTextFieldValue('subtitle');
    $logoUrl = $block->asset?->isImage() ? $block->asset?->url() : null;
    $hasCopy = $title !== null || $subtitle !== null;
    $accessibleLabel = $block->navbarBrandAccessibleLabel();
    $logoAlt = $title ?? $accessibleLabel ?? '';
    $linkLabel = ! $hasCopy && $accessibleLabel ? $accessibleLabel : null;
@endphp

@if ($logoUrl !== null || $hasCopy)
    <a href="{{ $href }}" class="wb-navbar-brand" data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"@if ($linkLabel) aria-label="{{ $linkLabel }}"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($logoUrl !== null)
            <img src="{{ $logoUrl }}" alt="{{ $logoAlt }}">
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
