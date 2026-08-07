@php
  $menuKey = $block->navbarNavigationMenuKey();
  $site = $block->renderSite();
  $activeIndicator = $block->navbarNavigationActiveIndicator();
  $activeMatching = $block->navbarNavigationActiveMatching();
  $activeIndicatorClass = match ($activeIndicator) {
    'pill' => 'wb-navbar-nav--active-pill',
    'dot' => 'wb-navbar-nav--active-dot',
    'background' => 'wb-navbar-nav--active-background',
    'none' => 'wb-navbar-nav--active-none',
    default => 'wb-navbar-nav--active-underline',
  };
  $items = app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)
    ->buildMenuTree($menuKey, $site?->id)
    ->filter(fn ($item) => $item->isVisible())
    ->map(function ($item) {
      $item->setRelation('children', $item->children
        ->filter(fn ($child) => $child->isVisible())
        ->filter(function ($child) {
          if ($child->link_type !== \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE) {
            return $child->resolvedUrl() !== null;
          }

          return $child->page?->status === \WebBlocks\Cms\Models\Page::STATUS_PUBLISHED && $child->resolvedUrl() !== null;
        })
        ->values());

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
  $a11y = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
    ->get('blocks.a11y.'.$key, strtolower((string) ($block->renderLocaleCode() ?? app()->getLocale())));
  $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title') ?? $a11y('menu.primary');
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
  $isItemActive = function ($item) use (&$isItemActive, $activeMatching, $currentPageId, $currentPath, $currentUrl, $normalizePath): bool {
    if ($activeMatching === 'off') {
      return false;
    }

    if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
      return $item->children->contains(fn ($child) => $isItemActive($child));
    }

    $href = $item->resolvedUrl();

    if ($href === null) {
      return false;
    }

    $normalized = $normalizePath($href);

    return match ($activeMatching) {
      'exact' => rtrim((string) url()->to($href), '/') === $currentUrl,
      'current-page' => $item->page_id !== null
        ? (int) $item->page_id === $currentPageId
        : $normalized !== null && $normalized === $currentPath,
      'section' => $normalized !== null && $normalized !== '/'
        ? $currentPath === $normalized || str_starts_with($currentPath, $normalized.'/')
        : $currentPath === '/',
      default => ($item->page_id !== null && (int) $item->page_id === $currentPageId)
        || rtrim((string) url()->to($href), '/') === $currentUrl
        || ($normalized !== null && $normalized === $currentPath),
    };
  };
  $drawerId = 'wb-navbar-drawer-'.$block->id;

  $renderDrawerItems = function ($items) use (&$renderDrawerItems, $isItemActive) {
    $html = '';

    foreach ($items as $item) {
      $isActive = $isItemActive($item);
      $url = $item->resolvedUrl();
      $children = $item->children;

      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP && $children->isNotEmpty()) {
        $html .= '<span class="wb-text-sm wb-text-muted">'.e($item->resolvedTitle()).'</span>';
        $html .= $renderDrawerItems($children);

        continue;
      }

      if (! $url) {
        continue;
      }

      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
      $html .= '<a href="'.e($url).'" class="wb-navbar-link'.($isActive ? ' is-active' : '').'"'.($isActive ? ' aria-current="page"' : '').$target.'>'.e($item->resolvedTitle()).'</a>';
    }

    return $html;
  };

  $renderItems = function ($items) use (&$renderItems, $block, $isItemActive) {
    $html = '';

    foreach ($items as $item) {
      $isActive = $isItemActive($item);
      $url = $item->resolvedUrl();
      $children = $item->children;

      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP && $children->isNotEmpty()) {
        $groupMenuId = 'navbar-navigation-group-'.$block->id.'-'.$item->id;
        $html .= '<li class="wb-navbar-nav-item wb-dropdown">';
        $html .= '<button type="button" class="wb-navbar-link'.($isActive ? ' is-active' : '').'" data-wb-toggle="dropdown" data-wb-target="#'.$groupMenuId.'" aria-expanded="false">';
        $html .= e($item->resolvedTitle());
        $html .= ' <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>';
        $html .= '</button>';
        $html .= '<div class="wb-dropdown-menu" id="'.$groupMenuId.'">';

        foreach ($children as $child) {
          $childActive = $isItemActive($child);
          $childUrl = $child->resolvedUrl();
          $childTarget = $child->target ? ' target="'.e($child->target).'" rel="noopener noreferrer"' : '';

          if ($childUrl) {
            $html .= '<a class="wb-dropdown-item'.($childActive ? ' is-active' : '').'" href="'.e($childUrl).'"'.($childActive ? ' aria-current="page"' : '').$childTarget.'>'.e($child->resolvedTitle()).'</a>';
          }
        }

        $html .= '</div></li>';

        continue;
      }

      if (! $url) {
        continue;
      }

      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
      $html .= '<li class="wb-navbar-nav-item">';
      $html .= '<a href="'.e($url).'" class="wb-navbar-link'.($isActive ? ' is-active' : '').'"'.($isActive ? ' aria-current="page"' : '').$target.'>'.e($item->resolvedTitle()).'</a>';
      $html .= '</li>';
    }

    return $html;
  };
@endphp

@if ($items->isNotEmpty())
  @php
    // Mobile menu: the shipped wb-navbar-drawer contract. The drawer must sit
    // directly after the navbar element, so it is pushed to the navbar drawer
    // registry and rendered by the navbar container after its own </nav>.
    $drawerHtml = '<div class="wb-navbar-drawer" id="'.e($drawerId).'" aria-label="'.e($label).'">'
      .$renderDrawerItems($items)
      .'</div>';
    app(\WebBlocks\Cms\Support\Blocks\PublicNavbarDrawerRegistry::class)->push($drawerHtml);
  @endphp

  <button
    type="button"
    class="wb-navbar-toggle"
    data-wb-collapse="{{ $drawerId }}"
    aria-expanded="false"
    aria-controls="{{ $drawerId }}"
    aria-label="{{ $a11y('toggle_navigation') }}"
    data-wb-public-block-type="{{ $block->publicBlockTypeAttribute() }}"
  >
    <span></span><span></span><span></span>
  </button>

  <div class="wb-navbar-links">
    <ul class="wb-navbar-nav {{ $activeIndicatorClass }}" aria-label="{{ $label }}">
      {!! $renderItems($items) !!}
    </ul>
  </div>
@endif
