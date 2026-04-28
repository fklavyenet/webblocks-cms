@php
    $menuKey = $block->navigationMenuKey();
    $siteScope = $block->page?->site_id;
    $items = app(\App\Support\Navigation\NavigationTree::class)
        ->buildMenuTree($menuKey, $siteScope)
        ->filter(fn ($item) => $item->isVisible());

    $renderNavigationBranch = function ($branch, bool $buttonRoot = false) use (&$renderNavigationBranch) {
        $html = '';

        foreach ($branch as $item) {
            if (! $item->isVisible()) {
                continue;
            }

            $children = $item->children->filter(fn ($child) => $child->isVisible());
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
        @if (in_array($menuKey, [\App\Models\NavigationItem::MENU_FOOTER, \App\Models\NavigationItem::MENU_LEGAL], true))
            <ul class="wb-stack wb-gap-1">{!! $renderNavigationBranch($items) !!}</ul>
        @else
            <ul class="wb-cluster wb-cluster-2 wb-cluster-between">{!! $renderNavigationBranch($items, true) !!}</ul>
        @endif
    </nav>
@endif
