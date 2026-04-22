<x-guest-layout title="Sign In" meta-description="Sign in to WebBlocks CMS and manage your content workspace.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Welcome back"
        :description="'Sign in to ' . config('app.name') . ' to access your content workspace.'"
    >
        <x-auth-feedback />

        <form method="POST" action="{{ route('login') }}" class="wb-stack-4">
            @csrf

            <div class="wb-field">
                <x-input-label for="email" :value="__('Email address')" />
                <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
                <x-input-error :messages="$errors->get('email')" />
            </div>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('Password')"
                :messages="$errors->get('password')"
                required
                autocomplete="current-password"
            />

            <div class="wb-split">
                <label class="wb-check" for="remember">
                    <input id="remember" type="checkbox" name="remember">
                    <span>Remember this device</span>
                </label>

                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="wb-action-link">Forgot password</a>
                @endif
            </div>

            <x-primary-button class="wb-w-full">{{ __('Continue') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>
                @if (Route::has('register'))
                    Need an account? <a href="{{ route('register') }}">Create one</a>.
                @endif
            </p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
