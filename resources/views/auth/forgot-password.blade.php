@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    use WebBlocks\Cms\Support\WebBlocks;

    $authLocaleCode = app(AdminLocaleResolver::class)->locale();
    $authTranslator = app(CmsTranslator::class);
    $authText = static fn (string $key, array $replace = []) => $authTranslator->admin($key, $authLocaleCode, $replace);
@endphp

@extends('webblocks-cms::layouts.guest', [
    'title' => $authText('auth.reset_title'),
    'metaDescription' => $authText('auth.reset_meta'),
    'guestLocaleCode' => $authLocaleCode,
])

@section('content')
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
                        <span>{{ $authText('auth.reset_heading') }}</span>
                    </h1>
                    <p class="wb-auth-header-subtitle">{{ $authText('auth.reset_subtitle') }}</p>
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
                            <label for="email" class="wb-label">{{ $authText('auth.email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" class="wb-input @error('email') wb-input-error @enderror" @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                            @error('email')
                                <div id="email_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="wb-btn wb-btn-primary wb-w-full">{{ $authText('auth.send_reset_link') }}</button>
                    </form>
                </div>

                <div class="wb-auth-footer">
                    <p>{{ $authText('auth.remembered_password') }} <a href="{{ route('webblocks.auth.login') }}">{{ $authText('auth.back_to_sign_in') }}</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
