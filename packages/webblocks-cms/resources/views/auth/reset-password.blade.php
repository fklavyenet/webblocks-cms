@extends('webblocks-cms::layouts.guest', [
    'title' => 'Set New Password',
    'metaDescription' => 'Set a new password for your WebBlocks CMS account.',
])

@section('content')
    @php
        use WebBlocks\Cms\Support\WebBlocks;
    @endphp

    <div class="wb-auth-shell wb-auth-split">
        <div class="wb-auth-panel wb-bg-primary">
            <h1 class="wb-auth-panel-title wb-auth-brand">
                <x-webblocks-cms::brand-mark class="wb-auth-brand-mark wb-auth-brand-mark-on-accent" decorative="true" />
                <span>{{ WebBlocks::name() }}</span>
            </h1>

            <p class="wb-auth-panel-text">{{ WebBlocks::slogan() }}</p>
        </div>

        <div class="wb-auth-form-area">
            <div class="wb-auth-card">
                <div class="wb-auth-header">
                    <h1 class="wb-auth-header-title wb-auth-brand">
                        <x-webblocks-cms::brand-mark class="wb-auth-brand-mark wb-auth-brand-mark-sm wb-auth-brand-mark-on-surface" decorative="true" />
                        <span>Set new password</span>
                    </h1>
                    <p class="wb-auth-header-subtitle">Create a new password for your account.</p>
                </div>

                <div class="wb-auth-body wb-stack-4">
                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('webblocks.auth.password.store') }}" class="wb-stack-4">
                        @csrf

                        <input type="hidden" name="token" value="{{ $request->route('token') }}">

                        <div class="wb-field">
                            <label for="email" class="wb-label">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" class="wb-input" @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                            @error('email')
                                <div id="email_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label for="password" class="wb-label">Password</label>
                            <input id="password" type="password" name="password" required autocomplete="new-password" class="wb-input" @error('password') aria-invalid="true" aria-describedby="password_error" @enderror>
                            @error('password')
                                <div id="password_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label for="password_confirmation" class="wb-label">Confirm password</label>
                            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="wb-input" @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation_error" @enderror>
                            @error('password_confirmation')
                                <div id="password_confirmation_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="wb-btn wb-btn-primary wb-w-full">Update password</button>
                    </form>
                </div>

                <div class="wb-auth-footer">
                    <p>Need to use a different account? <a href="{{ route('webblocks.auth.login') }}">Back to sign in</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
