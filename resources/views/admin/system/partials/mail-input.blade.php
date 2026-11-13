@php
    [$type, $example, $attributes] = $definition;
    $name = 'cms_mail_'.$field;
    $id = 'settings_'.$name;
@endphp
<div class="wb-field">
    <label for="{{ $id }}">{{ $systemText($field) }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" class="wb-input" value="{{ old($name, $customMail[$field]) }}" placeholder="{{ $mailPlaceholder($example) }}" {{ new \Illuminate\View\ComponentAttributeBag($attributes) }} @error($name) aria-invalid="true" aria-describedby="{{ $id }}_error" @enderror>
    @error($name)<p id="{{ $id }}_error" class="wb-field-error">{{ $message }}</p>@enderror
</div>
