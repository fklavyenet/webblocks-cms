@php
  $menuKey = $block->navigationMenuKey();
  $siteScope = $block->renderSite()?->id;
  // Same rule as navbar-navigation: a page-linked item only renders while its
  // page is published, so archiving a page drops its link from every menu.
  $linksToUnpublishedPage = fn ($item) => $item->link_type === \WebBlocks\Cms\Models\NavigationItem::LINK_PAGE
    && ($item->page?->status !== \WebBlocks\Cms\Models\Page::STATUS_PUBLISHED || $item->resolvedUrl() === null);
  $items = app(\WebBlocks\Cms\Support\Navigation\NavigationTree::class)
    ->buildMenuTree($menuKey, $siteScope)
    ->filter(fn ($item) => $item->isVisible() && ! $linksToUnpublishedPage($item));

  $renderNavigationBranch = function ($branch, bool $buttonRoot = false) use (&$renderNavigationBranch, $linksToUnpublishedPage) {
    $html = '';

    foreach ($branch as $item) {
      if (! $item->isVisible() || $linksToUnpublishedPage($item)) {
        continue;
      }

      $children = $item->children->filter(fn ($child) => $child->isVisible() && ! $linksToUnpublishedPage($child));
      $url = $item->resolvedUrl();
      $label = e($item->resolvedTitle());
      $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';

      $html .= '<li class="wb-stack wb-gap-1">';

      if ($url) {
        $class = $buttonRoot ? 'wb-btn wb-btn-secondary' : 'wb-link';
        $html .= '<a href="'.e($url).'" class="'.$class.'"'.$target.'>'.$label.'</a>';
      } else {
        $html .= '<span>'.$label.'</span>';
      }

      if ($children->isNotEmpty()) {
        $html .= '<ul class="wb-stack wb-gap-1 wb-text-sm">'.$renderNavigationBranch($children).'</ul>';
      }

      $html .= '</li>';
    }

    return $html;
  };
@endphp

@if ($items->isNotEmpty())
  <nav class="wb-stack wb-gap-2" aria-label="{{ $menuKey }} navigation" data-wb-menu-key="{{ $menuKey }}">
    @if (in_array($menuKey, [\WebBlocks\Cms\Models\NavigationItem::MENU_FOOTER, \WebBlocks\Cms\Models\NavigationItem::MENU_LEGAL], true))
      <ul class="wb-stack wb-gap-1">{!! $renderNavigationBranch($items) !!}</ul>
    @else
      <ul class="wb-cluster wb-cluster-2 wb-cluster-between">{!! $renderNavigationBranch($items, true) !!}</ul>
    @endif
  </nav>
@endif
