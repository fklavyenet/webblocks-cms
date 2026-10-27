@if ($twoFactorResetTargetAllowed ?? false)
    <section class="wb-card wb-mt-4" aria-labelledby="two-factor-reset-title">
        <div class="wb-card-header"><h2 class="wb-card-title" id="two-factor-reset-title">{{ __('webblocks-cms::two-factor.reset_title') }}</h2></div>
        <div class="wb-card-body wb-stack-4">
            @if ($twoFactorResetReady)
                <p class="wb-alert wb-alert-warning">{{ __('webblocks-cms::two-factor.reset_help') }}</p>
                @include('webblocks-cms::auth.partials.two-factor-send-code', ['purpose' => 'action', 'sendTab' => 'user-reset', 'returnUser' => $managedUser->getKey()])
                <form method="POST" action="{{ route('admin.users.two-factor.reset', ['user' => $managedUser->getKey()]) }}" class="wb-stack-4">
                    @csrf
                    <div class="wb-field"><label class="wb-label" for="reset-email">{{ __('webblocks-cms::two-factor.recovery_email') }}</label><input class="wb-input" id="reset-email" name="recovery_email" type="email" maxlength="255" autocomplete="off"><p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::two-factor.recovery_email_help') }}</p></div>
                    <div class="wb-field"><label class="wb-label" for="reset-reason">{{ __('webblocks-cms::two-factor.reset_reason') }}</label><textarea class="wb-textarea" id="reset-reason" name="reason" maxlength="500" rows="3" required></textarea><p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::two-factor.reset_reason_help') }}</p></div>
                    <p>{{ __('webblocks-cms::two-factor.reset_actor_help') }}</p>
                    @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'reset', 'password' => true])
                    <div><button class="wb-btn wb-btn-danger" type="submit">{{ __('webblocks-cms::two-factor.reset_title') }}</button></div>
                </form>
            @else
                <p>{{ __('webblocks-cms::two-factor.reset_setup_first') }}</p>
                <a class="wb-btn wb-btn-secondary" href="{{ route('webblocks.two-factor.show') }}">{{ __('webblocks-cms::two-factor.manage') }}</a>
            @endif
        </div>
    </section>
@endif
