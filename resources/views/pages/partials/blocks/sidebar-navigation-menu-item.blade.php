@php
    $label = trim((string) $item->resolvedTitle());
    $children = $item->children->filter(fn ($child) => $child->isVisible())->values();
    $isGroup = $item->link_type === \App\Models\NavigationItem::LINK_GROUP && $children->isNotEmpty();
    $isActive = $isNavigationItemActive($item);
    $href = $item->resolvedUrl();
    $target = $item->target === '_blank';
    $icon = $showIcons ? $item->sidebarIcon() : null;
@endphp

@if ($label !== '')
    @if ($isGroup)
        <div class="wb-nav-group{{ $isActive ? ' is-open' : '' }}" data-wb-nav-group>
            <button type="button" class="wb-nav-group-toggle{{ $isActive ? ' is-active' : '' }}" aria-expanded="{{ $isActive ? 'true' : 'false' }}" data-wb-nav-group-toggle>
                @if ($icon !== null)
                    <span class="wb-nav-group-icon"><i class="wb-icon wb-icon-{{ $icon }}" aria-hidden="true"></i></span>
                @endif
                <span class="wb-nav-group-label">{{ $label }}</span>
                <span class="wb-nav-group-arrow" aria-hidden="true"></span>
            </button>

            <div class="wb-nav-group-items">
                @foreach ($children as $child)
                    @include('pages.partials.blocks.sidebar-navigation-menu-item', [
                        'item' => $child,
                        'nested' => true,
                        'showIcons' => $showIcons,
                        'isNavigationItemActive' => $isNavigationItemActive,
                    ])
                @endforeach
            </div>
        </div>
    @elseif ($href !== null)
        <a href="{{ $href }}" class="{{ $nested ? 'wb-nav-group-item' : 'wb-sidebar-link' }}{{ $isActive ? ' is-active' : '' }}"@if ($isActive) aria-current="page"@endif @if ($target) target="_blank" rel="noopener noreferrer"@endif>
            @if ($icon !== null)
                <i class="wb-icon wb-icon-{{ $icon }} wb-sidebar-icon" aria-hidden="true"></i>
            @endif
            <span>{{ $label }}</span>
        </a>
    @endif
@endif
