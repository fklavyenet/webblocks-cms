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
    <div class="wb-auth-panel">
        @if ($panelTitle)
            <h1 class="wb-auth-panel-title">
                @if ($showPanelLogo)
                    <img src="{{ asset('brand/logo-64.png') }}" alt="{{ config('app.name') }} logo" width="32" height="32">
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
                <h1 class="wb-auth-header-title">
                    @if ($showHeaderLogo)
                        <img src="{{ asset('brand/logo-64.png') }}" alt="{{ config('app.name') }} logo" width="32" height="32">
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
