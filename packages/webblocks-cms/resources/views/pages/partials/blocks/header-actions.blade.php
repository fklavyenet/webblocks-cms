@php
    $routeResolver = app(\WebBlocks\Cms\Support\Pages\PageRouteResolver::class);
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = ($settings['show_accent_toggle'] ?? true) !== false;
    $showSearch = ($settings['show_search'] ?? true) !== false;
    $themeMenuId = 'wb-header-actions-theme-menu-'.$block->id;
    $searchPath = $routeResolver->searchPath($block->renderLocaleCode(), $block->renderSite());
    $presets = [
        'modern' => 'Modern',
        'corporate' => 'Corporate',
        'minimal' => 'Minimal',
        'editorial' => 'Editorial',
        'playful' => 'Playful',
    ];
    $accents = [
        'ocean' => 'Ocean',
        'royal' => 'Royal',
        'forest' => 'Forest',
        'sunset' => 'Sunset',
        'mint' => 'Mint',
        'amber' => 'Amber',
        'rose' => 'Rose',
        'slate-fire' => 'Slate Fire',
    ];
@endphp

@if ($showModeToggle || $showAccentToggle || ($showSearch && $searchPath))
    <div class="wb-cluster wb-cluster-2 wb-cluster-end" data-wb-header-actions>
        <div class="wb-topbar-actions">
            @if ($showSearch && $searchPath)
                <a
                    href="{{ $searchPath }}"
                    class="wb-topbar-action"
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
                    class="wb-topbar-action"
                    data-wb-mode-cycle
                    data-wb-header-actions-mode-toggle
                    aria-label="Auto mode"
                    aria-pressed="false"
                    title="Auto mode"
                >
                    <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
                </button>
            @endif

            @if ($showAccentToggle)
                <div class="wb-dropdown wb-dropdown-end" data-wb-header-actions-accent>
                    <button
                        type="button"
                        class="wb-topbar-action"
                        data-wb-toggle="dropdown"
                        data-wb-target="#{{ $themeMenuId }}"
                        data-wb-header-actions-accent-toggle
                        aria-label="Theme settings"
                        aria-expanded="false"
                        aria-haspopup="menu"
                        aria-controls="{{ $themeMenuId }}"
                        title="Theme settings"
                    >
                        <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
                    </button>

                    <div class="wb-dropdown-menu" id="{{ $themeMenuId }}" role="menu" aria-label="Theme settings">
                        <div class="wb-dropdown-label">Presets</div>
                        @foreach ($presets as $presetValue => $presetLabel)
                            <button
                                type="button"
                                class="wb-dropdown-item"
                                data-wb-header-actions-preset-option
                                data-wb-preset-set="{{ $presetValue }}"
                                role="menuitemradio"
                                aria-checked="false"
                            >
                                {{ $presetLabel }}
                            </button>
                        @endforeach

                        <hr class="wb-dropdown-divider">
                        <div class="wb-dropdown-label">Accent</div>
                        @foreach ($accents as $accentValue => $accentLabel)
                            <button
                                type="button"
                                class="wb-dropdown-item"
                                data-wb-header-actions-accent-option
                                data-wb-accent-set="{{ $accentValue }}"
                                role="menuitemradio"
                                aria-checked="false"
                            >
                                {{ $accentLabel }}
                            </button>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
