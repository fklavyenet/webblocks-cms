<div class="wb-card wb-card-muted">
    <div class="wb-card-body">
        <div class="wb-stack wb-gap-2">
            <strong>{{ $block->title }}</strong>
            <p>{{ $block->content }}</p>
        </div>
    </div>
</div>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
