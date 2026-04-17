<x-guest-layout title="Verify Email" meta-description="Verify your email address to continue using WebBlocks CMS.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Verify email"
        :description="'Confirm your email address to continue using ' . config('app.name') . '.'"
    >
        @if (session('status') == 'verification-link-sent')
            <div class="wb-alert wb-alert-success">
                <div>{{ __('A new verification link has been sent to the email address you provided during registration.') }}</div>
            </div>
        @endif

        <p class="wb-text-sm wb-text-muted">
            {{ __('We sent a verification link to your inbox. Open the email and follow the link before continuing.') }}
        </p>

        <form method="POST" action="{{ route('verification.send') }}" class="wb-stack-4">
            @csrf

            <x-primary-button class="wb-w-full">{{ __('Resend verification email') }}</x-primary-button>
        </form>

        <x-slot:footer>
            <div class="wb-split">
                <p>Did not receive it? Check spam, then request another email.</p>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="wb-btn wb-btn-secondary">{{ __('Log out') }}</button>
                </form>
            </div>
        </x-slot:footer>
    </x-auth-shell>
</x-guest-layout>
