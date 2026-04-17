@php
    $tone = match ($block->variant) {
        'success' => 'success',
        'warning' => 'warning',
        'danger' => 'danger',
        default => 'info',
    };
@endphp

<div class="wb-alert wb-alert-{{ $tone }}">
    <div>
        @if ($block->title)
            <div class="wb-alert-title">{{ $block->title }}</div>
        @endif
        <div>{{ $block->content }}</div>
    </div>
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
