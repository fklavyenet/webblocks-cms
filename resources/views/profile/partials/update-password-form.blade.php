<section class="wb-stack wb-stack-4">
    <div class="wb-text-sm wb-text-muted">
        {{ __('Use a strong password to keep your account secure.') }}
    </div>

    <form method="post" action="{{ route('password.update') }}" class="wb-stack wb-stack-4">
        @csrf
        @method('put')

        <x-auth-password-field
            id="update_password_current_password"
            name="current_password"
            :label="__('Current Password')"
            :messages="$errors->updatePassword->get('current_password')"
            autocomplete="current-password"
            wrapper-class="wb-form-group"
        />

        <x-auth-password-field
            id="update_password_password"
            name="password"
            :label="__('New Password')"
            :messages="$errors->updatePassword->get('password')"
            autocomplete="new-password"
            wrapper-class="wb-form-group"
        />

        <x-auth-password-field
            id="update_password_password_confirmation"
            name="password_confirmation"
            :label="__('Confirm Password')"
            :messages="$errors->updatePassword->get('password_confirmation')"
            autocomplete="new-password"
            wrapper-class="wb-form-group"
        />

        <div class="wb-cluster wb-cluster-2">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="wb-text-sm wb-text-muted"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
