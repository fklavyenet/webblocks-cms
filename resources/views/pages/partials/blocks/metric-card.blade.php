@php
    $value = $block->subtitle ?: $block->content ?: $block->title;
@endphp

<div class="wb-stat">
    @if ($block->title)
        <div class="wb-stat-label">{{ $block->title }}</div>
    @endif

    @if ($value)
        <div class="wb-stat-value">{{ $value }}</div>
    @endif

    @if ($block->subtitle && $block->content)
        <div class="wb-stat-delta">{{ $block->content }}</div>
    @endif
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
