<x-guest-layout title="Reset Password" meta-description="Request a password reset link for your WebBlocks CMS account.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Reset password"
        description="Enter your email address and we will send you a password reset link."
    >
        <x-auth-feedback />

        <form method="POST" action="{{ route('password.email') }}" class="wb-stack-4">
            @csrf

            <div class="wb-field">
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-primary-button class="wb-w-full">{{ __('Send reset link') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>Remembered your password? <a href="{{ route('login') }}">Back to sign in</a>.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
