@php
    $label = $block->sidebarNavResolvedLabel();
    $icon = $block->sidebarNavItemIcon();
    $items = $block->children->where('status', 'published')->filter(fn ($child) => $child->isSidebarNavItem())->sortBy('sort_order')->values();
    $isOpen = $block->sidebarNavGroupInitiallyOpen() || $items->contains(fn ($item) => $item->sidebarNavItemIsActive());
    $groupItemsId = 'wb-nav-group-items-'.$block->id;
@endphp

@if ($label !== null && $items->isNotEmpty())
    <div class="wb-nav-group{{ $isOpen ? ' is-open' : '' }}" data-wb-nav-group @if ($isOpen) data-wb-nav-group-open @endif>
        <button type="button" class="wb-nav-group-toggle{{ $isOpen ? ' is-active' : '' }}" aria-expanded="{{ $isOpen ? 'true' : 'false' }}" aria-controls="{{ $groupItemsId }}">
            @if ($icon !== null)
                <span class="wb-nav-group-icon"><i class="wb-icon wb-icon-{{ $icon }}" aria-hidden="true"></i></span>
            @endif
            <span class="wb-nav-group-label">{{ $label }}</span>
            <span class="wb-nav-group-arrow" aria-hidden="true"></span>
        </button>

        <div class="wb-nav-group-items" id="{{ $groupItemsId }}" @if (! $isOpen) hidden @endif>
            @foreach ($items as $item)
                @include('webblocks-cms::pages.partials.blocks.sidebar-nav-item-link', ['item' => $item, 'nested' => true])
            @endforeach
        </div>
    </div>
@endif
