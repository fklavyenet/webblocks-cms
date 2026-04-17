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

            <div class="wb-field">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <div class="wb-field">
                <x-input-label for="password_confirmation" :value="__('Confirm password')" />
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <x-primary-button class="wb-w-full">{{ __('Update password') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>Need to use a different account? <a href="{{ route('login') }}">Back to sign in</a>.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
