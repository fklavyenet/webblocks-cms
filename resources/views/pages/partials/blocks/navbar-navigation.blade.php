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
  $renderableItems = function ($items) use (&$renderableItems, $block) {
    return $items->filter(function ($item) use (&$renderableItems, $block) {
      if (! $item->isVisible()) {
        return false;
      }

      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
        $item->setRelation('children', $renderableItems($item->children));

        return $item->children->isNotEmpty();
      }

      return (bool) $item->resolvedUrlForBlock($block);
    })->values();
  };
  $items = $renderableItems(app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)
    ->buildPublicMenuTree($menuKey, $site?->id));
  $a11y = fn (string $key) => app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class)
    ->get('blocks.a11y.'.$key, strtolower((string) ($block->renderLocaleCode() ?? app()->getLocale())));
  $label = $block->stringValueOrNull($block->title) ?? $block->translatedTextFieldValue('title') ?? $a11y('menu.primary');
  $currentPageId = (int) ($block->renderPageId() ?? 0);
  $activeState = app(\WebBlocks\Cms\Support\Navigation\PublicNavigationActiveState::class);
  $isItemActive = function ($item) use (&$isItemActive, $activeMatching, $currentPageId, $activeState, $block): bool {
    if ($activeMatching === 'off') {
      return false;
    }

    if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
      return $item->children->contains(fn ($child) => $isItemActive($child));
    }

    $href = $item->resolvedUrlForBlock($block);

    if ($href === null) {
      return false;
    }

    return $activeState->matches($href, $activeMatching, $item->page_id, $currentPageId ?: null);
  };
  $drawerId = 'wb-navbar-drawer-'.$block->id;
  $iconPresenter = app(\WebBlocks\Cms\Support\PublicRendering\PublicIconPresenter::class);
  $renderItemContent = function ($item) use ($iconPresenter, $block): string {
    $title = e($item->resolvedTitleForBlock($block));
    $iconClass = $iconPresenter->iconClass($item->sidebarIcon());

    if ($iconClass === null) {
      return $title;
    }

    return '<i class="'.e($iconClass).'" aria-hidden="true"></i>'
      .'<span>'.$title.'</span>';
  };

  $renderDrawerItems = function ($items, bool $insideGroup = false) use (&$renderDrawerItems, $isItemActive, $renderItemContent, $block) {
    $html = '';

    foreach ($items as $item) {
      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
        $title = e($item->resolvedTitleForBlock($block));

        if ($insideGroup) {
          $labelId = 'navbar-navigation-drawer-group-'.$block->id.'-'.$item->id;
          $html .= '<div class="wb-stack wb-stack-1 wb-px-4" role="group" aria-labelledby="'.$labelId.'">';
          $html .= '<span class="wb-text-sm wb-text-muted" id="'.$labelId.'">'.$title.'</span>';
          $html .= $renderDrawerItems($item->children, true);
          $html .= '</div>';
        } else {
          $html .= '<span class="wb-text-sm wb-text-muted">'.$title.'</span>';
          $html .= $renderDrawerItems($item->children, true);
        }

        continue;
      }

      $url = $item->resolvedUrlForBlock($block);

      if (! $url) {
        continue;
      }

      $isActive = $isItemActive($item);
      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
      $html .= '<a href="'.e($url).'" class="wb-navbar-link'.($isActive ? ' is-active' : '').'"'.($isActive ? ' aria-current="page"' : '').$target.\WebBlocks\Cms\Support\Seo\SeoHtmlEvidence::navigationAttribute((int) $item->id, $block).'>'.$renderItemContent($item).'</a>';
    }

    return $html;
  };

  $renderDropdownItems = function ($items) use (&$renderDropdownItems, $block, $isItemActive) {
    $html = '';

    foreach ($items as $item) {
      $isActive = $isItemActive($item);
      $title = e($item->resolvedTitleForBlock($block));

      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP) {
        $menuId = 'navbar-navigation-group-'.$block->id.'-'.$item->id;
        $html .= '<button type="button" class="wb-dropdown-item'.($isActive ? ' is-active' : '').'" data-wb-toggle="submenu" aria-controls="'.$menuId.'" aria-expanded="false">'.$title.'</button>';
        $html .= '<div class="wb-dropdown-menu" id="'.$menuId.'">'.$renderDropdownItems($item->children).'</div>';

        continue;
      }

      $url = $item->resolvedUrlForBlock($block);
      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';

      if ($url) {
        $html .= '<a class="wb-dropdown-item'.($isActive ? ' is-active' : '').'" href="'.e($url).'"'.($isActive ? ' aria-current="page"' : '').$target.\WebBlocks\Cms\Support\Seo\SeoHtmlEvidence::navigationAttribute((int) $item->id, $block).'>'.$title.'</a>';
      }
    }

    return $html;
  };

  $renderItems = function ($items) use ($block, $isItemActive, $renderItemContent, $renderDropdownItems) {
    $html = '';

    foreach ($items as $item) {
      $isActive = $isItemActive($item);
      $url = $item->resolvedUrlForBlock($block);
      $children = $item->children;

      if ($item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_GROUP && $children->isNotEmpty()) {
        $groupMenuId = 'navbar-navigation-group-'.$block->id.'-'.$item->id;
        $html .= '<li class="wb-navbar-nav-item wb-dropdown">';
        $html .= '<button type="button" class="wb-navbar-link'.($isActive ? ' is-active' : '').'" data-wb-toggle="dropdown" data-wb-target="#'.$groupMenuId.'" aria-expanded="false">';
        $html .= e($item->resolvedTitleForBlock($block));
        $html .= ' <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>';
        $html .= '</button>';
        $html .= '<div class="wb-dropdown-menu" id="'.$groupMenuId.'">'.$renderDropdownItems($children).'</div></li>';

        continue;
      }

      if (! $url) {
        continue;
      }

      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
      $html .= '<li class="wb-navbar-nav-item">';
      $html .= '<a href="'.e($url).'" class="wb-navbar-link'.($isActive ? ' is-active' : '').'"'.($isActive ? ' aria-current="page"' : '').$target.\WebBlocks\Cms\Support\Seo\SeoHtmlEvidence::navigationAttribute((int) $item->id, $block).'>'.$renderItemContent($item).'</a>';
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
