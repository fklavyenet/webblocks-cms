<x-guest-layout title="Create First Admin" meta-description="Create the first super admin account for WebBlocks CMS.">
    <x-auth-shell
        :panel-title="config('app.name')"
        :panel-text="config('app.slogan')"
        :show-panel-logo="true"
        :show-header-logo="true"
        heading="Create the first super admin"
        description="This account becomes the initial active super admin for the install."
    >
        <x-auth-feedback />

        @include('install.partials.steps', ['steps' => $steps])

        <form method="POST" action="{{ route('install.admin.store') }}" class="wb-stack wb-gap-4">
            @csrf

            <div class="wb-card">
                <div class="wb-card-body wb-stack wb-gap-3">
                    <div class="wb-field">
                        <x-input-label for="name" value="Full name" />
                        <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
                        <x-input-error :messages="$errors->get('name')" />
                    </div>

                    <div class="wb-field">
                        <x-input-label for="email" value="Email address" />
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
                </div>
            </div>

            <div class="wb-row wb-row-gap-2">
                <a href="{{ route('install.core') }}" class="wb-btn wb-btn-secondary">Back</a>
                <x-primary-button>Create super admin</x-primary-button>
            </div>
        </form>
    </x-auth-shell>
</x-guest-layout>
