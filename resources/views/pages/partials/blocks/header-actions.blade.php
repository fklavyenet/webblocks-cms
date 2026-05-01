@php
    $settings = json_decode((string) $block->getRawOriginal('settings'), true);
    $settings = is_array($settings) ? $settings : [];
    $showModeToggle = ($settings['show_mode_toggle'] ?? true) !== false;
    $showAccentToggle = ($settings['show_accent_toggle'] ?? true) !== false;
@endphp

@if ($showModeToggle || $showAccentToggle)
    <div class="wb-cluster wb-cluster-2" data-wb-header-actions>
        @if ($showModeToggle)
            <button
                type="button"
                class="wb-btn wb-btn-ghost wb-btn-icon"
                data-wb-header-actions-mode-toggle
                aria-label="Toggle color mode"
            >
                <i class="wb-icon wb-icon-sun-moon" aria-hidden="true"></i>
            </button>
        @endif

        @if ($showAccentToggle)
            <button
                type="button"
                class="wb-btn wb-btn-ghost wb-btn-icon"
                data-wb-header-actions-accent-toggle
                aria-label="Change accent color"
            >
                <i class="wb-icon wb-icon-palette" aria-hidden="true"></i>
            </button>
        @endif
    </div>
@endif
