<section class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-2">
        @if ($block->title)
            <strong>{{ $block->title }}</strong>
        @endif

        @if ($block->content)
            <p class="wb-m-0">{{ $block->content }}</p>
        @endif
    </div>
</section>

@if ($block->children->isNotEmpty())
    <div class="wb-stack wb-gap-4">
        @foreach ($block->children as $child)
            @include('pages.partials.block', ['block' => $child])
        @endforeach
    </div>
@endif
