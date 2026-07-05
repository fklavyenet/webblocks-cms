@php
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = false;
    $showSearch = ($settings['show_search'] ?? true) !== false;
    $searchPath = $routeResolver->searchPath($block->renderLocaleCode(), $block->renderSite());
@endphp

@if ($showModeToggle || $showAccentToggle || ($showSearch && $searchPath))
    <div class="wb-navbar-end wb-ms-auto" data-wb-header-actions>
        <div class="wb-navbar-iconbar">
            @if ($showSearch && $searchPath)
                <a
                    href="{{ $searchPath }}"
                    class="wb-navbar-icon-trigger"
                    data-wb-public-search-open
                    aria-label="Search"
                    title="Search"
                >
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i>
                    <span class="wb-sr-only">Search</span>
                </a>
            @endif

            @if ($showModeToggle)
                <button
                    type="button"
                    class="wb-navbar-icon-trigger"
                    data-wb-mode-cycle
                    data-wb-header-actions-mode-toggle
                    aria-label="Auto mode"
                    aria-pressed="false"
                    title="Auto mode"
                >
                    <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </div>
@endif
