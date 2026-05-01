@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = ($settings['show_accent_toggle'] ?? true) !== false;
    $accents = [
        'ocean' => 'Ocean',
        'forest' => 'Forest',
        'sunset' => 'Sunset',
        'royal' => 'Royal',
        'mint' => 'Mint',
        'amber' => 'Amber',
        'rose' => 'Rose',
        'slate-fire' => 'Slate Fire',
    ];
@endphp

@if ($showModeToggle || $showAccentToggle)
    <div class="wb-topbar-actions wb-cluster wb-cluster-2" data-wb-header-actions>
        @if ($showModeToggle)
            <button
                type="button"
                class="wb-btn wb-btn-ghost wb-btn-icon"
                data-wb-header-actions-mode-toggle
                aria-label="Toggle color mode"
                aria-pressed="false"
            >
                <span class="wb-icon wb-icon-sun-moon" aria-hidden="true"></span>
            </button>
        @endif

        @if ($showAccentToggle)
            <div class="wb-dropdown wb-dropdown-end" data-wb-header-actions-accent>
                <button
                    type="button"
                    class="wb-btn wb-btn-ghost wb-btn-icon"
                    data-wb-toggle="dropdown"
                    data-wb-header-actions-accent-toggle
                    aria-label="Change accent color"
                    aria-expanded="false"
                    aria-haspopup="menu"
                >
                    <span class="wb-icon wb-icon-palette" aria-hidden="true"></span>
                </button>

                <div class="wb-dropdown-menu" role="menu" aria-label="Accent color options">
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
@endif
