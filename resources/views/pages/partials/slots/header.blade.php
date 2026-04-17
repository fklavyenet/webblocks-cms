@php
    $chrome = $slot['chrome'];
    $branding = $chrome['branding'] ?? null;
    $actionBlocks = $chrome['actions'] ?? collect();
    $primaryItems = $chrome['primary_items'] ?? collect();
    $mobileItems = $chrome['mobile_items'] ?? collect();

    $branchHasCurrent = function ($item) use (&$branchHasCurrent, $page) {
        if ($item->page_id === $page->id) {
            return true;
        }

        return $item->children->contains(fn ($child) => $branchHasCurrent($child));
    };

    $renderHeaderItems = function ($items, bool $isMobile = false) use (&$renderHeaderItems, $branchHasCurrent) {
        $html = '';

        foreach ($items as $item) {
            $children = $item->children->filter(fn ($child) => $child->isVisible());
            $url = $item->resolvedUrl();
            $label = e($item->resolvedTitle());
            $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
            $isCurrent = $branchHasCurrent($item);
            $isAction = $item->link_type === \App\Models\NavigationItem::LINK_CUSTOM_URL && $children->isEmpty();

            if ($isMobile) {
                $html .= '<li class="wb-stack wb-gap-2">';
                $html .= $url
                    ? '<a href="'.e($url).'" class="'.($isAction ? 'wb-btn wb-btn-primary' : 'wb-link').($isCurrent && ! $isAction ? ' is-active' : '').'"'.$target.'>'.$label.'</a>'
                    : '<span>'.$label.'</span>';

                if ($children->isNotEmpty()) {
                    $html .= '<ul class="wb-stack wb-gap-1 wb-text-sm">'.$renderHeaderItems($children, true).'</ul>';
                }

                $html .= '</li>';

                continue;
            }

            if ($children->isNotEmpty()) {
                $menuId = 'public-nav-group-'.$item->id;
                $html .= '<li class="wb-dropdown wb-public-nav-item'.($isCurrent ? ' is-active' : '').'">';
                $html .= '<button type="button" class="wb-public-nav-link wb-public-nav-link-trigger'.($isCurrent ? ' is-active' : '').'" data-wb-toggle="dropdown" data-wb-target="#'.$menuId.'" aria-expanded="false">'.$label.' <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i></button>';
                $html .= '<div class="wb-dropdown-menu" id="'.$menuId.'">';

                foreach ($children as $child) {
                    $childUrl = $child->resolvedUrl();
                    $childTarget = $child->target ? ' target="'.e($child->target).'" rel="noopener noreferrer"' : '';
                    $html .= $childUrl
                        ? '<a class="wb-dropdown-item" href="'.e($childUrl).'"'.$childTarget.'>'.e($child->resolvedTitle()).'</a>'
                        : '<span class="wb-dropdown-item">'.e($child->resolvedTitle()).'</span>';
                }

                $html .= '</div></li>';

                continue;
            }

            $html .= '<li class="wb-public-nav-item'.($isCurrent ? ' is-active' : '').'">';
            $html .= $url
                ? '<a href="'.e($url).'" class="'.($isAction ? 'wb-btn wb-btn-primary' : 'wb-public-nav-link').($isCurrent && ! $isAction ? ' is-active' : '').'"'.($isCurrent && ! $isAction ? ' aria-current="page"' : '').$target.'>'.$label.'</a>'
                : '<span class="wb-public-nav-link">'.$label.'</span>';
            $html .= '</li>';
        }

        return $html;
    };

    $brandLabel = $branding?->title ?: ($branding?->content ?: config('app.name'));
    $brandContext = $branding?->typeSlug() === 'heading'
        ? null
        : ($branding?->content ?: config('app.slogan'));
    $brandImage = $branding?->typeSlug() === 'image' ? $branding?->asset?->url() : null;
@endphp

<header class="wb-section wb-public-header" data-wb-public-header>
    <div class="wb-container wb-container-lg">
        <div class="wb-public-header-bar">
            <a href="{{ route('pages.show', 'home') }}" class="wb-public-header-identity wb-no-decoration" aria-label="{{ $brandLabel }} home">
                @if ($brandImage)
                    <img src="{{ $brandImage }}" alt="{{ $brandLabel }}" style="max-height: 2.5rem; width: auto;">
                @endif
                <span>
                    <span class="wb-public-header-brand">{{ $brandLabel }}</span>
                    @if ($brandContext)
                        <span class="wb-public-header-context">{{ $brandContext }}</span>
                    @endif
                </span>
            </a>

            <span class="wb-public-header-spacer"></span>

            @if ($primaryItems->isNotEmpty())
                <nav class="wb-public-header-nav" aria-label="Primary navigation">
                    <ul class="wb-public-nav-list">{!! $renderHeaderItems($primaryItems) !!}</ul>
                </nav>
            @endif

            @if ($actionBlocks->isNotEmpty())
                <div class="wb-public-header-actions">
                    <div class="wb-cluster wb-cluster-2">
                        @foreach ($actionBlocks as $block)
                            @include('pages.partials.block', ['block' => $block])
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($mobileItems->isNotEmpty())
                <div class="wb-dropdown wb-dropdown-end wb-public-header-mobile">
                    <button class="wb-public-header-menu-trigger" type="button" data-wb-toggle="dropdown" data-wb-target="#public-mobile-menu" aria-expanded="false" aria-label="Open navigation">
                        <i class="wb-icon wb-icon-menu-2" aria-hidden="true"></i>
                    </button>
                    <div class="wb-dropdown-menu" id="public-mobile-menu">
                        <div class="wb-stack wb-gap-2" style="min-width: 16rem;">
                            <ul class="wb-stack wb-gap-2">{!! $renderHeaderItems($mobileItems, true) !!}</ul>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</header>
