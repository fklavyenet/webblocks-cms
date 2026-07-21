@php
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $searchLabel = $translator->public('search.title', $block->renderLocaleCode());
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = false;
    $showSearch = ($settings['show_search'] ?? true) !== false;
    $searchPath = $routeResolver->searchPath($block->renderLocaleCode(), $block->renderSite());
@endphp

@if ($showModeToggle || $showAccentToggle || ($showSearch && $searchPath))
    <div class="wb-navbar-end wb-ms-auto" data-wb-header-actions>
        <div class="wb-cluster">
            @if ($showSearch && $searchPath)
                <a
                    href="{{ $searchPath }}"
                    class="wb-btn wb-btn-ghost wb-btn-icon"
                    data-wb-public-search-open
                    aria-label="{{ $searchLabel }}"
                    title="{{ $searchLabel }}"
                >
                    <i class="wb-icon wb-icon-search" aria-hidden="true"></i>
                    <span class="wb-sr-only">{{ $searchLabel }}</span>
                </a>
            @endif

            @if ($showModeToggle)
                @php $modeAutoLabel = $translator->public('theme.mode_auto', $block->renderLocaleCode()); @endphp
                <button
                    type="button"
                    class="wb-btn wb-btn-ghost wb-btn-icon"
                    data-wb-mode-cycle
                    data-wb-mode-label-light="{{ $translator->public('theme.mode_light', $block->renderLocaleCode()) }}"
                    data-wb-mode-label-dark="{{ $translator->public('theme.mode_dark', $block->renderLocaleCode()) }}"
                    data-wb-mode-label-auto="{{ $modeAutoLabel }}"
                    aria-label="{{ $modeAutoLabel }}"
                    title="{{ $modeAutoLabel }}"
                >
                    <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                </button>
            @endif
        </div>
    </div>
@endif
