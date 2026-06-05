@extends('webblocks-cms::layouts.guest', [
    'title' => 'Reset Password',
    'metaDescription' => 'Request a password reset link for your WebBlocks CMS account.',
])

@section('content')
    @php
        use WebBlocks\Cms\Support\WebBlocks;
    @endphp

    <div class="wb-auth-shell wb-auth-split">
        <div class="wb-auth-panel wb-bg-primary">
            <h1 class="wb-auth-panel-title wb-auth-brand">
                <img src="{{ asset('cms/brand/logo-mark-on-accent.svg') }}" alt="{{ WebBlocks::name() }} logo" width="32" height="32" class="wb-auth-brand-mark wb-auth-brand-mark-on-accent">
                <span>{{ WebBlocks::name() }}</span>
            </h1>

            <p class="wb-auth-panel-text">{{ WebBlocks::slogan() }}</p>
        </div>

        <div class="wb-auth-form-area">
            <div class="wb-auth-card">
                <div class="wb-auth-header">
                    <h1 class="wb-auth-header-title wb-auth-brand">
                        <picture>
                            <source srcset="{{ asset('cms/brand/logo-mark-dark.svg') }}" media="(prefers-color-scheme: dark)">
                            <img src="{{ asset('cms/brand/logo-mark.svg') }}" alt="{{ WebBlocks::name() }} logo" width="32" height="32" class="wb-auth-brand-mark wb-auth-brand-mark-sm">
                        </picture>
                        <span>Reset password</span>
                    </h1>
                    <p class="wb-auth-header-subtitle">Enter your email address and we will send you a password reset link.</p>
                </div>

                <div class="wb-auth-body wb-stack-4">
                    @if (session('status'))
                        <div class="wb-alert wb-alert-success">
                            <div>{{ session('status') }}</div>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="wb-alert wb-alert-danger">
                            <div>{{ $errors->first() }}</div>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('webblocks.auth.password.email') }}" class="wb-stack-4">
                        @csrf

                        <div class="wb-field">
                            <label for="email" class="wb-label">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="wb-input" @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                            @error('email')
                                <div id="email_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="wb-btn wb-btn-primary wb-w-full">Send reset link</button>
                    </form>
                </div>

                <div class="wb-auth-footer">
                    <p>Remembered your password? <a href="{{ route('login') }}">Back to sign in</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
