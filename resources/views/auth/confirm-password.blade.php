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

            <div class="wb-field">
                <x-input-label for="password" :value="__('Password')" />
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                <x-input-error :messages="$errors->get('password')" />
            </div>

            <x-primary-button class="wb-w-full">{{ __('Confirm password') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <p>This check keeps access to sensitive actions secure.</p>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
