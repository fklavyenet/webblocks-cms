<x-guest-layout title="Set New Password" meta-description="Set a new password for your WebBlocks CMS account.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Set new password"
        description="Create a new password for your account."
    >
        <x-auth-feedback />

        <form method="POST" action="{{ route('password.store') }}" class="wb-stack-4">
            @csrf

            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="wb-field">
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email', $request->email)" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('Password')"
                :messages="$errors->get('password')"
                required
                autocomplete="new-password"
            />

            <x-auth-password-field
                id="password_confirmation"
                name="password_confirmation"
                :label="__('Confirm password')"
                :messages="$errors->get('password_confirmation')"
                required
                autocomplete="new-password"
            />

            <x-primary-button class="wb-w-full">{{ __('Update password') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>Need to use a different account? <a href="{{ route('login') }}">Back to sign in</a>.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
