@props(['value'])

<label {{ $attributes->merge(['class' => 'wb-label']) }}>
    {{ $value ?? $slot }}
</label>
