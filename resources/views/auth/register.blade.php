<x-guest-layout title="Create Account" meta-description="Create an administrator account for WebBlocks CMS.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Create account"
        :description="'Create a new administrator account for ' . config('app.name') . '.'"
    >
        <x-auth-feedback />

        <form method="POST" action="{{ route('register') }}" class="wb-stack-4">
            @csrf

            <div class="wb-field">
                <x-input-label for="name" :value="__('Full name')" />
                <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                <x-input-error :messages="$errors->get('name')" />
            </div>

            <div class="wb-field">
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
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

            <x-primary-button class="wb-w-full">{{ __('Create account') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>Already have an account? <a href="{{ route('login') }}">Back to sign in</a>.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
