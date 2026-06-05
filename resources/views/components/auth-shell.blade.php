@props([
    'panelTitle' => null,
    'panelText' => null,
    'heading',
    'description' => null,
    'footer' => null,
    'showHeaderLogo' => false,
    'showPanelLogo' => false,
])

<div class="wb-auth-shell wb-auth-split">
    <div class="wb-auth-panel wb-bg-primary">
        @if ($panelTitle)
            <h1 class="wb-auth-panel-title wb-auth-brand">
                @if ($showPanelLogo)
                    <img src="{{ asset('cms/brand/logo-mark-on-accent.svg') }}" alt="{{ config('app.name') }} logo" width="32" height="32" class="wb-auth-brand-mark wb-auth-brand-mark-on-accent">
                @endif
                <span>{{ $panelTitle }}</span>
            </h1>
        @endif

        @if ($panelText)
            <p class="wb-auth-panel-text">{{ $panelText }}</p>
        @endif
    </div>

    <div class="wb-auth-form-area">
        <div class="wb-auth-card">
            <div class="wb-auth-header">
                <h1 class="wb-auth-header-title wb-auth-brand">
                    @if ($showHeaderLogo)
                        <picture>
                            <source srcset="{{ asset('cms/brand/logo-mark-dark.svg') }}" media="(prefers-color-scheme: dark)">
                            <img src="{{ asset('cms/brand/logo-mark.svg') }}" alt="{{ config('app.name') }} logo" width="32" height="32" class="wb-auth-brand-mark wb-auth-brand-mark-sm">
                        </picture>
                    @endif
                    <span>{{ $heading }}</span>
                </h1>

                @if ($description)
                    <p class="wb-auth-header-subtitle">{{ $description }}</p>
                @endif
            </div>

            <div class="wb-auth-body wb-stack-4">
                {{ $slot }}
            </div>

            @if (filled($footer))
                <div class="wb-auth-footer">
                    {{ $footer }}
                </div>
            @endif
        </div>
    </div>
</div>
