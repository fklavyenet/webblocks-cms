@extends('webblocks-cms::layouts.guest', [
    'title' => 'Sign In',
    'metaDescription' => 'Sign in to WebBlocks CMS and manage your content workspace.',
])

@section('content')
    @php
        use WebBlocks\Cms\Support\WebBlocks;
    @endphp

    <div class="wb-auth-shell wb-auth-split">
        <div class="wb-auth-panel wb-bg-primary">
            <h1 class="wb-auth-panel-title wb-auth-brand">
                <span class="wb-auth-brand-mark wb-auth-brand-mark-mask wb-auth-brand-mark-on-accent" role="img" aria-label="{{ WebBlocks::name() }} logo"></span>
                <span>{{ WebBlocks::name() }}</span>
            </h1>

            <p class="wb-auth-panel-text">{{ WebBlocks::slogan() }}</p>
        </div>

        <div class="wb-auth-form-area">
            <div class="wb-auth-card">
                <div class="wb-auth-header">
                    <h1 class="wb-auth-header-title wb-auth-brand">
                        <span class="wb-auth-brand-logo" role="img" aria-label="{{ WebBlocks::name() }} logo">
                            <img src="{{ asset('cms/brand/logo-mark.svg') }}" alt="" aria-hidden="true" class="wb-auth-brand-mark wb-auth-brand-mark-light wb-auth-brand-mark-sm">
                            <img src="{{ asset('cms/brand/logo-mark-dark.svg') }}" alt="" aria-hidden="true" class="wb-auth-brand-mark wb-auth-brand-mark-dark wb-auth-brand-mark-sm">
                        </span>
                        <span>Welcome back</span>
                    </h1>
                    <p class="wb-auth-header-subtitle">Sign in to {{ WebBlocks::name() }} to access your content workspace.</p>
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

                    <form method="POST" action="{{ route('login') }}" class="wb-stack-4">
                        @csrf

                        <div class="wb-field">
                            <label for="email" class="wb-label">Email address</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="wb-input" @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                            @error('email')
                                <div id="email_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label for="password" class="wb-label">Password</label>
                            <div class="wb-input-group wb-password-field" data-password-field>
                                <input id="password" type="password" name="password" required autocomplete="current-password" class="wb-input" @error('password') aria-invalid="true" aria-describedby="password_error" @enderror data-password-input>
                                <button
                                    id="password_toggle"
                                    type="button"
                                    class="wb-btn wb-btn-secondary wb-btn-icon wb-input-addon-btn wb-password-field-toggle"
                                    data-password-toggle
                                    aria-label="Show password"
                                    aria-controls="password"
                                    aria-pressed="false"
                                >
                                    <i class="wb-icon wb-icon-eye" aria-hidden="true" data-password-toggle-icon></i>
                                    <span class="wb-sr-only" data-password-toggle-label>Show password</span>
                                </button>
                            </div>
                            @error('password')
                                <div id="password_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-split">
                            <label class="wb-check" for="remember">
                                <input id="remember" type="checkbox" name="remember" value="1">
                                <span>Remember this device</span>
                            </label>

                            @if (Route::has('webblocks.auth.password.request'))
                                <a href="{{ route('webblocks.auth.password.request') }}" class="wb-action-link">Forgot password</a>
                            @endif
                        </div>

                        <button type="submit" class="wb-btn wb-btn-primary wb-w-full">Continue</button>
                    </form>
                </div>

                @if (Route::has('webblocks.auth.register'))
                    <div class="wb-auth-footer">
                        <p>Need an account? <a href="{{ route('webblocks.auth.register') }}">Create one</a>.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
