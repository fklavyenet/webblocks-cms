@props([
    'showSlogan' => true,
    'sloganClass' => null,
])

<span>{{ config('app.name') }}</span>

@if ($showSlogan)
    <span @class([$sloganClass])>{{ config('app.slogan') }}</span>
@endif
