@if ($password ?? false)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-password">{{ __('webblocks-cms::two-factor.password') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-password" type="password" name="current_password" autocomplete="current-password" required>
    </div>
@endif
@if ($factor ?? true)
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-code">{{ __('webblocks-cms::two-factor.code') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-code" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" aria-describedby="{{ $fieldPrefix }}-factor-help">
    </div>
    <div class="wb-field">
        <label class="wb-label" for="{{ $fieldPrefix }}-recovery">{{ __('webblocks-cms::two-factor.recovery_code') }}</label>
        <input class="wb-input" id="{{ $fieldPrefix }}-recovery" type="text" name="recovery_code" autocomplete="off" spellcheck="false" maxlength="64">
        <p class="wb-text-sm wb-text-muted" id="{{ $fieldPrefix }}-factor-help">{{ __('webblocks-cms::two-factor.factor_help') }}</p>
    </div>
@endif
