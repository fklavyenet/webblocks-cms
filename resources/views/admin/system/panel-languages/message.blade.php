@if (str_contains($message, '|'))
    <ul class="wb-m-0" lang="{{ $language }}">
        @foreach (explode('|', $message) as $variant)
            <li>{{ trim($variant) }}</li>
        @endforeach
    </ul>
@else
    <span lang="{{ $language }}">{{ $message }}</span>
@endif
