@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => 'Users stay install-level accounts. Role and assigned sites now determine which CMS areas each person can access.',
    ])

    @include('webblocks-cms::admin.partials.flash')

    <div class="wb-card">
        <form method="POST" action="{{ $formAction }}" class="wb-stack wb-gap-0">
            @csrf
            @if ($formMethod !== 'POST')
                @method($formMethod)
            @endif

            <div class="wb-card-body">
                <div class="wb-stack wb-gap-4">
                    <div class="wb-grid wb-grid-2">
                        <div class="wb-stack wb-gap-3">
                            <div class="wb-stack-2 wb-field">
                                <label for="user_name">Name</label>
                                <input id="user_name" name="name" class="wb-input" type="text" value="{{ old('name', $managedUser->name) }}" required>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="user_email">Email</label>
                                <input id="user_email" name="email" class="wb-input" type="email" value="{{ old('email', $managedUser->email) }}" required>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="password">{{ $managedUser->exists ? 'New Password' : 'Password' }}</label>
                                <div class="wb-input-group">
                                    <input
                                        id="password"
                                        name="password"
                                        class="wb-input @if ($errors->has('password')) wb-border-danger @endif"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="{{ $managedUser->exists ? 'Leave blank to keep current password' : 'Password' }}"
                                        @required(! $managedUser->exists)
                                    >
                                    <button
                                        class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                        type="button"
                                        data-wb-password-toggle
                                        data-wb-target="#password"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                    </button>
                                </div>

                                @error('password')
                                    <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                                @enderror
                            </div>

                            @if ($managedUser->exists)
                                <div class="wb-text-sm wb-text-muted">Leave both password fields blank to keep the current password unchanged.</div>
                            @endif

                            <div class="wb-stack-2 wb-field">
                                <label for="password_confirmation">{{ $managedUser->exists ? 'Confirm New Password' : 'Confirm Password' }}</label>
                                <div class="wb-input-group">
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        class="wb-input @if ($errors->has('password_confirmation')) wb-border-danger @endif"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="{{ $managedUser->exists ? 'Confirm new password' : 'Confirm password' }}"
                                        @required(! $managedUser->exists)
                                    >
                                    <button
                                        class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                        type="button"
                                        data-wb-password-toggle
                                        data-wb-target="#password_confirmation"
                                        aria-label="Show password"
                                        aria-pressed="false"
                                    >
                                        <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                    </button>
                                </div>

                                @error('password_confirmation')
                                    <div class="wb-text-sm wb-text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="wb-card wb-card-muted">
                            <div class="wb-card-body wb-stack wb-gap-2">
                                <div class="wb-stack wb-gap-1">
                                    <label for="user_role">Role</label>
                                    <select id="user_role" name="role" class="wb-select">
                                        @foreach (\App\Models\User::roles() as $role)
                                            <option value="{{ $role }}" @selected(old('role', $managedUser->normalizedRole()) === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <div class="wb-text-sm wb-text-muted">Super admins have full system access. Site admins and editors are limited to assigned sites.</div>
                                </div>

                                @php($selectedRole = old('role', $managedUser->normalizedRole()))

                                <div class="wb-stack wb-gap-1">
                                    <div>Assigned sites</div>
                                    <div class="wb-stack wb-gap-1">
                                        @foreach ($sites as $site)
                                            <label class="wb-nowrap">
                                                <input type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, old('site_ids', $managedUser->exists ? $managedUser->accessibleSiteIds()->all() : []), true))>
                                                <span>{{ $site->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($selectedRole === \App\Models\User::ROLE_SUPER_ADMIN)
                                        <div class="wb-text-sm wb-text-muted">Super admins do not require assigned sites and always have access to every site.</div>
                                    @else
                                        <div class="wb-text-sm wb-text-muted">Select at least one site for site admins and editors.</div>
                                    @endif
                                </div>

                                <label class="wb-nowrap">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser->exists ? $managedUser->is_active : true))>
                                    <span>Active account</span>
                                </label>

                                <div class="wb-text-sm wb-text-muted">Inactive users cannot sign in. Users management and install-level system screens stay restricted to super admins.</div>

                                @if ($managedUser->exists)
                                    <div class="wb-text-sm wb-text-muted">Last login: <strong>{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? 'Never' }}</strong></div>
                                    <div class="wb-text-sm wb-text-muted">Created: <strong>{{ $managedUser->created_at?->format('Y-m-d H:i') }}</strong></div>
                                @endif

                                @if (! empty($deleteBlockedMessage))
                                    <div class="wb-text-sm wb-text-muted">Delete protection: {{ $deleteBlockedMessage }}</div>
                                @endif

                                @if (! empty($updateBlockedMessage))
                                    <div class="wb-alert wb-alert-warning">
                                        <div>
                                            <div class="wb-alert-title">Protected Super Admin</div>
                                            <div>{{ $updateBlockedMessage }} Keep at least one active super admin available.</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="wb-card-footer">
                <x-webblocks-cms::admin.form-actions :cancel-url="route('admin.users.index')" />
            </div>
        </form>
    </div>
@endsection
