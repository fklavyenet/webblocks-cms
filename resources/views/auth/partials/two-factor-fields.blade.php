@if ($password ?? false)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-password">{{ __('webblocks-cms::two-factor.password') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-password" type="password" name="current_password" autocomplete="current-password" @error('current_password') aria-invalid="true" aria-describedby="{{ $fieldPrefix }}-password-error" @enderror required>
        @error('current_password')<p class="wb-field-error" id="{{ $fieldPrefix }}-password-error">{{ $message }}</p>@enderror
    </div>
@endif
@if ($factor ?? true)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-code">{{ __('webblocks-cms::two-factor.verification_code') }}</label>
        @if ($inlineSend ?? false)<div class="wb-input-group">@endif
        <input class="wb-input" id="{{ $fieldPrefix }}-code" type="text" name="code" maxlength="64" autocomplete="one-time-code" spellcheck="false" aria-describedby="{{ $fieldPrefix }}-factor-help{{ $errors->hasAny(['code', 'recovery_code']) ? ' '.$fieldPrefix.'-code-error' : '' }}" @if ($errors->hasAny(['code', 'recovery_code'])) aria-invalid="true" @endif required>
        @if ($inlineSend ?? false)
            <button type="button" class="wb-btn wb-btn-secondary wb-input-addon-btn" data-wb-policy-send-code
                data-url="{{ route('webblocks.two-factor.send') }}" data-code-id="{{ $fieldPrefix }}-code"
                data-sending="{{ __('webblocks-cms::two-factor.sending') }}"
                data-retry="{{ __('webblocks-cms::two-factor.resend_wait') }}"
                data-failed="{{ __('webblocks-cms::two-factor.send_request_failed') }}"
                data-throttled="{{ __('webblocks-cms::two-factor.send_throttled') }}">{{ __('webblocks-cms::two-factor.request_code') }}</button>
        @endif
        @if ($inlineSend ?? false)</div>@endif
        @if ($errors->hasAny(['code', 'recovery_code']))<p class="wb-field-error" id="{{ $fieldPrefix }}-code-error">{{ $errors->first('code') ?: $errors->first('recovery_code') }}</p>@endif
        <p class="wb-text-sm wb-text-muted" id="{{ $fieldPrefix }}-factor-help">{{ __('webblocks-cms::two-factor.factor_help') }}</p>
        @if ($inlineSend ?? false)
            <p class="wb-text-sm wb-text-muted">{{ __('webblocks-cms::two-factor.policy_code_help') }}</p>
            <p class="wb-text-sm wb-text-muted" data-wb-policy-delivery-status role="status" aria-live="polite" aria-atomic="true"></p>
        @endif
    </div>
@endif
