@php
    $buttons = ($buttons ?? collect())
        ->filter(fn ($child) => $child->typeSlug() === 'button')
        ->values();
    $wrapperClass = $wrapperClass ?? 'wb-cluster wb-cluster-2';
@endphp

@if ($buttons->isNotEmpty())
    <div class="{{ $wrapperClass }}">
        @foreach ($buttons as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
