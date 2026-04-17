<section class="wb-section {{ ($block->variant ?? 'default') === 'muted' ? 'wb-section-muted' : '' }}">
    <div class="wb-stack wb-gap-3">
        @if ($block->title)
            <h2>{{ $block->title }}</h2>
        @endif

        @if ($block->content)
            <p>{{ $block->content }}</p>
        @endif

        @if ($block->children->isNotEmpty())
            <div class="wb-stack wb-gap-3">
                @foreach ($block->children as $child)
                    @include('pages.partials.block', ['block' => $child])
                @endforeach
            </div>
        @endif
    </div>
</section>
