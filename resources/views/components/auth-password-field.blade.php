@props([
    'id',
    'name',
    'label',
    'messages' => [],
    'value' => null,
    'autocomplete' => null,
    'required' => false,
    'autofocus' => false,
    'placeholder' => null,
    'wrapperClass' => 'wb-form-group',
    'labelClass' => null,
    'inputClass' => null,
])

@php
    $toggleId = $id.'_toggle';
    $hasErrors = filled($messages);
    $errorId = $id.'_error';
    $describedBy = trim(($attributes->get('aria-describedby') ? $attributes->get('aria-describedby').' ' : '').($hasErrors ? $errorId : ''));
@endphp

<div class="{{ $wrapperClass }}">
    <x-input-label for="{{ $id }}" :value="$label" :class="$labelClass" />

    <div class="wb-input-group wb-password-field" data-password-field>
        <x-text-input
            :id="$id"
            type="password"
            :name="$name"
            :value="$value"
            :required="$required"
            :autofocus="$autofocus"
            :autocomplete="$autocomplete"
            :placeholder="$placeholder"
            :aria-invalid="$hasErrors ? 'true' : 'false'"
            :aria-describedby="$describedBy !== '' ? $describedBy : null"
            :class="trim(($hasErrors ? 'wb-border-danger ' : '').($inputClass ?? ''))"
            data-password-input
            {{ $attributes->except(['aria-describedby']) }}
        />

        <button
            id="{{ $toggleId }}"
            type="button"
            class="wb-btn wb-btn-secondary wb-btn-icon wb-input-addon-btn wb-password-field-toggle"
            data-password-toggle
            aria-label="Show password"
            aria-controls="{{ $id }}"
            aria-pressed="false"
        >
            <i class="wb-icon wb-icon-eye" aria-hidden="true" data-password-toggle-icon></i>
            <span class="wb-sr-only" data-password-toggle-label>Show password</span>
        </button>
    </div>

    <x-input-error :messages="$messages" :id="$hasErrors ? $errorId : null" />
</div>
