@php
    $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title');
    $icon = $block->sidebarNavItemIcon();
    $items = $block->children->where('status', 'published')->filter(fn ($child) => $child->isSidebarNavItem())->sortBy('sort_order')->values();
    $isOpen = $block->sidebarNavGroupInitiallyOpen() || $items->contains(function ($item) {
        $href = $item->sidebarLinkUrl();

        if ($href === null || preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $href) || str_starts_with($href, '#')) {
            return false;
        }

        $hrefPath = '/'.ltrim(parse_url($href, PHP_URL_PATH) ?? '', '/');
        $hrefPath = $hrefPath === '/' ? '/' : rtrim($hrefPath, '/');
        $currentPath = '/'.ltrim(request()->path(), '/');
        $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');

        return $hrefPath === $currentPath;
    });
@endphp

@if ($label !== null && $items->isNotEmpty())
    <div class="wb-nav-group{{ $isOpen ? ' is-open' : '' }}" data-wb-nav-group>
        <button type="button" class="wb-nav-group-toggle{{ $isOpen ? ' is-active' : '' }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" data-wb-nav-group-toggle>
            @if ($icon !== null)
                <span class="wb-nav-group-icon"><i class="wb-icon wb-icon-{{ $icon }}" aria-hidden="true"></i></span>
            @endif
            <span class="wb-nav-group-label">{{ $label }}</span>
            <span class="wb-nav-group-arrow" aria-hidden="true"></span>
        </button>

        <div class="wb-nav-group-items">
            @foreach ($items as $item)
                @php
                    $href = $item->sidebarLinkUrl();
                    $itemLabel = $item->stringValueOrNull($item->title) ?? $item->translatedTextFieldValue('title');
                    $currentPath = '/'.ltrim(request()->path(), '/');
                    $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
                    $hrefPath = $href !== null ? '/'.ltrim(parse_url($href, PHP_URL_PATH) ?? '', '/') : null;
                    $hrefPath = $hrefPath === '/' ? '/' : rtrim((string) $hrefPath, '/');
                    $itemActive = $href !== null && $hrefPath === $currentPath;
                    $target = $item->sidebarLinkTarget() === '_blank';
                @endphp
                @if ($href !== null && $itemLabel !== null)
                    <a href="{{ $href }}" class="wb-nav-group-item{{ $itemActive ? ' is-active' : '' }}"@if ($itemActive) aria-current="page"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
                        {{ $itemLabel }}
                    </a>
                @endif
            @endforeach
        </div>
    </div>
@endif
