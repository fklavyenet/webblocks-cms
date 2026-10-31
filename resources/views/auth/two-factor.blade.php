@php
    $twoFactorTab = request()->query('tab', 'recovery');
    if (! in_array($twoFactorTab, ['recovery', 'replace', 'disable'], true) || ($required && $twoFactorTab === 'disable')) {
        $twoFactorTab = 'recovery';
    }
@endphp

@extends($layout, ['title' => __('webblocks-cms::two-factor.title'), 'heading' => __('webblocks-cms::two-factor.title')])

@section('content')
<div class="{{ $inPanel ? 'wb-stack-4' : 'wb-container wb-py-6 wb-stack-4' }}">
    @include('webblocks-cms::admin.partials.page-header', ['title' => __('webblocks-cms::two-factor.title'), 'description' => __('webblocks-cms::two-factor.description')])
    @if ($errors->any())
        <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    @if (session('status'))
        <div class="wb-alert wb-alert-success" role="status">{{ session('status') }}</div>
    @endif
    @if ($required)
        <div class="wb-alert wb-alert-info wb-stack-2">
            <p>{{ __('webblocks-cms::two-factor.required_help') }}</p>
            @if (! $enabled)<p>{{ __('webblocks-cms::two-factor.required_setup_help') }}</p>@endif
            @if ($canConfigurePolicy)
                <p>{{ __('webblocks-cms::two-factor.policy_manage_help') }}</p>
                <a class="wb-action-link" href="{{ route('admin.system.settings.edit', ['tab' => 'security']) }}#two-factor-policy">{{ __('webblocks-cms::two-factor.policy_manage') }}</a>
            @endif
        </div>
    @endif
    @if ($recoveryCodes !== [])
        <section class="wb-card" aria-labelledby="new-recovery-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="new-recovery-title">{{ __('webblocks-cms::two-factor.save_codes') }}</h2></div>
            <div class="wb-card-body wb-stack-4">
                <p>{{ __('webblocks-cms::two-factor.save_codes_help') }}</p>
                <ul>@foreach ($recoveryCodes as $recoveryCode)<li><code>{{ $recoveryCode }}</code></li>@endforeach</ul>
            </div>
        </section>
    @endif
    @if (! $enabled || $setupEmail !== null)
        <section class="wb-card" aria-labelledby="setup-title">
            <div class="wb-card-header"><h2 class="wb-card-title" id="setup-title">{{ __('webblocks-cms::two-factor.setup') }}</h2></div>
            <div class="wb-card-body wb-stack-4">
                @if ($setupEmail !== null)
                    <p>{{ __('webblocks-cms::two-factor.setup_help') }}</p>
                    <p>{{ __('webblocks-cms::two-factor.recipient', ['email' => $setupEmail]) }}</p>
                    @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'setup', 'sendTab' => 'recovery'])
                    <form method="POST" action="{{ route('webblocks.two-factor.confirm') }}" class="wb-stack-4">
                        @csrf
                        <div class="wb-field"><label class="wb-label" for="setup-code">{{ __('webblocks-cms::two-factor.code') }}</label><input class="wb-input" id="setup-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required></div>
                        <button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::two-factor.enable') }}</button>
                    </form>
                @else
                    <p>{{ __('webblocks-cms::two-factor.start_help') }}</p>
                    <form method="POST" action="{{ route('webblocks.two-factor.start') }}" class="wb-stack-4">
                        @csrf
                        @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'setup', 'password' => true, 'factor' => false])
                        <button class="wb-btn wb-btn-primary" type="submit">{{ __('webblocks-cms::two-factor.start') }}</button>
                    </form>
                @endif
            </div>
        </section>
    @else
        <div class="wb-card"><div class="wb-card-body wb-stack-4">
            <p>{{ __('webblocks-cms::two-factor.recipient', ['email' => $recipient]) }}</p>
        </div></div>
        <div class="wb-tabs" data-wb-tabs>
            <div class="wb-tabs-nav" role="tablist" aria-label="{{ __('webblocks-cms::two-factor.title') }}">
                <button class="wb-tabs-btn {{ $twoFactorTab === 'recovery' ? 'is-active' : '' }}" type="button" data-wb-tab="two-factor-recovery" aria-selected="{{ $twoFactorTab === 'recovery' ? 'true' : 'false' }}" @if ($twoFactorTab !== 'recovery') tabindex="-1" @endif>{{ __('webblocks-cms::two-factor.recovery') }}</button>
                <button class="wb-tabs-btn {{ $twoFactorTab === 'replace' ? 'is-active' : '' }}" type="button" data-wb-tab="two-factor-replace" aria-selected="{{ $twoFactorTab === 'replace' ? 'true' : 'false' }}" @if ($twoFactorTab !== 'replace') tabindex="-1" @endif>{{ __('webblocks-cms::two-factor.replace') }}</button>
                @if (! $required)<button class="wb-tabs-btn {{ $twoFactorTab === 'disable' ? 'is-active' : '' }}" type="button" data-wb-tab="two-factor-disable" aria-selected="{{ $twoFactorTab === 'disable' ? 'true' : 'false' }}" @if ($twoFactorTab !== 'disable') tabindex="-1" @endif>{{ __('webblocks-cms::two-factor.disable') }}</button>@endif
            </div>
            <div class="wb-tabs-panels">
                <section class="wb-tabs-panel {{ $twoFactorTab === 'recovery' ? 'is-active' : '' }}" id="two-factor-recovery">
                    <div class="wb-card"><div class="wb-card-body wb-stack-4">
                        <p>{{ __('webblocks-cms::two-factor.enabled') }}</p>
                        <p>{{ __('webblocks-cms::two-factor.remaining', ['count' => $recoveryCount]) }}</p>
                        <p>{{ __('webblocks-cms::two-factor.regenerate_help') }}</p>
                        @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'action', 'sendTab' => 'recovery'])
                        <form method="POST" action="{{ route('webblocks.two-factor.regenerate') }}" class="wb-stack-4">
                            @csrf
                            @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'regenerate', 'password' => true])
                            <button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.regenerate') }}</button>
                        </form>
                    </div></div>
                </section>
                <section class="wb-tabs-panel {{ $twoFactorTab === 'replace' ? 'is-active' : '' }}" id="two-factor-replace">
                    <div class="wb-card"><div class="wb-card-body wb-stack-4">
                        <p>{{ __('webblocks-cms::two-factor.replace_help') }}</p>
                        @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'action', 'sendTab' => 'replace'])
                        <form method="POST" action="{{ route('webblocks.two-factor.start') }}" class="wb-stack-4">
                            @csrf
                            @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'replace', 'password' => true])
                            <button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.replace') }}</button>
                        </form>
                    </div></div>
                </section>
                @if (! $required)
                    <section class="wb-tabs-panel {{ $twoFactorTab === 'disable' ? 'is-active' : '' }}" id="two-factor-disable">
                        <div class="wb-card"><div class="wb-card-body wb-stack-4">
                            <p>{{ __('webblocks-cms::two-factor.disable_help') }}</p>
                            @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'action', 'sendTab' => 'disable'])
                        <form method="POST" action="{{ route('webblocks.two-factor.disable') }}" class="wb-stack-4">
                                @csrf @method('DELETE')
                                @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'disable', 'password' => true])
                                <button class="wb-btn wb-btn-danger" type="submit">{{ __('webblocks-cms::two-factor.disable') }}</button>
                            </form>
                        </div></div>
                    </section>
                @endif
            </div>
        </div>
    @endif
    <div class="wb-action-group">
        @if ($inPanel)
            <a class="wb-btn wb-btn-secondary" href="{{ route('admin.profile.edit') }}">{{ __('webblocks-cms::two-factor.back_profile') }}</a>
        @else
            @if ($enabled || ! $required)<a class="wb-btn wb-btn-secondary" href="{{ $continueUrl }}">{{ __('webblocks-cms::two-factor.continue') }}</a>@endif
            <form method="POST" action="{{ route('webblocks.auth.logout') }}">@csrf<button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.logout') }}</button></form>
        @endif
    </div>
</div>
@endsection
