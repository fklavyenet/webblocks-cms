<div class="wb-card">
    <div class="wb-card-header">
        <strong>{{ $block->title ?: 'Tab' }}</strong>
        @if ($block->subtitle)
            <span>{{ $block->subtitle }}</span>
        @endif
    </div>
    <div class="wb-card-body">
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
