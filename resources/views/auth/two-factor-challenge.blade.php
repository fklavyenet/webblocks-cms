@extends('webblocks-cms::layouts.guest', ['title' => __('webblocks-cms::two-factor.challenge')])

@section('content')
<div class="wb-auth-shell">
    <div class="wb-auth-card">
        <div class="wb-auth-header">
            <h1 class="wb-auth-header-title">{{ __('webblocks-cms::two-factor.challenge') }}</h1>
            <p class="wb-auth-header-subtitle">{{ __('webblocks-cms::two-factor.challenge_help') }}</p>
        </div>
        <div class="wb-auth-body wb-stack-4">
            @if ($errors->any())
                <div class="wb-alert wb-alert-danger" role="alert">{{ $errors->first() }}</div>
            @endif
            @if (session('status'))<div class="wb-alert wb-alert-success" role="status">{{ session('status') }}</div>@endif
            @if (session('email_error'))<div class="wb-alert wb-alert-danger" role="alert">{{ session('email_error') }}</div>@endif
            <p>{{ __('webblocks-cms::two-factor.recipient', ['email' => $recipient]) }}</p>
            <p role="status">{{ __('webblocks-cms::two-factor.'.($emailCodeActive ? 'challenge_email_sent' : 'challenge_request_email')) }}</p>
            <form method="POST" action="{{ route('webblocks.two-factor.sendChallenge') }}">
                @csrf
                <button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.'.($emailCodeActive ? 'send_code' : 'request_code')) }}</button>
            </form>
            <form id="two-factor-verify" method="POST" action="{{ route('webblocks.two-factor.verify') }}" class="wb-stack-4">
                @csrf
                @include('webblocks-cms::auth.partials.two-factor-fields', ['fieldPrefix' => 'challenge'])
            </form>
            <div class="wb-action-group">
                <button class="wb-btn wb-btn-primary" type="submit" form="two-factor-verify">{{ __('webblocks-cms::two-factor.verify') }}</button>
                @if (auth('web')->check())
                    <form method="POST" action="{{ route('webblocks.auth.logout') }}">@csrf<button class="wb-btn wb-btn-secondary" type="submit">{{ __('webblocks-cms::two-factor.logout') }}</button></form>
                @else
                    <a class="wb-btn wb-btn-secondary" href="{{ route('webblocks.auth.login') }}">{{ __('webblocks-cms::two-factor.back_login') }}</a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
