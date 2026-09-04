@php
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $translator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $searchLabel = $translator->public('search.title', $block->renderLocaleCode());
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = false;
    $showSearch = ($settings['show_search'] ?? true) !== false;
    $showLanguageSwitcher = ($settings['show_language_switcher'] ?? true) !== false;
    $searchPath = $routeResolver->searchPath($block->renderLocaleCode(), $block->renderSite());

    $currentPage = $block->renderPage();
    $currentSite = $block->renderSite();
    $currentLocaleCode = $block->renderLocaleCode();
    $availableLocales = ($showLanguageSwitcher && $currentSite) ? $currentSite->enabledLocales()->get() : collect();

    // Each locale links to the current page's real translated path (e.g. the
    // ES link on /products goes to /es/productos), falling back to that
    // locale's home page when the page has no resolvable translation.
    $localeLinks = $availableLocales
        ->map(function ($locale) use ($routeResolver, $currentPage, $currentSite) {
            $path = $currentPage
                ? $routeResolver->pathFor($currentPage, $locale, $currentSite)
                : null;
            $path ??= $routeResolver->homePath($locale->code, $currentSite);

            return $path ? ['locale' => $locale, 'path' => $path] : null;
        })
        ->filter()
        ->values();

    $currentLocaleName = $availableLocales->firstWhere('code', $currentLocaleCode)?->name;
@endphp

@if ($showModeToggle || $showAccentToggle || ($showSearch && $searchPath) || $localeLinks->count() > 1)
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

            @if ($localeLinks->count() > 1)
                <div class="wb-language-switcher wb-language-switcher--code wb-dropdown wb-dropdown-end">
                    <button
                        class="wb-btn wb-btn-ghost wb-language-switcher-trigger"
                        type="button"
                        data-wb-toggle="dropdown"
                        data-wb-target="#header-locale-menu-{{ $block->id }}"
                        aria-label="{{ $currentLocaleName ?? 'Language' }}"
                        aria-expanded="false"
                    >
                        <span class="wb-language-switcher-code" lang="{{ $currentLocaleCode }}" aria-hidden="true">{{ strtoupper((string) $currentLocaleCode) }}</span>
                        <i class="wb-icon wb-icon-chevron-down wb-language-switcher-chevron" aria-hidden="true"></i>
                    </button>

                    <div class="wb-dropdown-menu" id="header-locale-menu-{{ $block->id }}">
                        @foreach ($localeLinks as $entry)
                            <a
                                href="{{ $entry['path'] }}"
                                class="wb-dropdown-item @if ($entry['locale']->code === $currentLocaleCode) is-active @endif"
                                lang="{{ $entry['locale']->code }}"
                                @if ($entry['locale']->code === $currentLocaleCode) aria-current="true" @endif
                            >
                                <span class="wb-language-switcher-item-code">{{ strtoupper($entry['locale']->code) }}</span>
                                {{ $entry['locale']->name }}
                            </a>
                        @endforeach
                    </div>
                </div>
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
