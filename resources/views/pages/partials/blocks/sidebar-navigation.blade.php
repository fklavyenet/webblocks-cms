@php
  $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title') ?? 'Documentation navigation';
  $menuKey = $block->sidebarNavigationMenuKey();
  $manualItems = $block->children
    ->where('status', 'published')
    ->filter(fn ($child) => $child->isSidebarNavItem() || $child->isSidebarNavGroup())
    ->sortBy('sort_order')
    ->values();
  $showIcons = $block->sidebarNavigationShowIcons();
  $activeMatching = $block->sidebarNavigationActiveMatching();
  $currentPageId = (int) ($block->renderPageId() ?? 0);
  $activeState = app(\WebBlocks\Cms\Support\Navigation\PublicNavigationActiveState::class);

  $filterVisibleItems = function ($items) use (&$filterVisibleItems, $block) {
    return $items
      ->filter(fn ($item) => $item->isVisible() && (! $item->page || ! app(\WebBlocks\Cms\Support\Pages\PublicPageAccess::class)->protected($item->page)))
      ->map(function ($item) use (&$filterVisibleItems) {
        $item->setRelation('children', $filterVisibleItems($item->children ?? collect()));

        return $item;
      })
      ->filter(function ($item) use ($block) {
        if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
          return $item->children->isNotEmpty();
        }

        if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) {
          return $item->page?->status === \WebBlocks\Cms\Models\Page::STATUS_PUBLISHED && $item->resolvedUrlForBlock($block) !== null;
        }

        return $item->resolvedUrlForBlock($block) !== null;
      })
      ->values();
  };

  $isNavigationItemActive = function (\WebBlocks\Cms\Models\NavigationItem $item) use (&$isNavigationItemActive, $activeMatching, $currentPageId, $activeState, $block): bool {
    if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
      return $item->children->contains(fn ($child) => $isNavigationItemActive($child));
    }

    $href = $item->resolvedUrlForBlock($block);

    if ($href === null) {
      return false;
    }

    return $activeState->matches($href, $activeMatching, $item->page_id, $currentPageId ?: null);
  };

  $items = $menuKey !== null
    ? $filterVisibleItems(app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)->buildPublicMenuTree($menuKey, $block->renderSite()?->id))
    : $manualItems;
@endphp

@if ($items->isNotEmpty())
  <nav class="wb-sidebar-nav" aria-label="{{ $label }}">
    <div class="wb-sidebar-section">
      @foreach ($items as $item)
        @if ($menuKey !== null)
          @include('webblocks-cms::pages.partials.blocks.sidebar-navigation-menu-item', [
            'item' => $item,
            'nested' => false,
            'showIcons' => $showIcons,
            'isNavigationItemActive' => $isNavigationItemActive,
          ])
        @else
          @include('webblocks-cms::pages.partials.block', ['block' => $item])
        @endif
      @endforeach
    </div>
  </nav>
@endif
