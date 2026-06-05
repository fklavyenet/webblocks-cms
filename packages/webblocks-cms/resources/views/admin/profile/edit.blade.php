@extends('webblocks-cms::layouts.admin', ['title' => 'Profile', 'heading' => 'Profile'])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => 'Profile',
        'description' => 'Manage your account details and password.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-grid wb-grid-2">
        <div class="wb-card">
            <div class="wb-card-header">
                <div>
                    <h2 class="wb-card-title">Profile Information</h2>
                    <p class="wb-card-description">Update your name and email address.</p>
                </div>
            </div>

            <div class="wb-card-body">
                <form id="profile-information-form" method="POST" action="{{ route('admin.profile.update') }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('PUT')

                    <div class="wb-stack-2 wb-field">
                        <label for="profile_name">Name</label>
                        <input
                            id="profile_name"
                            name="name"
                            class="wb-input @if ($errors->has('name')) wb-border-danger @endif"
                            type="text"
                            value="{{ old('name', $user->name) }}"
                            required
                            autocomplete="name"
                        >

                        @error('name')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="profile_email">Email</label>
                        <input
                            id="profile_email"
                            name="email"
                            class="wb-input @if ($errors->has('email')) wb-border-danger @endif"
                            type="email"
                            value="{{ old('email', $user->email) }}"
                            required
                            autocomplete="email"
                        >

                        @error('email')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>
                </form>
            </div>

            <div class="wb-card-footer">
                <div class="wb-action-group">
                    <button type="submit" class="wb-btn wb-btn-primary" form="profile-information-form">Save Changes</button>
                </div>
            </div>
        </div>

        <div class="wb-card">
            <div class="wb-card-header">
                <div>
                    <h2 class="wb-card-title">Change Password</h2>
                    <p class="wb-card-description">Use your current password to set a new one.</p>
                </div>
            </div>

            <div class="wb-card-body">
                <form id="profile-password-form" method="POST" action="{{ route('admin.profile.password.update') }}" class="wb-stack wb-gap-4">
                    @csrf
                    @method('PUT')

                    <div class="wb-stack-2 wb-field">
                        <label for="profile_current_password">Current password</label>
                        <div class="wb-input-group">
                            <input
                                id="profile_current_password"
                                name="current_password"
                                class="wb-input @if ($errors->has('current_password')) wb-border-danger @endif"
                                type="password"
                                required
                                autocomplete="current-password"
                            >
                            <button
                                class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                type="button"
                                data-wb-password-toggle
                                data-wb-target="#profile_current_password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                            </button>
                        </div>

                        @error('current_password')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="profile_new_password">New password</label>
                        <div class="wb-input-group">
                            <input
                                id="profile_new_password"
                                name="new_password"
                                class="wb-input @if ($errors->has('new_password')) wb-border-danger @endif"
                                type="password"
                                required
                                autocomplete="new-password"
                            >
                            <button
                                class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                type="button"
                                data-wb-password-toggle
                                data-wb-target="#profile_new_password"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                            </button>
                        </div>

                        @error('new_password')
                            <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="wb-stack-2 wb-field">
                        <label for="profile_new_password_confirmation">Confirm new password</label>
                        <div class="wb-input-group">
                            <input
                                id="profile_new_password_confirmation"
                                name="new_password_confirmation"
                                class="wb-input"
                                type="password"
                                required
                                autocomplete="new-password"
                            >
                            <button
                                class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                type="button"
                                data-wb-password-toggle
                                data-wb-target="#profile_new_password_confirmation"
                                aria-label="Show password"
                                aria-pressed="false"
                            >
                                <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="wb-card-footer">
                <div class="wb-action-group">
                    <button type="submit" class="wb-btn wb-btn-primary" form="profile-password-form">Save Changes</button>
                </div>
            </div>
        </div>
    </div>
@endsection
