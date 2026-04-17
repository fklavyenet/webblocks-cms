@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'wb-alert wb-alert-success']) }}>
        {{ $status }}
    </div>
@endif
