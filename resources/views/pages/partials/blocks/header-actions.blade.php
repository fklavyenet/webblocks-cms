@php
    $settings = $block->settings ?? [];
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = ($settings['show_accent_toggle'] ?? true) !== false;
    $accentMenuId = 'wb-header-actions-accent-menu-'.$block->id;
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
    <div class="wb-topbar-actions" data-wb-header-actions>
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
                    data-wb-target="#{{ $accentMenuId }}"
                    data-wb-header-actions-accent-toggle
                    aria-label="Change accent color"
                    aria-expanded="false"
                    aria-haspopup="menu"
                    aria-controls="{{ $accentMenuId }}"
                    title="Change accent color"
                >
                    <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
                </button>

                <div class="wb-dropdown-menu" id="{{ $accentMenuId }}" role="menu" aria-label="Accent color options">
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
