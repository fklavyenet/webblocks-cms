@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'wb-field-error']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
