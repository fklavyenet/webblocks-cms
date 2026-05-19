@php
    $actor = $actor ?? null;
@endphp

@if ($actor)
    {{ $actor->name }}@if ($actor->email) <span class="wb-text-muted">({{ $actor->email }})</span>@endif
@else
    Not recorded
@endif
