<div class="wb-stack wb-gap-2">
    @if ($block->title)
        <strong>{{ $block->title }}</strong>
    @endif

    <p>{{ $block->content }}</p>
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
