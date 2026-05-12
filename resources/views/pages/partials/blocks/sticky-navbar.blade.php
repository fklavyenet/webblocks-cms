@php
    $site = $block->renderSite();
    $localeCode = $block->renderLocaleCode();
    $menuKey = $block->stickyNavbarMenuKey();
    $items = app(\App\Support\Navigation\NavigationTree::class)
        ->buildMenuTree($menuKey, $site?->id)
        ->filter(fn ($item) => $item->isVisible())
        ->map(function ($item) {
            $item->setRelation('children', $item->children
                ->filter(fn ($child) => $child->isVisible())
                ->filter(function ($child) {
                    if ($child->link_type !== \App\Models\NavigationItem::LINK_PAGE) {
                        return $child->resolvedUrl() !== null;
                    }

                    return $child->page?->status === \App\Models\Page::STATUS_PUBLISHED && $child->resolvedUrl() !== null;
                })
                ->values());

            return $item;
        })
        ->filter(function ($item) {
            if ($item->link_type === \App\Models\NavigationItem::LINK_GROUP) {
                return $item->children->isNotEmpty();
            }

            if ($item->link_type === \App\Models\NavigationItem::LINK_PAGE) {
                return $item->page?->status === \App\Models\Page::STATUS_PUBLISHED && $item->resolvedUrl() !== null;
            }

            return $item->resolvedUrl() !== null;
        })
        ->values();
    $brandLabel = $block->stringValueOrNull($block->title)
        ?? $block->translatedTextFieldValue('title')
        ?? $site?->publicDisplayName()
        ?? config('app.name');
    $brandUrl = $block->stickyNavbarBrandUrl()
        ?? app(\App\Support\Pages\PageRouteResolver::class)->homePath($localeCode, $site)
        ?? '/';
    $brandLogo = $block->asset?->url() ?? $block->stickyNavbarLogoPath();
    $containerClass = trim('wb-container '.$block->stickyNavbarContainerWidthClass());
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
    $isItemActive = function ($item) use (&$isItemActive, $currentPageId, $currentPath, $currentUrl, $normalizePath): bool {
        if ($item->link_type === \App\Models\NavigationItem::LINK_GROUP) {
            return $item->children->contains(fn ($child) => $isItemActive($child));
        }

        $href = $item->resolvedUrl();

        if ($href === null) {
            return false;
        }

        if ($item->page_id !== null && (int) $item->page_id === $currentPageId) {
            return true;
        }

        $normalized = $normalizePath($href);

        return rtrim((string) url()->to($href), '/') === $currentUrl
            || ($normalized !== null && $normalized === $currentPath);
    };
@endphp

@if ($items->isNotEmpty())
    <nav
        class="wb-navbar wb-cms-sticky-navbar wb-cms-sticky-navbar-{{ $block->stickyNavbarVariant() }} wb-cms-sticky-navbar-mode-{{ $block->stickyNavbarMode() }}{{ $block->stickyNavbarCompact() ? ' is-compact' : '' }}"
        aria-label="{{ $brandLabel }} navigation"
        data-wb-menu-key="{{ $menuKey }}"
        data-wb-block-type="{{ $block->publicBlockTypeAttribute() }}"
    >
        <div class="{{ $containerClass }}">
            <div class="wb-cms-sticky-navbar-bar">
                <a href="{{ $brandUrl }}" class="wb-cms-sticky-navbar-brand wb-no-decoration">
                    @if ($brandLogo)
                        <img src="{{ $brandLogo }}" alt="{{ $brandLabel }}" class="wb-cms-sticky-navbar-logo">
                    @endif

                    <span class="wb-cms-sticky-navbar-brand-text">{{ $brandLabel }}</span>
                </a>

                <nav class="wb-cms-sticky-navbar-menu" aria-label="Primary navigation">
                    <ul class="wb-cms-sticky-navbar-list">
                        @foreach ($items as $item)
                            @php
                                $isActive = $isItemActive($item);
                                $url = $item->resolvedUrl();
                                $children = $item->children;
                            @endphp

                            @if ($item->link_type === \App\Models\NavigationItem::LINK_GROUP && $children->isNotEmpty())
                                <li class="wb-dropdown wb-cms-sticky-navbar-item{{ $isActive ? ' is-active' : '' }}">
                                    <button type="button" class="wb-cms-sticky-navbar-link wb-cms-sticky-navbar-link-trigger{{ $isActive ? ' is-active' : '' }}" data-wb-toggle="dropdown" data-wb-target="#sticky-navbar-group-{{ $block->id }}-{{ $item->id }}" aria-expanded="false">
                                        {{ $item->resolvedTitle() }}
                                        <i class="wb-icon wb-icon-chevron-down" aria-hidden="true"></i>
                                    </button>

                                    <div class="wb-dropdown-menu" id="sticky-navbar-group-{{ $block->id }}-{{ $item->id }}">
                                        @foreach ($children as $child)
                                            @php
                                                $childActive = $isItemActive($child);
                                                $childUrl = $child->resolvedUrl();
                                                $childTarget = $child->target ? ' target="'.e($child->target).'" rel="noopener noreferrer"' : '';
                                            @endphp

                                            @if ($childUrl)
                                                <a class="wb-dropdown-item{{ $childActive ? ' is-active' : '' }}" href="{{ $childUrl }}"{!! $childActive ? ' aria-current="page"' : '' !!}{!! $childTarget !!}>{{ $child->resolvedTitle() }}</a>
                                            @endif
                                        @endforeach
                                    </div>
                                </li>
                            @elseif ($url)
                                @php
                                    $target = $item->target ? ' target="'.e($item->target).'" rel="noopener noreferrer"' : '';
                                @endphp
                                <li class="wb-cms-sticky-navbar-item{{ $isActive ? ' is-active' : '' }}">
                                    <a href="{{ $url }}" class="wb-cms-sticky-navbar-link{{ $isActive ? ' is-active' : '' }}"{!! $isActive ? ' aria-current="page"' : '' !!}{!! $target !!}>{{ $item->resolvedTitle() }}</a>
                                </li>
                            @endif
                        @endforeach
                    </ul>
                </nav>
            </div>
        </div>
    </nav>
@endif
