@extends('webblocks-cms::layouts.guest', [
    'title' => 'Sign In',
    'metaDescription' => 'Sign in to WebBlocks CMS and manage your content workspace.',
])

@section('content')
    <div class="wb-auth-shell wb-auth-shell-centered">
        <div class="wb-auth-shell-panel">
            <div class="wb-stack wb-stack-4">
                <div class="wb-stack wb-gap-1">
                    <h1 class="wb-text-2xl wb-font-semibold">Welcome back</h1>
                    <p class="wb-text-muted">Sign in to access the WebBlocks CMS admin.</p>
                </div>

                @if (session('status'))
                    <div class="wb-alert wb-alert-success">{{ session('status') }}</div>
                @endif

                @if ($errors->any())
                    <div class="wb-alert wb-alert-danger">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login') }}" class="wb-stack wb-stack-4">
                    @csrf

                    <div class="wb-field">
                        <label for="email" class="wb-label">Email address</label>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="wb-input">
                    </div>

                    <div class="wb-field">
                        <label for="password" class="wb-label">Password</label>
                        <input id="password" type="password" name="password" required autocomplete="current-password" class="wb-input">
                    </div>

                    <label class="wb-check" for="remember">
                        <input id="remember" type="checkbox" name="remember" value="1">
                        <span>Remember this device</span>
                    </label>

                    <button type="submit" class="wb-btn wb-btn-primary wb-w-full">Continue</button>
                </form>
            </div>
        </div>
    </div>
@endsection
