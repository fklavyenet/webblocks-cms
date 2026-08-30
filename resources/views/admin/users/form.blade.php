@php
    $adminLocale = app(\WebBlocks\Cms\Support\Translations\AdminLocaleResolver::class)->locale(request()->user());
    $adminTranslator = app(\WebBlocks\Cms\Support\Translations\CmsTranslator::class);
    $adminText = fn (string $key, array $replace = []) => $adminTranslator->get('admin.users.'.$key, $adminLocale, $replace);
@endphp

@extends('webblocks-cms::layouts.admin', ['title' => $pageTitle, 'heading' => $pageTitle])

@section('content')
    @include('webblocks-cms::admin.partials.page-header', [
        'title' => $pageTitle,
        'description' => $adminText('form_description'),
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
                                <label for="user_name">{{ $adminText('name') }}</label>
                                <input id="user_name" name="name" class="wb-input" type="text" value="{{ old('name', $managedUser->name) }}" required>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="user_email">{{ $adminText('email') }}</label>
                                <input id="user_email" name="email" class="wb-input" type="email" value="{{ old('email', $managedUser->email) }}" required>
                            </div>

                            <div class="wb-stack-2 wb-field">
                                <label for="password">{{ $managedUser->exists ? $adminText('new_password') : $adminText('password') }}</label>
                                <div class="wb-input-group">
                                    <input
                                        id="password"
                                        name="password"
                                        class="wb-input @if ($errors->has('password')) wb-border-danger @endif"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="{{ $managedUser->exists ? $adminText('password_keep_placeholder') : $adminText('password') }}"
                                        @required(! $managedUser->exists)
                                    >
                                    <button
                                        class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                        type="button"
                                        data-wb-password-toggle
                                        data-wb-target="#password"
                                        aria-label="{{ $adminText('show_password') }}"
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
                                <div class="wb-text-sm wb-text-muted">{{ $adminText('password_keep_help') }}</div>
                            @endif

                            <div class="wb-stack-2 wb-field">
                                <label for="password_confirmation">{{ $managedUser->exists ? $adminText('confirm_new_password') : $adminText('confirm_password') }}</label>
                                <div class="wb-input-group">
                                    <input
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        class="wb-input @if ($errors->has('password_confirmation')) wb-border-danger @endif"
                                        type="password"
                                        autocomplete="new-password"
                                        placeholder="{{ $managedUser->exists ? $adminText('confirm_new_password_placeholder') : $adminText('confirm_password_placeholder') }}"
                                        @required(! $managedUser->exists)
                                    >
                                    <button
                                        class="wb-btn wb-btn-secondary wb-input-addon-btn wb-btn-icon"
                                        type="button"
                                        data-wb-password-toggle
                                        data-wb-target="#password_confirmation"
                                        aria-label="{{ $adminText('show_password') }}"
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
                                    <label for="user_role">{{ $adminText('role') }}</label>
                                    <select id="user_role" name="role" class="wb-select">
                                        @foreach (\App\Models\User::roles() as $role)
                                            <option value="{{ $role }}" @selected(old('role', $managedUser->normalizedRole()) === $role)>{{ str($role)->replace('_', ' ')->title() }}</option>
                                        @endforeach
                                    </select>
                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('role_help') }}</div>
                                </div>

                                @php($selectedRole = old('role', $managedUser->normalizedRole()))

                                <div class="wb-stack wb-gap-1">
                                    <div>{{ $adminText('assigned_sites') }}</div>
                                    <div class="wb-stack wb-gap-1">
                                        @foreach ($sites as $site)
                                            <label class="wb-nowrap">
                                                <input type="checkbox" name="site_ids[]" value="{{ $site->id }}" @checked(in_array($site->id, old('site_ids', $managedUser->exists ? $managedUser->accessibleSiteIds()->all() : []), true))>
                                                <span>{{ $site->name }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    @if ($selectedRole === \App\Models\User::ROLE_SUPER_ADMIN)
                                        <div class="wb-text-sm wb-text-muted">{{ $adminText('super_admin_sites_help') }}</div>
                                    @else
                                        <div class="wb-text-sm wb-text-muted">{{ $adminText('assigned_sites_help') }}</div>
                                    @endif
                                </div>

                                <label class="wb-nowrap">
                                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $managedUser->exists ? $managedUser->is_active : true))>
                                    <span>{{ $adminText('active_account') }}</span>
                                </label>

                                <div class="wb-text-sm wb-text-muted">{{ $adminText('inactive_help') }}</div>

                                @if ($managedUser->exists)
                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('last_login_label') }} <strong>{{ $managedUser->last_login_at?->format('Y-m-d H:i') ?? $adminText('never') }}</strong></div>
                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('created_label') }} <strong>{{ $managedUser->created_at?->format('Y-m-d H:i') }}</strong></div>
                                @endif

                                @if (! empty($deleteBlockedMessage))
                                    <div class="wb-text-sm wb-text-muted">{{ $adminText('delete_protection') }} {{ $deleteBlockedMessage }}</div>
                                @endif

                                @if (! empty($updateBlockedMessage))
                                    <div class="wb-alert wb-alert-warning">
                                        <div>
                                            <div class="wb-alert-title">{{ $adminText('protected_super_admin') }}</div>
                                            <div>{{ $updateBlockedMessage }} {{ $adminText('protected_super_admin_help') }}</div>
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

@push('admin-scripts')
@endpush
