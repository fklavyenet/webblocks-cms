@if ($password ?? false)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-password">{{ __('webblocks-cms::two-factor.password') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-password" type="password" name="current_password" autocomplete="current-password" required>
    </div>
@endif
@if ($factor ?? true)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-code">{{ __('webblocks-cms::two-factor.verification_code') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-code" type="text" name="code" maxlength="64" autocomplete="one-time-code" spellcheck="false" aria-describedby="{{ $fieldPrefix }}-factor-help" required>
        <p class="wb-text-sm wb-text-muted" id="{{ $fieldPrefix }}-factor-help">{{ __('webblocks-cms::two-factor.factor_help') }}</p>
    </div>
@endif
