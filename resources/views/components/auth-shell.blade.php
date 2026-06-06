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
                    <span class="wb-auth-brand-mark wb-auth-brand-mark-mask wb-auth-brand-mark-on-accent" role="img" aria-label="{{ config('app.name') }} logo"></span>
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
                        <span class="wb-auth-brand-logo" role="img" aria-label="{{ config('app.name') }} logo">
                            <img src="{{ asset('cms/brand/logo-mark.svg') }}" alt="" aria-hidden="true" class="wb-auth-brand-mark wb-auth-brand-mark-light wb-auth-brand-mark-sm">
                            <img src="{{ asset('cms/brand/logo-mark-dark.svg') }}" alt="" aria-hidden="true" class="wb-auth-brand-mark wb-auth-brand-mark-dark wb-auth-brand-mark-sm">
                        </span>
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
