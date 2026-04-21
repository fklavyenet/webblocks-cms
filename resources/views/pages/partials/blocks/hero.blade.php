<section class="wb-card wb-card-highlight wb-public-hero">
    <div class="wb-card-body wb-stack wb-gap-3">
        @if ($block->title)
            <h1>{{ $block->title }}</h1>
        @endif

        @if ($block->subtitle)
            <p>{{ $block->subtitle }}</p>
        @endif

        @if ($block->content)
            <div class="wb-prose">
                <p>{{ $block->content }}</p>
            </div>
        @endif
    </div>
</section>
