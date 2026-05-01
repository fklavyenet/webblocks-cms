@php
    $href = $block->sidebarLinkUrl();
    $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $icon = $block->sidebarNavItemIcon();
    $target = $block->sidebarLinkTarget() === '_blank';
    $activeMode = $block->sidebarNavItemActiveMode();
    $manualActive = $block->sidebarNavItemManualActive();
    $currentPath = '/'.ltrim(request()->path(), '/');
    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
    $hrefPath = null;

    if ($href !== null && ! preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) && ! str_starts_with($href, '#')) {
        $hrefPath = '/'.ltrim(parse_url($href, PHP_URL_PATH) ?? '', '/');
        $hrefPath = $hrefPath === '/' ? '/' : rtrim($hrefPath, '/');
    }

    $isActive = match ($activeMode) {
        'exact' => $href !== null && url()->current() === url($href),
        'current-page' => $hrefPath !== null && request()->routeIs('pages.show') && $hrefPath === $currentPath,
        'manual' => $manualActive,
        default => $hrefPath !== null && $hrefPath === $currentPath,
    };
@endphp

@if ($href !== null && $label !== null)
    <a href="{{ $href }}" class="wb-sidebar-link{{ $isActive ? ' is-active' : '' }}"@if ($isActive) aria-current="page"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($icon !== null)
            <i class="wb-icon wb-icon-{{ $icon }}" aria-hidden="true"></i>
        @endif
        <span>{{ $label }}</span>
    </a>
@endif
