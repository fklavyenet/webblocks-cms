<x-guest-layout title="Confirm Password" meta-description="Confirm your password to continue securely in WebBlocks CMS.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Confirm password"
        :description="'Confirm your password to continue to this secure area of ' . config('app.name') . '.'"
    >
        <x-auth-feedback />

        <form method="POST" action="{{ route('password.confirm') }}" class="wb-stack-4">
            @csrf

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('Password')"
                :messages="$errors->get('password')"
                required
                autocomplete="current-password"
            />

            <x-primary-button class="wb-w-full">{{ __('Confirm password') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>This check keeps access to sensitive actions secure.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
