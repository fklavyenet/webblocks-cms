@php
    $href = $item->sidebarLinkUrl();
    $label = $item->sidebarNavResolvedLabel();
    $icon = $item->sidebarNavItemIcon();
    $target = $item->sidebarLinkTarget() === '_blank';
    $isActive = $item->sidebarNavItemIsActive();
    $linkClass = $nested ? 'wb-nav-group-item' : 'wb-sidebar-link';
@endphp

@if ($href !== null && $label !== null)
    <a href="{{ $href }}" class="{{ $linkClass }}{{ $isActive ? ' is-active' : '' }}"@if ($isActive) aria-current="page"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
        @if ($icon !== null)
            <i class="wb-icon wb-icon-{{ $icon }} wb-sidebar-icon" aria-hidden="true"></i>
        @endif
        <span>{{ $label }}</span>
    </a>
@endif
