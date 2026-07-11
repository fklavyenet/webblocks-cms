@php
    $items = is_array($items ?? null) ? $items : [];
    $empty = $empty ?? 'None documented.';
    $code = (bool) ($code ?? false);
@endphp

@if ($items === [])
    <span class="wb-text-sm wb-text-muted">{{ $empty }}</span>
@else
    <div class="wb-stack wb-gap-1 wb-text-sm">
        @foreach ($items as $item)
            <div>
                @if ($code)
                    <code>{{ $item }}</code>
                @else
                    {{ $item }}
                @endif
            </div>
        @endforeach
    </div>
@endif
