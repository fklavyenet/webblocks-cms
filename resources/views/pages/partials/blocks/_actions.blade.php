@php
    $buttons = ($buttons ?? collect())
        ->filter(fn ($child) => in_array($child->typeSlug(), ['button', 'button_link'], true))
        ->values();
    $wrapperClass = $wrapperClass ?? 'wb-cluster wb-cluster-2';
@endphp

@if ($buttons->isNotEmpty())
    <div class="{{ $wrapperClass }}">
        @foreach ($buttons as $child)
            @include('webblocks-cms::pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
