<section class="wb-stack wb-stack-4">
    <div class="wb-text-sm wb-text-muted">
        {{ __('Deleting your account permanently removes all associated resources and data.') }}
    </div>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Delete Account') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="wb-stack wb-stack-4">
            @csrf
            @method('delete')

            <h2>
                {{ __('Are you sure you want to delete your account?') }}
            </h2>

            <p class="wb-text-sm wb-text-muted">
                {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
            </p>

            <x-auth-password-field
                id="password"
                name="password"
                :label="__('Password')"
                :messages="$errors->userDeletion->get('password')"
                :placeholder="__('Password')"
                wrapper-class="wb-form-group"
                label-class="sr-only"
            />

            <div class="wb-cluster wb-cluster-end wb-cluster-2">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button>
                    {{ __('Delete Account') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
