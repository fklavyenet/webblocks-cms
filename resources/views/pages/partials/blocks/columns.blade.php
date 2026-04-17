<section class="wb-card wb-card-muted">
    <div class="wb-card-body wb-stack wb-gap-4">
        @if ($block->title || $block->subtitle)
            <div class="wb-stack wb-gap-1">
                @if ($block->title)
                    <h2>{{ $block->title }}</h2>
                @endif

                @if ($block->subtitle)
                    <p class="wb-text-muted">{{ $block->subtitle }}</p>
                @endif
            </div>
        @endif

        @if ($block->content)
            <div class="wb-prose">
                <div>{!! nl2br(e($block->content)) !!}</div>
            </div>
        @endif

        @if ($block->children->where('status', 'published')->isNotEmpty())
            <div class="wb-grid wb-grid-3">
                @foreach ($block->children->where('status', 'published')->sortBy('sort_order') as $child)
                    <div class="wb-card">
                        <div class="wb-card-body">
                            @include('pages.partials.block', ['block' => $child])
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
