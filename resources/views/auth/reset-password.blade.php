@php
    use WebBlocks\Cms\Support\Translations\AdminLocaleResolver;
    use WebBlocks\Cms\Support\Translations\CmsTranslator;
    use WebBlocks\Cms\Support\WebBlocks;

    $authLocaleCode = app(AdminLocaleResolver::class)->locale();
    $authTranslator = app(CmsTranslator::class);
    $authText = static fn (string $key, array $replace = []) => $authTranslator->admin($key, $authLocaleCode, $replace);
@endphp

@extends('webblocks-cms::layouts.guest', [
    'title' => $authText('auth.new_password_title'),
    'metaDescription' => $authText('auth.new_password_meta'),
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
                        <span>{{ $authText('auth.new_password_heading') }}</span>
                    </h1>
                    <p class="wb-auth-header-subtitle">{{ $authText('auth.new_password_subtitle') }}</p>
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
                            <label for="email" class="wb-label">{{ $authText('auth.email') }}</label>
                            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" class="wb-input @error('email') wb-input-error @enderror" @error('email') aria-invalid="true" aria-describedby="email_error" @enderror>
                            @error('email')
                                <div id="email_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label for="password" class="wb-label">{{ $authText('auth.password') }}</label>
                            <div class="wb-input-group">
                                <input id="password" type="password" name="password" required autocomplete="new-password" class="wb-input @error('password') wb-input-error @enderror" @error('password') aria-invalid="true" aria-describedby="password_error" @enderror>
                                <button type="button"
                                        class="wb-btn wb-btn-secondary wb-btn-icon wb-input-addon-btn"
                                        data-wb-password-toggle
                                        data-wb-target="#password"
                                        data-wb-password-label-show="{{ $authText('auth.show_password') }}"
                                        data-wb-password-label-hide="{{ $authText('auth.hide_password') }}"
                                        aria-label="{{ $authText('auth.show_password') }}"
                                        aria-pressed="false">
                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            @error('password')
                                <div id="password_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="wb-field">
                            <label for="password_confirmation" class="wb-label">{{ $authText('auth.confirm_password') }}</label>
                            <div class="wb-input-group">
                                <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="wb-input @error('password_confirmation') wb-input-error @enderror" @error('password_confirmation') aria-invalid="true" aria-describedby="password_confirmation_error" @enderror>
                                <button type="button"
                                        class="wb-btn wb-btn-secondary wb-btn-icon wb-input-addon-btn"
                                        data-wb-password-toggle
                                        data-wb-target="#password_confirmation"
                                        data-wb-password-label-show="{{ $authText('auth.show_password') }}"
                                        data-wb-password-label-hide="{{ $authText('auth.hide_password') }}"
                                        aria-label="{{ $authText('auth.show_password') }}"
                                        aria-pressed="false">
                                    <i class="wb-icon wb-icon-eye" aria-hidden="true"></i>
                                </button>
                            </div>
                            @error('password_confirmation')
                                <div id="password_confirmation_error" class="wb-field-error">{{ $message }}</div>
                            @enderror
                        </div>

                        <button type="submit" class="wb-btn wb-btn-primary wb-w-full">{{ $authText('auth.update_password') }}</button>
                    </form>
                </div>

                <div class="wb-auth-footer">
                    <p>{{ $authText('auth.different_account') }} <a href="{{ route('webblocks.auth.login') }}">{{ $authText('auth.back_to_sign_in') }}</a>.</p>
                </div>
            </div>
        </div>
    </div>
@endsection
