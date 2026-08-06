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
  $currentPath = '/'.ltrim(request()->path(), '/');
  $currentPath = $currentPath === '/' ? '/' : rtrim($currentPath, '/');
  $currentUrl = rtrim(url()->current(), '/');
  $currentPageId = (int) ($block->renderPageId() ?? 0);
  $normalizePath = function (?string $value): ?string {
    if (! is_string($value) || trim($value) === '') {
      return null;
    }

    if (preg_match('/^[A-Za-z][A-Za-z0-9+.-]*:/', $value) === 1 || str_starts_with($value, '#')) {
      return null;
    }

    $path = '/'.ltrim(parse_url($value, PHP_URL_PATH) ?? '', '/');

    return $path === '/' ? '/' : rtrim($path, '/');
  };

  $filterVisibleItems = function ($items) use (&$filterVisibleItems) {
    return $items
      ->filter(fn ($item) => $item->isVisible())
      ->map(function ($item) use (&$filterVisibleItems) {
        $item->setRelation('children', $filterVisibleItems($item->children ?? collect()));

        return $item;
      })
      ->filter(function ($item) {
        if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
          return $item->children->isNotEmpty();
        }

        if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) {
          return $item->page?->status === \WebBlocks\Cms\Models\Page::STATUS_PUBLISHED && $item->resolvedUrl() !== null;
        }

        return $item->resolvedUrl() !== null;
      })
      ->values();
  };

  $isNavigationItemActive = function (\WebBlocks\Cms\Models\NavigationItem $item) use (&$isNavigationItemActive, $activeMatching, $currentPageId, $currentPath, $currentUrl, $normalizePath): bool {
    if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
      return $item->children->contains(fn ($child) => $isNavigationItemActive($child));
    }

    $href = $item->resolvedUrl();

    if ($href === null) {
      return false;
    }

    return match ($activeMatching) {
      'exact' => rtrim((string) url()->to($href), '/') === $currentUrl,
      'current-page' => $item->page_id !== null
        ? (int) $item->page_id === $currentPageId
        : $normalizePath($href) !== null && $normalizePath($href) === $currentPath,
      default => $normalizePath($href) !== null && $normalizePath($href) === $currentPath,
    };
  };

  $items = $menuKey !== null
    ? $filterVisibleItems(app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)->buildMenuTree($menuKey, $block->renderSite()?->id))
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
